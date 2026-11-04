<?php

declare(strict_types=1);

namespace App\Services\Draws;

use App\Contracts\ResultSource;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LoteceResultSource implements ResultSource
{
    private const ORDER = ['Manhã' => 1, 'Tarde' => 2, 'Tarde II' => 3, 'Noite' => 4];

    public function name(): string
    {
        return 'lotece';
    }

    public function isConfigured(): bool
    {
        return (string) config('bicho.results.lotece.url') !== '';
    }

    public function fetch(CarbonInterface $date): array
    {
        $config = (array) config('bicho.results.lotece');

        try {
            $response = Http::withUserAgent((string) $config['user_agent'])
                ->timeout((int) $config['timeout'])
                ->retry(3, 1500, fn ($exception) => $exception instanceof ConnectionException || ($exception instanceof RequestException && $exception->response->serverError()), throw: false)
                ->get((string) $config['url'], ['data' => $date->format('d/m/Y')]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Falha de conexão com a Lotece: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('A Lotece respondeu com erro '.$response->status().' para '.$date->toDateString().'.');
        }

        return $this->parse($response->body(), $date);
    }

    public function parse(string $html, CarbonInterface $date): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $draws = [];

        foreach ($xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' data ')][.//div[contains(@class,'texto-extracao')]]") ?: [] as $header) {
            $label = $this->text($xpath, ".//div[contains(@class,'texto-extracao')]", $header);
            $day = $this->text($xpath, ".//div[contains(@class,'texto-dia')]", $header);

            if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $day, $match) !== 1 || $date->toDateString() !== $match[3].'-'.$match[2].'-'.$match[1]) {
                continue;
            }

            $thousands = [];

            foreach ($xpath->query('following-sibling::ul[1]/li', $header) ?: [] as $item) {
                $prize = (int) preg_replace('/\D+/', '', $this->text($xpath, ".//div[contains(@class,'premio')]", $item));
                $number = preg_replace('/\D+/', '', $this->text($xpath, ".//div[contains(@class,'numeros')]", $item)) ?? '';

                if ($prize >= 1 && strlen($number) === 4) {
                    $thousands[$prize] = $number;
                }
            }

            if ($thousands !== []) {
                $draws[] = ['lottery' => (string) config('bicho.results.lotece.lottery'), 'time' => $this->normalizeLabel($label), 'thousands' => $thousands];
            }
        }

        usort($draws, fn (array $a, array $b): int => (self::ORDER[$a['time']] ?? 99) <=> (self::ORDER[$b['time']] ?? 99));

        return $draws;
    }

    private function text(DOMXPath $xpath, string $query, DOMElement $context): string
    {
        $node = $xpath->query($query, $context)?->item(0);

        return $node === null ? '' : trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
    }

    private function normalizeLabel(string $label): string
    {
        $label = trim(preg_replace('/^(extra[cç][aã]o\s+)?(d[aoe]s?\s+)?/iu', '', $label) ?? $label);

        return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8') === 'Tarde Ii' ? 'Tarde II' : mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
    }
}
