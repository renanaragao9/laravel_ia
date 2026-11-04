<?php

namespace App\Enums;

enum KnowledgeKind: string
{
    case Animals = 'animals';
    case Dreams = 'dreams';
    case Rules = 'rules';
    case Style = 'style';
    case Post = 'post';

    public function label(): string
    {
        return match ($this) {
            self::Animals => 'Animais e dezenas',
            self::Dreams => 'Sonhos e palpites',
            self::Rules => 'Regras e termos',
            self::Style => 'Guia de estilo',
            self::Post => 'Post publicado',
        };
    }
}
