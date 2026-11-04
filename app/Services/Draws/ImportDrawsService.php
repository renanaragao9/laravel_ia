<?php

declare(strict_types=1);

namespace App\Services\Draws;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;

class ImportDrawsService
{
    public const MAX_ROWS = 200000;

    public function __construct(private readonly StoreDrawService $store) {}

    public function run(string $path, ?string $extension = null): array
    {
        $extension = Str::lower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        $rows = match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'csv', 'txt' => $this->readCsv($path),
            default => throw new InvalidArgumentException('Formato não suportado: use .csv ou .xlsx.'),
        };

        return $this->importRows($rows);
    }

    private function importRows(iterable $rows): array
    {
        $summary = ['draws' => 0, 'results' => 0, 'skipped' => 0, 'errors' => []];
        $header = null;
        $groups = [];
        $line = 0;

        foreach ($rows as $row) {
            $line++;
            $cells = array_map(fn (mixed $cell): mixed => is_string($cell) ? trim($cell) : $cell, $row);

            if ($header === null) {
                $header = $this->mapHeader($cells);

                if (! isset($header['date']) || (! isset($header['prize'], $header['thousand']) && $this->wideColumns($header) === [])) {
                    $summary['errors'][] = 'Cabeçalho inválido: use as colunas data, premio e milhar (ou data e p1..p5).';

                    return $summary;
                }

                continue;
            }

            if (implode('', array_map(fn (mixed $cell): string => $cell instanceof DateTimeInterface ? '1' : (string) $cell, $cells)) === '') {
                continue;
            }

            if ($line > self::MAX_ROWS) {
                $summary['errors'][] = 'Limite de '.self::MAX_ROWS.' linhas atingido; o restante foi ignorado.';

                break;
            }

            try {
                $date = $this->parseDate($cells[$header['date']] ?? null);
                $lottery = Str::lower((string) ($cells[$header['lottery'] ?? -1] ?? '')) ?: (string) config('bicho.results.default_lottery');
                $time = (string) ($cells[$header['time'] ?? -1] ?? '');
                $entries = [];

                if (isset($header['prize'], $header['thousand'])) {
                    $entries[$this->parsePosition($cells[$header['prize']] ?? null)] = $this->parseThousand($cells[$header['thousand']] ?? null);
                } else {
                    foreach ($this->wideColumns($header) as $position => $column) {
                        if (($cells[$column] ?? '') !== '') {
                            $entries[$position] = $this->parseThousand($cells[$column]);
                        }
                    }
                }

                $key = $date->toDateString().'|'.$lottery.'|'.$time;
                $groups[$key] ??= ['date' => $date, 'lottery' => $lottery, 'time' => $time, 'thousands' => []];
                $groups[$key]['thousands'] = $entries + $groups[$key]['thousands'];
            } catch (InvalidArgumentException $e) {
                $summary['skipped']++;
                $summary['errors'][] = "Linha {$line}: ".$e->getMessage();
            }
        }

        foreach (array_chunk($groups, 500) as $batch) {
            DB::transaction(function () use ($batch, &$summary): void {
                foreach ($batch as $group) {
                    $this->store->run($group['date'], $group['lottery'], $group['time'], $group['thousands'], 'import');
                    $summary['draws']++;
                    $summary['results'] += count($group['thousands']);
                }
            });
        }

        return $summary;
    }

    private function readXlsx(string $path): iterable
    {
        $reader = new Reader;
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield $row->toArray();
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    private function readCsv(string $path): iterable
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new InvalidArgumentException('Não foi possível abrir o arquivo.');
        }

        try {
            $first = (string) fgets($handle);
            rewind($handle);
            $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                yield array_map(fn (?string $cell): string => (string) $cell, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    private function mapHeader(array $cells): array
    {
        $aliases = [
            'data' => 'date', 'date' => 'date', 'dia' => 'date',
            'loteria' => 'lottery', 'lottery' => 'lottery',
            'horario' => 'time', 'hora' => 'time', 'time' => 'time', 'extracao' => 'time',
            'premio' => 'prize', 'posicao' => 'prize', 'prize' => 'prize',
            'milhar' => 'thousand', 'resultado' => 'thousand', 'thousand' => 'thousand',
            'p1' => 'p1', 'p2' => 'p2', 'p3' => 'p3', 'p4' => 'p4', 'p5' => 'p5',
            '1' => 'p1', '2' => 'p2', '3' => 'p3', '4' => 'p4', '5' => 'p5',
        ];

        $map = [];

        foreach ($cells as $index => $cell) {
            $key = Str::lower(Str::ascii(trim(str_replace(['º', '°', 'ª'], '', (string) $cell))));
            $key = preg_replace('/\s+/', '', $key) ?? $key;

            if (isset($aliases[$key])) {
                $map[$aliases[$key]] ??= $index;
            }
        }

        return $map;
    }

    private function wideColumns(array $header): array
    {
        $columns = [];

        foreach (range(1, 5) as $position) {
            if (isset($header["p{$position}"])) {
                $columns[$position] = $header["p{$position}"];
            }
        }

        return $columns;
    }

    private function parseDate(mixed $value): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $text = trim((string) $value);

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $text, $match) === 1) {
            return $this->validDate((int) $match[3], (int) $match[2], (int) $match[1], $text);
        }

        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $text, $match) === 1) {
            return $this->validDate((int) $match[1], (int) $match[2], (int) $match[3], $text);
        }

        throw new InvalidArgumentException("data inválida ({$text})");
    }

    private function validDate(int $year, int $month, int $day, string $original): Carbon
    {
        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException("data inválida ({$original})");
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private function parsePosition(mixed $value): int
    {
        $position = (int) preg_replace('/\D+/', '', (string) $value);

        if ($position < 1 || $position > 5) {
            throw new InvalidArgumentException('prêmio inválido ('.(string) $value.'), use de 1 a 5');
        }

        return $position;
    }

    private function parseThousand(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', is_float($value) ? (string) (int) $value : (string) $value) ?? '';

        if ($digits === '' || strlen($digits) > 4) {
            throw new InvalidArgumentException('milhar inválida ('.(string) $value.')');
        }

        return $digits;
    }
}
