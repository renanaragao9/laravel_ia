<?php

declare(strict_types=1);

namespace App\Support\Blog;

final class Disclaimer
{
    public const TEXT = 'Conteúdo de estatística e curiosidade sobre resultados já divulgados, destinado a maiores de 18 anos. O sorteio é aleatório: nenhum palpite aumenta a chance de acerto e não há garantia de nada. O jogo do bicho é proibido no Brasil e este site não incentiva apostas.';

    public static function ensureIn(string $markdown): string
    {
        if (str_contains(mb_strtolower($markdown), '18 anos') && str_contains(mb_strtolower($markdown), 'proibido')) {
            return $markdown;
        }

        return rtrim($markdown)."\n\n---\n\n**Aviso:** ".self::TEXT;
    }
}
