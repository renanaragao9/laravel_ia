<?php

declare(strict_types=1);

namespace App\Services\Draws;

use App\Contracts\ResultSource;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HtmlTableResultSource implements ResultSource
{
    public function name(): string
    {
        return 'scraper';
    }

    public function isConfigured(): bool
    {
        return (string) config('bicho.results.scraper.url') !== '';
    }

    public function fetch(CarbonInterface $date): array
    {
        $config = (array) config('bicho.results.scraper');

        if (! $this->isConfigured()) {
            throw new RuntimeException('A coleta automática não está configurada (RESULTS_SCRAPER_URL).');
        }

        $url = str_replace(
            ['{date}', '{d}', '{m}', '{Y}'],
            [$date->format('Y-m-d'), $date->format('d'), $date->format('m'), $date->format('Y')],
            (string) $config['url'],
        );

        $response = Http::withUserAgent((string) $config['user_agent'])->timeout((int) $config['timeout'])->get($url);

        if ($response->failed()) {
            throw new RuntimeException('A fonte de resultados respondeu com erro '.$response->status().'.');
        }

        return $this->parse($response->body(), $config);
    }

    public function parse(string $html, array $config): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $draws = [];

        foreach ((new DOMXPath($document))->query((string) $config['rows_xpath']) ?: [] as $row) {
            $cells = [];

            foreach ($row->childNodes as $node) {
                if (in_array($node->nodeName, ['td', 'th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
                }
            }

            $thousands = [];

            foreach ((array) $config['prize_columns'] as $offset => $column) {
                $digits = str_replace(['.', ' '], '', $cells[$column] ?? '');

                if ($digits !== '' && ctype_digit($digits) && strlen($digits) <= 4) {
                    $thousands[$offset + 1] = $digits;
                }
            }

            if (count($thousands) === count((array) $config['prize_columns'])) {
                $draws[] = ['lottery' => (string) $config['lottery'], 'time' => $cells[(int) $config['time_column']] ?? '', 'thousands' => $thousands];
            }
        }

        return $draws;
    }
}
