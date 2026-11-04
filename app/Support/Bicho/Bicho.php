<?php

declare(strict_types=1);

namespace App\Support\Bicho;

final class Bicho
{
    public const ANIMALS = [
        1 => 'Avestruz', 2 => 'Águia', 3 => 'Burro', 4 => 'Borboleta', 5 => 'Cachorro',
        6 => 'Cabra', 7 => 'Carneiro', 8 => 'Camelo', 9 => 'Cobra', 10 => 'Coelho',
        11 => 'Cavalo', 12 => 'Elefante', 13 => 'Galo', 14 => 'Gato', 15 => 'Jacaré',
        16 => 'Leão', 17 => 'Macaco', 18 => 'Porco', 19 => 'Pavão', 20 => 'Peru',
        21 => 'Touro', 22 => 'Tigre', 23 => 'Urso', 24 => 'Veado', 25 => 'Vaca',
    ];

    public static function normalizeThousand(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return str_pad(substr($digits, -4), 4, '0', STR_PAD_LEFT);
    }

    public static function ten(string $thousand): string
    {
        return substr(self::normalizeThousand($thousand), -2);
    }

    public static function hundred(string $thousand): string
    {
        return substr(self::normalizeThousand($thousand), -3);
    }

    public static function animalNumber(string $ten): int
    {
        $value = (int) $ten;

        return $value === 0 ? 25 : intdiv($value - 1, 4) + 1;
    }

    public static function animalName(int $number): string
    {
        return self::ANIMALS[$number] ?? 'Desconhecido';
    }

    public static function tensOf(int $number): array
    {
        $start = ($number - 1) * 4 + 1;

        return array_map(fn (int $ten): string => str_pad((string) ($ten % 100), 2, '0', STR_PAD_LEFT), range($start, $start + 3));
    }
}
