<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Llm\LlmResponse;

interface LlmClient
{
    public function chat(array $messages, array $tools = []): LlmResponse;
}
