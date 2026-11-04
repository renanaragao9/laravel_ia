<?php

namespace Tests\Unit\Services;

use App\Models\Draw;
use App\Models\DrawResult;
use App\Services\Draws\ImportDrawsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportDrawsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $extension, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'draws').'.'.$extension;
        file_put_contents($path, $content);

        return $path;
    }

    public function test_imports_the_long_csv_format_with_semicolons(): void
    {
        $path = $this->file('csv', "data;loteria;horario;premio;milhar\n01/11/2026;rio;14:00;1;7345\n01/11/2026;rio;14:00;2;12\n01/11/2026;rio;18:00;1;9997\n");

        $summary = app(ImportDrawsService::class)->run($path);

        $this->assertSame(2, $summary['draws']);
        $this->assertSame(3, $summary['results']);
        $this->assertSame([], $summary['errors']);
        $this->assertDatabaseHas('draw_results', ['thousand' => '7345', 'hundred' => '345', 'ten' => '45', 'animal_number' => 12, 'position' => 1]);
        $this->assertDatabaseHas('draw_results', ['thousand' => '0012', 'ten' => '12', 'animal_number' => 3, 'position' => 2]);
        $this->assertDatabaseHas('draw_results', ['thousand' => '9997', 'animal_number' => 25]);
    }

    public function test_imports_the_wide_csv_format_with_commas_and_iso_dates(): void
    {
        $path = $this->file('csv', "data,horario,p1,p2,p3,p4,p5\n2026-11-02,PTM,1111,2222,3333,4444,5555\n");

        $summary = app(ImportDrawsService::class)->run($path);

        $this->assertSame(1, $summary['draws']);
        $this->assertSame(5, $summary['results']);
        $this->assertSame('rio', Draw::firstOrFail()->lottery);
        $this->assertSame(['1111', '2222', '3333', '4444', '5555'], DrawResult::orderBy('position')->pluck('thousand')->all());
    }

    public function test_imports_xlsx_files(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'draws').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Data', 'Horário', 'Prêmio', 'Milhar']));
        $writer->addRow(Row::fromValues(['03/11/2026', '11:00', '1º', '4321']));
        $writer->addRow(Row::fromValues(['03/11/2026', '11:00', '2º', 8765]));
        $writer->close();

        $summary = app(ImportDrawsService::class)->run($path);

        $this->assertSame(1, $summary['draws']);
        $this->assertSame(2, $summary['results']);
        $this->assertDatabaseHas('draw_results', ['thousand' => '8765', 'position' => 2]);
    }

    public function test_importing_the_same_file_twice_does_not_duplicate_and_updates_values(): void
    {
        $path = $this->file('csv', "data;horario;premio;milhar\n01/11/2026;14:00;1;1111\n");
        app(ImportDrawsService::class)->run($path);

        $corrected = $this->file('csv', "data;horario;premio;milhar\n01/11/2026;14:00;1;2222\n");
        app(ImportDrawsService::class)->run($corrected);

        $this->assertSame(1, Draw::count());
        $this->assertSame(1, DrawResult::count());
        $this->assertSame('2222', DrawResult::firstOrFail()->thousand);
    }

    public function test_restores_soft_deleted_draws_when_reimported(): void
    {
        $path = $this->file('csv', "data;horario;premio;milhar\n01/11/2026;14:00;1;1111\n");
        app(ImportDrawsService::class)->run($path);
        Draw::firstOrFail()->delete();

        app(ImportDrawsService::class)->run($path);

        $this->assertSame(1, Draw::count());
    }

    public function test_invalid_rows_are_skipped_with_the_line_number(): void
    {
        $path = $this->file('csv', "data;horario;premio;milhar\n31/02/2026;14:00;1;1111\n01/11/2026;14:00;9;1111\n01/11/2026;14:00;1;abc\n01/11/2026;14:00;1;12345\n02/11/2026;14:00;1;5555\n");

        $summary = app(ImportDrawsService::class)->run($path);

        $this->assertSame(4, $summary['skipped']);
        $this->assertSame(1, $summary['draws']);
        $this->assertStringContainsString('Linha 2', $summary['errors'][0]);
        $this->assertStringContainsString('Linha 3', $summary['errors'][1]);
    }

    public function test_rejects_files_without_the_expected_header_or_with_an_unknown_extension(): void
    {
        $summary = app(ImportDrawsService::class)->run($this->file('csv', "foo;bar\n1;2\n"));

        $this->assertSame(0, $summary['draws']);
        $this->assertStringContainsString('Cabeçalho inválido', $summary['errors'][0]);

        $this->expectException(InvalidArgumentException::class);
        app(ImportDrawsService::class)->run($this->file('pdf', 'x'));
    }
}
