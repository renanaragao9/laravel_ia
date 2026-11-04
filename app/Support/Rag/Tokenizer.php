<?php

declare(strict_types=1);

namespace App\Support\Rag;

use Illuminate\Support\Str;

final class Tokenizer
{
    private const STOPWORDS = [
        'a', 'o', 'as', 'os', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
        'por', 'para', 'com', 'sem', 'sob', 'sobre', 'e', 'ou', 'mas', 'que', 'se', 'ao', 'aos', 'ate', 'como', 'mais',
        'menos', 'muito', 'pouco', 'ja', 'nao', 'sim', 'ser', 'estar', 'foi', 'sao', 'era', 'eh', 'ha', 'tem', 'ter',
        'seu', 'sua', 'seus', 'suas', 'ele', 'ela', 'eles', 'elas', 'isso', 'isto', 'esse', 'essa', 'este', 'esta',
        'qual', 'quais', 'quando', 'onde', 'quem', 'entre', 'depois', 'antes', 'tambem', 'so', 'pelo', 'pela', 'pelos', 'pelas',
    ];

    public static function tokens(string $text): array
    {
        $normalized = Str::lower(Str::ascii($text));
        $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];

        foreach ($words as $word) {
            if (in_array($word, self::STOPWORDS, true) || (strlen($word) < 2 && ! ctype_digit($word))) {
                continue;
            }

            $tokens[] = self::stem($word);
        }

        return $tokens;
    }

    public static function counts(string $text): array
    {
        return array_count_values(self::tokens($text));
    }

    private static function stem(string $word): string
    {
        if (ctype_digit($word) || strlen($word) <= 3) {
            return $word;
        }

        foreach (['oes', 'aes', 'ais', 'eis', 'ois'] as $suffix) {
            if (str_ends_with($word, $suffix)) {
                return substr($word, 0, -strlen($suffix)).match ($suffix) {
                    'oes', 'aes' => 'ao',
                    default => substr($suffix, 0, 1).'l',
                };
            }
        }

        if (str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }
}
