<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StatisticsPage;
use App\Filament\Resources\LoginAttempts\LoginAttemptResource;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\Page;
use App\Models\PageView;
use App\Support\Permissions;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard: visitors, pages and security at a glance, each shown only with the matching permission.
 */
class SiteStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $visitors = fn ($from) => (int) PageView::query()->where('created_at', '>=', $from)->selectRaw("COUNT(DISTINCT CONCAT(DATE(created_at), '-', visitor)) AS total")->value('total');
        $stats = [];

        if ($user?->hasPermission(Permissions::STATISTICS)) {
            $stats[] = Stat::make('Bezoekers vandaag', $visitors(today()))->url(StatisticsPage::getUrl(['period' => 'today']));
            $stats[] = Stat::make('Bezoekers (30 dagen)', $visitors(today()->subDays(29)))
                ->description(PageView::query()->where('created_at', '>=', today()->subDays(29))->count().' paginaweergaven')
                ->url(StatisticsPage::getUrl());
        }

        if ($user?->hasPermission(Permissions::PAGES)) {
            $stats[] = Stat::make('Pagina\'s', Page::query()->count())->description(Page::query()->where('is_published', false)->count().' niet gepubliceerd');
        }

        if ($user?->hasPermission(Permissions::SECURITY)) {
            $stats[] = Stat::make('Mislukte aanmeldingen (24 uur)', LoginAttempt::query()->where('successful', false)->where('created_at', '>=', now()->subDay())->count())
                ->description(BlockedIp::query()->active()->count().' geblokkeerde IP\'s')
                ->color('danger')
                ->url(LoginAttemptResource::getUrl());
        }

        return $stats;
    }

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyPermission(Permissions::STATISTICS, Permissions::PAGES, Permissions::SECURITY);
    }
}
