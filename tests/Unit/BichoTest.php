<?php

namespace Tests\Unit;

use App\Support\Bicho\Bicho;
use PHPUnit\Framework\TestCase;

class BichoTest extends TestCase
{
    public function test_animal_number_follows_the_traditional_groups(): void
    {
        $expected = ['01' => 1, '04' => 1, '05' => 2, '33' => 9, '36' => 9, '37' => 10, '96' => 24, '97' => 25, '99' => 25, '00' => 25];

        foreach ($expected as $ten => $number) {
            $this->assertSame($number, Bicho::animalNumber($ten), "dezena {$ten}");
        }
    }

    public function test_every_group_owns_four_distinct_tens_and_all_hundred_numbers_are_covered(): void
    {
        $all = [];

        foreach (array_keys(Bicho::ANIMALS) as $number) {
            $tens = Bicho::tensOf($number);
            $this->assertCount(4, $tens);

            foreach ($tens as $ten) {
                $this->assertSame($number, Bicho::animalNumber($ten));
                $all[] = $ten;
            }
        }

        $this->assertCount(100, array_unique($all));
        $this->assertSame(['97', '98', '99', '00'], Bicho::tensOf(25));
    }

    public function test_thousand_normalization_and_derived_values(): void
    {
        $this->assertSame('0012', Bicho::normalizeThousand('12'));
        $this->assertSame('7345', Bicho::normalizeThousand('7.345'));
        $this->assertSame('2345', Bicho::normalizeThousand('12345'));
        $this->assertSame('45', Bicho::ten('7345'));
        $this->assertSame('345', Bicho::hundred('7345'));
        $this->assertSame('Vaca', Bicho::animalName(25));
        $this->assertSame('Desconhecido', Bicho::animalName(99));
    }
}
