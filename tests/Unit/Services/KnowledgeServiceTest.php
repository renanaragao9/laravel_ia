<?php

namespace Tests\Unit\Services;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeTerm;
use App\Services\Rag\RetrieveKnowledgeService;
use App\Services\Rag\TrainKnowledgeService;
use Database\Seeders\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function doc(string $title, string $content, KnowledgeKind $kind = KnowledgeKind::Rules): KnowledgeDocument
    {
        return KnowledgeDocument::create(['title' => $title, 'kind' => $kind, 'content' => $content]);
    }

    public function test_training_builds_terms_chunks_and_vectors(): void
    {
        $this->doc('Gatos', 'O gato caça ratos à noite.');
        $this->doc('Cães', 'O cachorro cuida da casa.');

        $result = app(TrainKnowledgeService::class)->run();

        $this->assertSame(['documents' => 2, 'chunks' => 2, 'terms' => KnowledgeTerm::count()], $result);
        $this->assertGreaterThan(4, $result['terms']);
        $this->assertTrue(KnowledgeChunk::all()->every(fn (KnowledgeChunk $chunk) => $chunk->norm > 0 && $chunk->vector !== []));
        $this->assertNotNull(KnowledgeDocument::first()->trained_at);
    }

    public function test_training_is_repeatable_and_replaces_the_previous_index(): void
    {
        $document = $this->doc('Gatos', 'O gato caça ratos.');
        app(TrainKnowledgeService::class)->run();
        $first = KnowledgeChunk::count();

        $document->update(['content' => 'Agora fala de tartarugas marinhas.']);
        app(TrainKnowledgeService::class)->run();

        $this->assertSame($first, KnowledgeChunk::count());
        $this->assertNull(KnowledgeTerm::where('term', 'rato')->first());
        $this->assertNotNull(KnowledgeTerm::where('term', 'tartaruga')->first());
    }

    public function test_retrieval_ranks_the_most_similar_chunk_first(): void
    {
        $this->doc('Gatos', 'O gato é independente e caça ratos durante a noite.');
        $this->doc('Cães', 'O cachorro é leal e protege a casa.');
        $this->doc('Frutas', 'A banana e a laranja são frutas ricas em vitaminas.');
        app(TrainKnowledgeService::class)->run();

        $found = app(RetrieveKnowledgeService::class)->run('quem caça ratos?');

        $this->assertSame('Gatos', $found[0]['title']);
        $this->assertGreaterThan(0, $found[0]['score']);
        $this->assertSame('Cães', app(RetrieveKnowledgeService::class)->run('cachorro leal')[0]['title']);
    }

    public function test_retrieval_returns_nothing_for_unrelated_or_empty_queries_or_untrained_index(): void
    {
        $this->doc('Gatos', 'O gato caça ratos.');

        $this->assertSame([], app(RetrieveKnowledgeService::class)->run('gato'));

        app(TrainKnowledgeService::class)->run();

        $this->assertSame([], app(RetrieveKnowledgeService::class)->run('astronomia quântica'));
        $this->assertSame([], app(RetrieveKnowledgeService::class)->run('   '));
        $this->assertSame([], app(RetrieveKnowledgeService::class)->run('o de a'));
    }

    public function test_retrieval_can_be_limited_by_kind_and_count_and_ignores_deleted_documents(): void
    {
        $style = $this->doc('Estilo', 'Escreva com tom leve sobre curiosidades.', KnowledgeKind::Style);
        $this->doc('Regras', 'Curiosidades sobre regras do jogo.', KnowledgeKind::Rules);
        app(TrainKnowledgeService::class)->run();

        $this->assertCount(2, app(RetrieveKnowledgeService::class)->run('curiosidades'));
        $this->assertCount(1, app(RetrieveKnowledgeService::class)->run('curiosidades', 1));
        $this->assertSame(['Estilo'], array_column(app(RetrieveKnowledgeService::class)->run('curiosidades', 4, ['style']), 'title'));

        $style->delete();
        app(TrainKnowledgeService::class)->run();

        $this->assertSame(['Regras'], array_column(app(RetrieveKnowledgeService::class)->run('curiosidades'), 'title'));
    }

    public function test_seeded_knowledge_answers_real_questions(): void
    {
        $this->seed(KnowledgeSeeder::class);
        app(TrainKnowledgeService::class)->run();
        $retrieve = app(RetrieveKnowledgeService::class);

        $this->assertSame(KnowledgeKind::Rules->value, $retrieve->run('o que é milhar centena e dezena')[0]['kind']);
        $this->assertSame(KnowledgeKind::Style->value, $retrieve->run('quais regras editoriais e tom devo usar nos posts')[0]['kind']);

        $cobra = $retrieve->run('cobra traição veneno');
        $this->assertSame(KnowledgeKind::Dreams->value, $cobra[0]['kind']);
        $this->assertStringContainsString('Cobra (grupo 9)', $cobra[0]['content']);

        $table = $retrieve->run('dezenas do grupo 25 vaca');
        $this->assertStringContainsString('97, 98, 99, 00', collect($table)->pluck('content')->implode(' '));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(KnowledgeSeeder::class);
        $this->seed(KnowledgeSeeder::class);

        $this->assertSame(4, KnowledgeDocument::count());
    }
}
