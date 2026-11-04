<?php

declare(strict_types=1);

namespace App\Support\Llm;

final readonly class LlmResponse
{
    public function __construct(
        public ?string $content,
        public array $toolCalls,
        public array $message,
        public string $model,
        public int $promptTokens,
        public int $completionTokens,
    ) {}
}
