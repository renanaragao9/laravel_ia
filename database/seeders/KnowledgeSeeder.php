<?php

namespace Database\Seeders;

use App\Enums\KnowledgeKind;
use App\Models\KnowledgeDocument;
use App\Support\Bicho\Bicho;
use Illuminate\Database\Seeder;

class KnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $document) {
            KnowledgeDocument::query()->updateOrCreate(
                ['title' => $document['title'], 'kind' => $document['kind']->value],
                ['source' => 'seed', 'content' => $document['content']],
            );
        }
    }

    private function documents(): array
    {
        return [
            ['title' => 'Tabela dos 25 grupos e suas dezenas', 'kind' => KnowledgeKind::Animals, 'content' => $this->table()],
            ['title' => 'Significados populares dos animais', 'kind' => KnowledgeKind::Dreams, 'content' => $this->meanings()],
            ['title' => 'Termos e regras do jogo do bicho', 'kind' => KnowledgeKind::Rules, 'content' => $this->rules()],
            ['title' => 'Guia de estilo e regras editoriais dos posts', 'kind' => KnowledgeKind::Style, 'content' => $this->style()],
        ];
    }

    private function table(): string
    {
        $lines = ['Cada grupo (animal) reúne quatro dezenas consecutivas. A dezena 00 pertence ao grupo 25, a Vaca.'];

        foreach (Bicho::ANIMALS as $number => $name) {
            $lines[] = 'Grupo '.str_pad((string) $number, 2, '0', STR_PAD_LEFT).' - '.$name.': dezenas '.implode(', ', Bicho::tensOf($number)).'.';
        }

        return implode("\n", $lines);
    }

    private function meanings(): string
    {
        $meanings = json_decode((string) file_get_contents(__DIR__.'/data/animal_meanings.json'), true, flags: JSON_THROW_ON_ERROR);
        $lines = ['Associações populares de cada animal, vindas do folclore e da cultura popular. São curiosidades, sem nenhum valor estatístico ou preditivo.'];

        foreach (Bicho::ANIMALS as $number => $name) {
            $lines[] = $name.' (grupo '.$number.'): '.$meanings[(string) $number].'.';
        }

        return implode("\n", $lines);
    }

    private function rules(): string
    {
        return <<<'TXT'
A milhar é o número de quatro dígitos sorteado em cada prêmio, por exemplo 7345. A centena são os três últimos dígitos (345) e a dezena são os dois últimos (45). O grupo, ou animal, é definido pela dezena: cada animal reúne quatro dezenas, e a dezena 00 pertence à Vaca.

Cada extração sorteia cinco prêmios, do 1º ao 5º, e cada prêmio tem a sua milhar. Em um mesmo dia acontecem várias extrações em horários diferentes, e cada loteria tem os seus horários.

Cada sorteio é independente dos anteriores. Um número ou animal que não sai há muitos dias não fica mais provável de sair: o chamado atraso é apenas uma contagem de dias e não uma previsão. Frequências do passado descrevem o que aconteceu, e não o que vai acontecer.

O jogo do bicho é um jogo de azar proibido no Brasil e configura contravenção penal. Este conteúdo trata apenas de estatística e curiosidade sobre resultados já divulgados, não incentiva apostas e é destinado a maiores de 18 anos.
TXT;
    }

    private function style(): string
    {
        return <<<'TXT'
Escreva em português do Brasil, com tom leve, curioso e direto, como uma coluna de curiosidades e estatística. Nunca use linguagem de promessa, como "vai cair", "certeza" ou "aposta garantida".

Estrutura do post: um título criativo com a data; uma abertura curta; o que saiu nos últimos resultados; os números em destaque do ponto de vista estatístico (mais frequentes e mais atrasados, sempre dizendo qual período foi analisado); o histórico em datas parecidas; os palpites do dia como curiosidade; e um aviso final.

Regras editoriais obrigatórias: use somente números e resultados que vieram das ferramentas, sem inventar nenhum dado; deixe claro que o sorteio é aleatório e que nenhum palpite aumenta a chance de acertar; sugira no máximo cinco grupos e dez dezenas; não cite casas de apostas, links de aposta nem valores; inclua o aviso de que o conteúdo é para maiores de 18 anos e que o jogo é proibido no Brasil.

Tamanho: entre 350 e 600 palavras, em Markdown, com subtítulos de nível dois e listas curtas quando ajudarem.
TXT;
    }
}
