<?php

declare(strict_types=1);

namespace App\Services\Rag;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeTerm;
use App\Support\Rag\Chunker;
use App\Support\Rag\Tokenizer;
use Illuminate\Support\Facades\DB;

class TrainKnowledgeService
{
    public function run(): array
    {
        return DB::transaction(function (): array {
            KnowledgeChunk::withTrashed()->forceDelete();
            KnowledgeTerm::withTrashed()->forceDelete();

            $size = (int) config('bicho.rag.chunk_size');
            $pieces = [];

            foreach (KnowledgeDocument::query()->orderBy('id')->get() as $document) {
                foreach (Chunker::split($document->content, $size) as $position => $content) {
                    $pieces[] = ['document' => $document, 'position' => $position, 'content' => $content, 'counts' => Tokenizer::counts($document->title.' '.$content)];
                }
            }

            $frequency = [];

            foreach ($pieces as $piece) {
                foreach (array_keys($piece['counts']) as $term) {
                    $frequency[$term] = ($frequency[$term] ?? 0) + 1;
                }
            }

            $total = count($pieces);
            $now = now();
            $termIds = [];

            foreach (array_chunk(array_keys($frequency), 200) as $batch) {
                foreach ($batch as $term) {
                    $termIds[$term] = KnowledgeTerm::create([
                        'term' => $term,
                        'document_frequency' => $frequency[$term],
                        'idf' => $this->idf($total, $frequency[$term]),
                    ])->id;
                }
            }

            foreach ($pieces as $piece) {
                $vector = [];
                $squares = 0.0;

                foreach ($piece['counts'] as $term => $count) {
                    $weight = (1 + log($count)) * $this->idf($total, $frequency[$term]);
                    $vector[$termIds[$term]] = round($weight, 6);
                    $squares += $weight ** 2;
                }

                KnowledgeChunk::create([
                    'knowledge_document_id' => $piece['document']->id,
                    'position' => $piece['position'],
                    'content' => $piece['content'],
                    'vector' => $vector,
                    'norm' => round(sqrt($squares), 8),
                ]);
            }

            KnowledgeDocument::query()->update(['trained_at' => $now]);

            return ['documents' => KnowledgeDocument::query()->count(), 'chunks' => $total, 'terms' => count($termIds)];
        });
    }

    private function idf(int $total, int $documentFrequency): float
    {
        return log(($total + 1) / ($documentFrequency + 1)) + 1;
    }
}
