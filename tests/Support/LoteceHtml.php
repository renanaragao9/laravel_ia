<?php

namespace Tests\Support;

trait LoteceHtml
{
    protected function loteceHtml(array $extractions): string
    {
        $blocks = '';

        foreach ($extractions as [$label, $date, $numbers]) {
            $items = '';

            foreach ($numbers as $index => $number) {
                $items .= '<li><div class="premio">'.($index + 1).'° PRÊMIO</div><div class="numeros"> '.$number.' </div></li>';
            }

            $blocks .= '<div class="data branco"><div class="texto-extracao"> Extração '.$label.'</div><div class="texto-dia"> Dia '.$date.' </div></div><br /><div class="descricao"></div><ul>'.$items.'</ul>';
        }

        $blocks = '<div class="resultado"><div class="dataResultado">'.$blocks.'</div></div>';

        return '<html><body><div id="content">'.$blocks.'<div class="buscar"><h2>RESULTADOS ANTERIORES</h2></div></div></body></html>';
    }

    protected function ten(int $seed): array
    {
        return array_map(fn (int $i): string => str_pad((string) (($seed * 137 + $i * 911) % 10000), 4, '0', STR_PAD_LEFT), range(1, 10));
    }
}
