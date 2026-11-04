<?php

declare(strict_types=1);

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeTerm;
use App\Support\Rag\Tokenizer;

class RetrieveKnowledgeService
{
    public function run(string $query, int $limit = 4, ?array $kinds = null): array
    {
        $counts = Tokenizer::counts($query);

        if ($counts === []) {
            return [];
        }

        $terms = KnowledgeTerm::query()->whereIn('term', array_keys($counts))->get()->keyBy('term');

        if ($terms->isEmpty()) {
            return [];
        }

        $vector = [];
        $squares = 0.0;

        foreach ($terms as $term => $model) {
            $weight = (1 + log($counts[$term])) * $model->idf;
            $vector[$model->id] = $weight;
            $squares += $weight ** 2;
        }

        $queryNorm = sqrt($squares);
        $minScore = (float) config('bicho.rag.min_score');
        $scored = [];

        KnowledgeChunk::query()
            ->with('document:id,title,kind')
            ->when($kinds, fn ($q) => $q->whereHas('document', fn ($d) => $d->whereIn('kind', $kinds)))
            ->cursor()
            ->each(function (KnowledgeChunk $chunk) use ($vector, $queryNorm, $minScore, &$scored): void {
                if ($chunk->norm <= 0 || $chunk->document === null) {
                    return;
                }

                $dot = 0.0;

                foreach ($vector as $termId => $weight) {
                    $dot += $weight * (float) ($chunk->vector[$termId] ?? 0);
                }

                $score = $dot / ($queryNorm * $chunk->norm);

                if ($score >= $minScore) {
                    $scored[] = [
                        'document_id' => $chunk->knowledge_document_id,
                        'title' => $chunk->document->title,
                        'kind' => $chunk->document->kind->value,
                        'score' => round($score, 4),
                        'content' => $chunk->content,
                    ];
                }
            });

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }
}
