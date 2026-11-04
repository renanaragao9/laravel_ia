<?php

declare(strict_types=1);

namespace App\Services\Draws;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class ExportDrawsXlsxService
{
    public function run(array $draws, string $path): int
    {
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['data', 'loteria', 'horario', 'premio', 'milhar']));

        $rows = 0;

        foreach ($draws as $draw) {
            ksort($draw['thousands']);

            foreach ($draw['thousands'] as $position => $thousand) {
                $writer->addRow(Row::fromValues([Carbon::parse($draw['date'])->format('d/m/Y'), $draw['lottery'], $draw['time'], $position, (string) $thousand]));
                $rows++;
            }
        }

        $writer->close();

        return $rows;
    }
}
