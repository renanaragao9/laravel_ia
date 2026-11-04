<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\Post;
use App\Services\Stats\DrawStatsService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class OverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totals = app(DrawStatsService::class)->totals();
        $drafts = Post::query()->where('status', PostStatus::Draft->value)->count();

        return [
            Stat::make('Sorteios na base', number_format($totals['draws'], 0, ',', '.'))
                ->description($totals['last_date'] ? 'Último: '.Carbon::parse($totals['last_date'])->format('d/m/Y') : 'Importe uma planilha para começar')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color($totals['draws'] > 0 ? 'success' : 'warning'),

            Stat::make('Posts aguardando revisão', $drafts)
                ->description('rascunhos gerados pela IA')
                ->descriptionIcon(Heroicon::OutlinedNewspaper)
                ->color($drafts > 0 ? 'warning' : 'gray'),

            Stat::make('Posts publicados', Post::query()->where('status', PostStatus::Published->value)->count())
                ->description('visíveis no blog')
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->color('success'),

            Stat::make('Índice de conhecimento', KnowledgeChunk::query()->count().' trechos')
                ->description(KnowledgeDocument::query()->count().' documento(s)')
                ->descriptionIcon(Heroicon::OutlinedBookOpen)
                ->color('gray'),
        ];
    }
}
