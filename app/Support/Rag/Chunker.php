<?php

declare(strict_types=1);

namespace App\Support\Rag;

final class Chunker
{
    public static function split(string $text, int $size): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            foreach (self::pieces(trim($paragraph), $size) as $piece) {
                if ($current !== '' && mb_strlen($current) + mb_strlen($piece) + 2 > $size) {
                    $chunks[] = $current;
                    $current = '';
                }

                $current = $current === '' ? $piece : $current."\n\n".$piece;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    private static function pieces(string $paragraph, int $size): array
    {
        if ($paragraph === '') {
            return [];
        }

        if (mb_strlen($paragraph) <= $size) {
            return [$paragraph];
        }

        $pieces = [];
        $current = '';

        foreach (preg_split('/(?<=[.!?])\s+|\n/u', $paragraph) ?: [] as $sentence) {
            if ($current !== '' && mb_strlen($current) + mb_strlen($sentence) + 1 > $size) {
                $pieces[] = $current;
                $current = '';
            }

            $current = $current === '' ? $sentence : $current.' '.$sentence;
        }

        if ($current !== '') {
            $pieces[] = $current;
        }

        return $pieces;
    }
}
