<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Animal;
use App\Support\Bicho\Bicho;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('consultar_animal')]
#[Description('Consulta um animal (grupo) pelo número do grupo, pelo nome ou por uma dezena, e devolve suas quatro dezenas.')]
class AnimalLookupTool extends BichoTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'grupo' => $schema->integer()->min(1)->max(25)->description('Número do grupo (1 a 25).'),
            'nome' => $schema->string()->description('Nome do animal, por exemplo "Cobra".'),
            'dezena' => $schema->string()->description('Dezena com dois dígitos, por exemplo "33".'),
        ];
    }

    public function handle(Request $request): Response
    {
        $request->validate([
            'grupo' => ['nullable', 'integer', 'between:1,25'],
            'nome' => ['nullable', 'string', 'max:40'],
            'dezena' => ['nullable', 'regex:/^\d{1,2}$/'],
        ]);

        $animal = match (true) {
            $request->get('grupo') !== null => Animal::query()->where('number', (int) $request->get('grupo'))->first(),
            $request->get('dezena') !== null => Animal::query()->where('number', Bicho::animalNumber((string) $request->get('dezena')))->first(),
            $request->get('nome') !== null => Animal::query()->whereRaw('lower(name) = ?', [mb_strtolower((string) $request->get('nome'))])->first(),
            default => null,
        };

        if ($animal === null) {
            return Response::error('Animal não encontrado. Informe grupo, nome ou dezena.');
        }

        return Response::json(['grupo' => $animal->number, 'nome' => $animal->name, 'dezenas' => $animal->tens]);
    }
}
