<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\KnowledgeKind;
use App\Services\Rag\RetrieveKnowledgeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('buscar_conhecimento')]
#[Description('Busca na base de conhecimento (RAG) trechos sobre a tabela dos animais, significados populares, regras e termos do jogo, guia de estilo e posts anteriores.')]
class SearchKnowledgeTool extends BichoTool
{
    public function __construct(private readonly RetrieveKnowledgeService $retrieve) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'pergunta' => $schema->string()->min(2)->max(300)->description('O que você quer saber, em linguagem natural.')->required(),
            'limite' => $schema->integer()->min(1)->max(8)->description('Quantos trechos devolver. Padrão: 4.'),
            'tipos' => $schema->array()->items($schema->string()->enum(array_map(fn (KnowledgeKind $kind) => $kind->value, KnowledgeKind::cases())))->description('Restringe a busca a tipos de documento.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'pergunta' => ['required', 'string', 'min:2', 'max:300'],
            'limite' => ['nullable', 'integer', 'between:1,8'],
            'tipos' => ['nullable', 'array'],
            'tipos.*' => [Rule::in(array_map(fn (KnowledgeKind $kind) => $kind->value, KnowledgeKind::cases()))],
        ]);

        $found = $this->retrieve->run($data['pergunta'], (int) ($data['limite'] ?? 4), $data['tipos'] ?? null);

        return Response::json([
            'pergunta' => $data['pergunta'],
            'trechos' => $found,
            'aviso' => $found === [] ? 'Nada relevante foi encontrado na base de conhecimento.' : null,
        ]);
    }
}
