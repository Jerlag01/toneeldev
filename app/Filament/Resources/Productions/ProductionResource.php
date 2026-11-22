<?php

namespace App\Filament\Resources\Productions;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Productions\Pages\CreateProduction;
use App\Filament\Resources\Productions\Pages\EditProduction;
use App\Filament\Resources\Productions\Pages\ListProductions;
use App\Models\Production;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Plays: overview data (date, topic, title, picture) plus a page of text, photos and videos built from blocks.
 */
class ProductionResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PRODUCTIONS;

    protected static ?string $model = Production::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'voorstelling';

    protected static ?string $pluralModelLabel = 'voorstellingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Website;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Overzicht')
                ->description('Wat getoond wordt in de lijst met voorstellingen.')
                ->columnSpan(2)
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Titel')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Get $get, Set $set, string $operation) => $operation === 'create' && blank($get('slug')) ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('topic')->label('Onderwerp / genre')->maxLength(255)->placeholder('bv. Een komedie over een verbouwing die uit de hand loopt'),
                    Textarea::make('summary')->label('Korte inhoud')->rows(3)->maxLength(1000)->columnSpanFull(),
                    FileUpload::make('cover_image')->label('Hoofdfoto (affiche)')->image()->imageEditor()->disk('public')->directory('productions')->visibility('public')->maxSize(10240)->columnSpanFull(),
                ]),
            Section::make()
                ->columnSpan(1)
                ->schema([
                    TextInput::make('slug')->label('URL')->prefix('/voorstellingen/')->required()->alphaDash()->unique(ignoreRecord: true),
                    Toggle::make('is_published')->label('Gepubliceerd')->default(true),
                    TextInput::make('venue')->label('Zaal / locatie')->placeholder('Altenazaal, Kontich')->maxLength(255),
                    TextInput::make('ticket_url')->label('Link naar tickets')->url()->maxLength(255),
                    TextInput::make('charity')->label('Goede doel')->maxLength(255),
                    Textarea::make('meta_description')->label('Beschrijving voor Google')->rows(2)->maxLength(255),
                ]),
            Section::make('Data')
                ->description('Vul de voorstellingen in; "komend" of "voorbij" volgt daaruit. Geen exacte data? Gebruik dan een begindatum en een vrije datumtekst (bv. "2019").')
                ->columnSpan(2)
                ->columns(3)
                ->schema([
                    Repeater::make('performances')
                        ->label('Voorstellingen')
                        ->schema([
                            DatePicker::make('date')->label('Datum')->required()->native(false)->displayFormat('d/m/Y'),
                            TimePicker::make('time')->label('Uur')->seconds(false),
                            TextInput::make('venue')->label('Andere zaal (optioneel)'),
                            TextInput::make('note')->label('Opmerking')->placeholder('bv. Goede-doelvoorstelling'),
                        ])
                        ->columns(4)
                        ->reorderable()
                        ->defaultItems(0)
                        ->addActionLabel('Voorstelling toevoegen')
                        ->columnSpanFull(),
                    DatePicker::make('starts_on')->label('Begindatum (zonder voorstellingen)')->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('ends_on')->label('Einddatum')->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('date_label')->label('Vrije datumtekst')->placeholder('bv. 2019 of voorjaar 2027')->maxLength(60),
                ]),
            Section::make('Pagina van de voorstelling')
                ->description('Tekst (gewone editor of Markdown), foto\'s en video\'s (YouTube of eigen bestand). In tekst kan je ook afbeeldingen invoegen en een YouTube-link op een eigen regel wordt een speler.')
                ->columnSpanFull()
                ->schema([
                    Builder::make('blocks')
                        ->hiddenLabel()
                        ->blocks(PageResource::blocks(except: ['hero', 'chapter', 'productions']))
                        ->collapsible()
                        ->cloneable()
                        ->blockNumbers(false)
                        ->addActionLabel('Blok toevoegen'),
                    FileUpload::make('gallery')->label('Fotogalerij (onderaan)')->image()->multiple()->reorderable()->appendFiles()->panelLayout('grid')
                        ->disk('public')->directory('productions')->visibility('public')->maxSize(10240),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                ImageColumn::make('cover_image')->label('')->disk('public')->square()->imageSize(48),
                TextColumn::make('title')->label('Titel')->searchable()->sortable()->description(fn (Production $record): ?string => $record->topic),
                TextColumn::make('dates')->label('Wanneer')->state(fn (Production $record): string => $record->dateLabel())->sortable(query: fn (EloquentBuilder $query, string $direction) => $query->orderBy('starts_on', $direction)),
                TextColumn::make('status')->label('')->badge()
                    ->state(fn (Production $record): string => $record->isUpcoming() ? 'Komend' : 'Voorbij')
                    ->color(fn (string $state): string => $state === 'Komend' ? 'success' : 'gray'),
                IconColumn::make('is_published')->label('Gepubliceerd')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('upcoming')->label('Wanneer')->trueLabel('Komend')->falseLabel('Voorbij')
                    ->queries(true: fn (EloquentBuilder $q) => $q->whereDate('ends_on', '>=', today()), false: fn (EloquentBuilder $q) => $q->where(fn ($q) => $q->whereDate('ends_on', '<', today())->orWhereNull('ends_on'))),
            ])
            ->recordActions([
                Action::make('view')->label('Bekijken')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')->url(fn (Production $record): string => $record->url(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductions::route('/'),
            'create' => CreateProduction::route('/create'),
            'edit' => EditProduction::route('/{record}/edit'),
        ];
    }
}
