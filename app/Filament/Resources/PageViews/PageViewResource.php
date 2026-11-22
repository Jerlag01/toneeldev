<?php

namespace App\Filament\Resources\PageViews;

use App\Enums\NavigationGroup;
use App\Filament\Resources\LoginAttempts\BlockedIpDurations;
use App\Filament\Resources\PageViews\Pages\ListPageViews;
use App\Models\BlockedIp;
use App\Models\PageView;
use App\Support\Permissions;
use App\Support\Security\GeoIp;
use App\Support\Security\IpBlocker;
use App\Support\Stats\VisitorStats;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Every single page view of the public site: when, which page, IP address, country, device and where it came from.
 */
class PageViewResource extends Resource
{
    protected static ?string $model = PageView::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'bezoek';

    protected static ?string $pluralModelLabel = 'bezoeken';

    protected static ?string $navigationLabel = 'Bezoeken';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Website;

    protected static ?int $navigationSort = 21;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::STATISTICS);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tijdstip')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('path')->label('Pagina')->searchable()->limit(40),
                TextColumn::make('ip')->label('IP-adres')->searchable()->copyable()->placeholder('niet bewaard')->fontFamily('mono')
                    ->description(fn (PageView $record): ?string => $record->ip && app(IpBlocker::class)->blockFor($record->ip) ? 'Geblokkeerd' : null),
                TextColumn::make('country_code')->label('Land')->formatStateUsing(fn (?string $state): string => GeoIp::label($state))->placeholder('Onbekend')->sortable(),
                TextColumn::make('source')->label('Herkomst')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'internal' ? 'Binnen de site' : (VisitorStats::SOURCES[$state] ?? $state))
                    ->description(fn (PageView $record): ?string => $record->source_name),
                TextColumn::make('device')->label('Toestel')->formatStateUsing(fn (?string $state): string => ['desktop' => 'Computer', 'mobile' => 'Smartphone', 'tablet' => 'Tablet'][$state] ?? 'Onbekend')
                    ->description(fn (PageView $record): ?string => $record->browser),
                TextColumn::make('user_agent')->label('Browser (volledig)')->limit(40)->tooltip(fn (PageView $record): ?string => $record->user_agent)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('country_code')->label('Land')->searchable()
                    ->options(fn (): array => PageView::query()->whereNotNull('country_code')->distinct()->pluck('country_code')->mapWithKeys(fn (string $code): array => [$code => GeoIp::label($code)])->sort()->all()),
                SelectFilter::make('source')->label('Herkomst')->options(['internal' => 'Binnen de site', ...VisitorStats::SOURCES]),
                SelectFilter::make('device')->label('Toestel')->options(['desktop' => 'Computer', 'mobile' => 'Smartphone', 'tablet' => 'Tablet']),
                Filter::make('ip')->schema([TextInput::make('ip')->label('IP-adres')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['ip'] ?? null, fn (Builder $query, string $ip) => $query->where('ip', $ip))),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label('Vanaf'),
                    DatePicker::make('until')->label('Tot en met'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('block')
                    ->label('Blokkeer IP')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (PageView $record): bool => filled($record->ip) && $record->ip !== request()->ip()
                        && (bool) auth()->user()?->hasPermission(Permissions::SECURITY)
                        && ! BlockedIp::query()->active()->where('ip', $record->ip)->exists())
                    ->modalHeading(fn (PageView $record): string => 'IP '.$record->ip.' blokkeren')
                    ->schema([
                        TextInput::make('reason')->label('Reden')->default('Geblokkeerd vanuit de bezoekerslijst'),
                        Select::make('minutes')->label('Hoe lang')->options(BlockedIpDurations::options())->default('0')->required(),
                    ])
                    ->action(function (PageView $record, array $data): void {
                        app(IpBlocker::class)->block($record->ip, $data['reason'] ?? null, (int) $data['minutes'] ?: null, auth()->user());
                        Notification::make()->success()->title('IP '.$record->ip.' geblokkeerd')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPageViews::route('/')];
    }
}
