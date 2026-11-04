<?php

declare(strict_types=1);

namespace App\Support\Blog;

final class Disclaimer
{
    public const TEXT = 'Conteúdo de estatística e curiosidade sobre resultados já divulgados, destinado a maiores de 18 anos. O sorteio é aleatório: nenhum palpite aumenta a chance de acerto e não há garantia de nada. Resultados da Lotece (Loteria Estadual do Ceará). Este não é um canal oficial da Lotece e não incentiva apostas. Jogue com responsabilidade.';

    public static function ensureIn(string $markdown): string
    {
        if (str_contains(mb_strtolower($markdown), '18 anos') && str_contains(mb_strtolower($markdown), 'lotece')) {
            return $markdown;
        }

        return rtrim($markdown)."\n\n---\n\n**Aviso:** ".self::TEXT;
    }
}
