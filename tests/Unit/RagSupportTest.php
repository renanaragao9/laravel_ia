<?php

namespace Tests\Unit;

use App\Support\Rag\Chunker;
use App\Support\Rag\Tokenizer;
use PHPUnit\Framework\TestCase;

class RagSupportTest extends TestCase
{
    public function test_tokenizer_folds_accents_drops_stopwords_and_stems_plurals(): void
    {
        $this->assertSame(['cobra', 'veneno', 'traicao'], Tokenizer::tokens('A Cobra e o veneno da traição'));
        $this->assertSame(['animal', 'opcao'], Tokenizer::tokens('animais opções'));
        $this->assertSame(['dezena', '33', 'cobra'], Tokenizer::tokens('dezenas 33 cobras'));
        $this->assertSame([], Tokenizer::tokens('o de a e'));
    }

    public function test_counts_aggregate_repeated_terms(): void
    {
        $this->assertSame(['gato' => 2, 'rato' => 1], Tokenizer::counts('gato rato gatos'));
    }

    public function test_chunker_keeps_paragraphs_together_up_to_the_size_and_splits_long_ones(): void
    {
        $this->assertSame(['um parágrafo curto'], Chunker::split('um parágrafo curto', 100));

        $chunks = Chunker::split("aaa aaa aaa\n\nbbb bbb bbb\n\nccc ccc ccc", 25);
        $this->assertSame(["aaa aaa aaa\n\nbbb bbb bbb", 'ccc ccc ccc'], $chunks);

        $long = Chunker::split(str_repeat('Frase um. Frase dois. ', 20), 60);
        $this->assertGreaterThan(3, count($long));
        foreach ($long as $chunk) {
            $this->assertLessThanOrEqual(60, mb_strlen($chunk));
        }

        $this->assertSame([], Chunker::split('   ', 100));
    }
}
