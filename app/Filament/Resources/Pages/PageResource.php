<?php

namespace App\Filament\Resources\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPage;
use App\Models\Page;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Website pages built from story blocks. The page "home" is the story on the front page.
 * Each block type has a view in resources/views/blocks.
 */
class PageResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PAGES;

    protected static ?string $model = Page::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'pagina';

    protected static ?string $pluralModelLabel = 'pagina\'s';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Website;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    private static function canPublish(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::PAGES_PUBLISH);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()
                ->columnSpan(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, string $operation): void {
                            if ($operation === 'create' && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    Builder::make('blocks')
                        ->label('Inhoud')
                        ->blocks(self::blocks())
                        ->collapsible()
                        ->collapsed(fn (string $operation): bool => $operation === 'edit')
                        ->cloneable()
                        ->blockNumbers(false)
                        ->blockPreviews(false)
                        ->addActionLabel('Blok toevoegen')
                        ->addBetweenActionLabel('Blok hier invoegen'),
                ]),
            Section::make()
                ->columnSpan(1)
                ->schema([
                    TextInput::make('slug')
                        ->label('URL')
                        ->prefix('/')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->notIn(['admin', 'livewire', 'storage', 'filament', 'api', 'up', 'robots', 'sitemap'])
                        ->disabled(fn (?Page $record): bool => (bool) $record?->isHome())
                        ->dehydrated(fn (?Page $record): bool => ! $record?->isHome())
                        ->helperText(fn (?Page $record): ?string => $record?->isHome() ? 'Dit is de startpagina (het verhaal).' : null),
                    Toggle::make('is_published')->label('Gepubliceerd')->default(fn (): bool => self::canPublish())
                        ->disabled(fn (?Page $record): bool => ! self::canPublish() || (bool) $record?->isHome())
                        ->helperText(fn (): ?string => self::canPublish() ? null : 'Enkel wie mag publiceren kan dit wijzigen.'),
                    Toggle::make('show_in_menu')->label('Tonen in het menu')->disabled(fn (): bool => ! self::canPublish()),
                    TextInput::make('menu_order')->label('Volgorde in het menu')->numeric()->default(0)->disabled(fn (): bool => ! self::canPublish()),
                ]),
            Section::make('Zoekmachines en delen')
                ->description('Leeg = de standaardwaarden uit Instellingen → Zoekmachines.')
                ->columnSpan(1)
                ->collapsible()
                ->schema([
                    TextInput::make('seo_title')->label('SEO-titel')->maxLength(70)->helperText('Titel in Google en het browsertabblad. Max. 60-70 tekens.'),
                    Textarea::make('meta_description')->label('Beschrijving voor Google')->rows(3)->maxLength(255),
                    FileUpload::make('share_image')->label('Deelafbeelding')->image()->disk('public')->directory('pages')->visibility('public')->helperText('1200 × 630 pixels.'),
                ]),
        ]);
    }

    /**
     * @return list<Block>
     */
    public static function blocks(array $except = []): array
    {
        return array_values(array_filter(self::allBlocks(), fn (Block $block): bool => ! in_array($block->getName(), $except, true)));
    }

    /**
     * @return list<Block>
     */
    private static function allBlocks(): array
    {
        return [
            Block::make('hero')
                ->label('Openingsbeeld (grote foto met titel)')
                ->icon(Heroicon::OutlinedPhoto)
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Titel')->required()->columnSpanFull(),
                    TextInput::make('subtitle')->label('Ondertitel')->columnSpanFull(),
                    Textarea::make('intro')->label('Inleiding (onder de foto)')->rows(3)->columnSpanFull(),
                    self::imageUpload('image', 'Achtergrondfoto')->required()->columnSpanFull(),
                    Select::make('height')->label('Hoogte')->options(['full' => 'Volledig scherm', 'large' => 'Groot', 'medium' => 'Middel'])->default('full'),
                    Select::make('overlay')->label('Verduistering van de foto')->options(['light' => 'Licht', 'medium' => 'Middel', 'strong' => 'Sterk'])->default('medium'),
                ]),
            Block::make('chapter')
                ->label('Hoofdstuk (grote titel, optioneel met foto)')
                ->icon(Heroicon::OutlinedBookmark)
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Titel')->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Get $get, Set $set) => blank($get('anchor')) ? $set('anchor', Str::slug((string) $state)) : null),
                    TextInput::make('anchor')->label('Anker (voor links naar #...)')->alphaDash()->helperText('Bv. "geschiedenis" voor /#geschiedenis.'),
                    TextInput::make('subtitle')->label('Ondertitel')->columnSpanFull(),
                    self::imageUpload('image', 'Foto achter de titel (optioneel)')->columnSpanFull(),
                    TextInput::make('nav_title')->label('Korte titel in het menu (optioneel)')->maxLength(40),
                    Toggle::make('in_nav')->label('Opnemen in het menu (inhoudsnavigatie)')->default(true)->inline(false),
                ]),
            Block::make('text')
                ->label('Tekst')
                ->icon(Heroicon::OutlinedBars3BottomLeft)
                ->schema([
                    TextInput::make('heading')->label('Tussentitel'),
                    RichEditor::make('content')
                        ->label('Tekst')
                        ->toolbarButtons([['bold', 'italic', 'underline', 'link'], ['h3', 'blockquote', 'bulletList', 'orderedList'], ['attachFiles'], ['undo', 'redo']])
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages')
                        ->fileAttachmentsVisibility('public'),
                    Toggle::make('lead')->label('Grotere inleidende tekst'),
                ]),
            Block::make('markdown')
                ->label('Tekst in Markdown')
                ->icon(Heroicon::OutlinedCodeBracket)
                ->schema([
                    MarkdownEditor::make('content')
                        ->label('Tekst (Markdown)')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages')
                        ->helperText('# Titel, **vet**, *schuin*, [link](https://...), ![foto](...) via de afbeeldingsknop. Een YouTube-link of link naar een videobestand alleen op een regel wordt een speler.'),
                ]),
            Block::make('timeline')
                ->label('Tijdlijn (jaartallen)')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    TextInput::make('heading')->label('Tussentitel'),
                    Repeater::make('items')
                        ->label('Items')
                        ->schema([
                            TextInput::make('year')->label('Jaar')->required()->maxLength(20),
                            TextInput::make('title')->label('Titel')->required(),
                            TextInput::make('url')->label('Link (optioneel)')->url(),
                            TextInput::make('note')->label('Extra info (optioneel)'),
                        ])
                        ->columns(4)
                        ->reorderable()
                        ->itemLabel(fn (array $state): ?string => trim(($state['year'] ?? '').': '.($state['title'] ?? ''), ': ') ?: null)
                        ->addActionLabel('Jaar toevoegen'),
                ]),
            Block::make('gallery')
                ->label('Fotogalerij')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->columns(2)
                ->schema([
                    FileUpload::make('images')->label('Foto\'s')->image()->multiple()->reorderable()->appendFiles()->panelLayout('grid')
                        ->disk('public')->directory('pages')->visibility('public')->maxSize(10240)->required()->columnSpanFull(),
                    TextInput::make('caption')->label('Onderschrift')->columnSpanFull(),
                    Select::make('layout')->label('Weergave')->options(['grid' => 'Raster', 'masonry' => 'Metselwerk (behoudt verhoudingen)'])->default('masonry'),
                    Select::make('columns')->label('Kolommen')->options(['2' => '2', '3' => '3', '4' => '4'])->default('3'),
                ]),
            Block::make('image')
                ->label('Foto')
                ->icon(Heroicon::OutlinedPhoto)
                ->columns(2)
                ->schema([
                    self::imageUpload('image', 'Foto')->required()->columnSpanFull(),
                    TextInput::make('caption')->label('Onderschrift'),
                    Select::make('width')->label('Breedte')->options(['text' => 'Zo breed als de tekst', 'wide' => 'Breed', 'full' => 'Volledige breedte'])->default('wide'),
                ]),
            Block::make('video')
                ->label('Video (YouTube of eigen bestand)')
                ->icon(Heroicon::OutlinedPlayCircle)
                ->schema([
                    Select::make('source')->label('Bron')->options(['youtube' => 'YouTube-link', 'upload' => 'Eigen videobestand (mp4, webm, mov)'])->default('youtube')->live(),
                    TextInput::make('url')->label('YouTube-link')->placeholder('https://www.youtube.com/watch?v=...')
                        ->visible(fn (Get $get): bool => ($get('source') ?? 'youtube') === 'youtube')
                        ->required(fn (Get $get): bool => ($get('source') ?? 'youtube') === 'youtube')
                        ->rule(fn () => fn (string $attribute, mixed $value, \Closure $fail) => blank($value) || youtube_id((string) $value) ? null : $fail('Geen geldige YouTube-link.')),
                    FileUpload::make('file')->label('Videobestand')->helperText('Max. 100 MB. Langere video\'s zet je beter op YouTube.')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])->maxSize(102400)
                        ->disk('public')->directory('videos')->visibility('public')
                        ->visible(fn (Get $get): bool => $get('source') === 'upload')
                        ->required(fn (Get $get): bool => $get('source') === 'upload'),
                    FileUpload::make('poster')->label('Voorbeeldafbeelding (optioneel)')->image()->disk('public')->directory('videos')->visibility('public')
                        ->visible(fn (Get $get): bool => $get('source') === 'upload'),
                    TextInput::make('caption')->label('Onderschrift'),
                ]),
            Block::make('quote')
                ->label('Citaat')
                ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                ->schema([
                    Textarea::make('text')->label('Citaat')->required()->rows(2),
                    TextInput::make('author')->label('Wie'),
                ]),
            Block::make('list')
                ->label('Opsomming')
                ->icon(Heroicon::OutlinedListBullet)
                ->schema([
                    TextInput::make('heading')->label('Tussentitel'),
                    Textarea::make('intro')->label('Inleiding')->rows(2),
                    Repeater::make('items')->label('Items')->simple(TextInput::make('text')->required())->reorderable()->addActionLabel('Item toevoegen'),
                    Select::make('style')->label('Stijl')->options(['bullets' => 'Bolletjes', 'numbered' => 'Genummerd', 'columns' => 'In kolommen'])->default('bullets'),
                ]),
            Block::make('button')
                ->label('Knop')
                ->icon(Heroicon::OutlinedCursorArrowRays)
                ->columns(3)
                ->schema([
                    TextInput::make('label')->label('Tekst')->required(),
                    TextInput::make('url')->label('Link')->required()->placeholder('/#geschiedenis of https://...'),
                    Toggle::make('new_tab')->label('In nieuw venster')->inline(false),
                ]),
            Block::make('productions')
                ->label('Voorstellingen (overzicht)')
                ->icon(Heroicon::OutlinedTicket)
                ->columns(3)
                ->schema([
                    TextInput::make('heading')->label('Titel')->columnSpanFull(),
                    Select::make('mode')->label('Welke')->options(['upcoming' => 'Komende', 'past' => 'Voorbije', 'all' => 'Alle'])->default('upcoming')->required(),
                    TextInput::make('limit')->label('Maximum aantal')->numeric()->minValue(1)->placeholder('alle'),
                    Toggle::make('show_more')->label('Knop "Alle voorstellingen"')->default(true)->inline(false),
                    TextInput::make('empty_text')->label('Tekst als er geen zijn')->placeholder('Binnenkort meer nieuws!')->columnSpanFull(),
                ]),
            Block::make('divider')
                ->label('Scheidingslijn')
                ->icon(Heroicon::OutlinedMinus)
                ->schema([
                    Select::make('style')->label('Stijl')->options(['line' => 'Lijn', 'stars' => 'Drie sterretjes', 'space' => 'Enkel witruimte'])->default('stars'),
                ]),
        ];
    }

    private static function imageUpload(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->imageEditor()
            ->disk('public')
            ->directory('pages')
            ->visibility('public')
            ->maxSize(10240);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('menu_order')
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->sortable()
                    ->description(fn (Page $record): string => $record->isHome() ? '/ (startpagina)' : '/'.$record->slug),
                TextColumn::make('blocks')->label('Blokken')->state(fn (Page $record): int => count($record->blocks ?? []))->badge()->color('gray'),
                IconColumn::make('is_published')->label('Gepubliceerd')->boolean(),
                IconColumn::make('show_in_menu')->label('In menu')->boolean(),
                TextColumn::make('menu_order')->label('Volgorde')->sortable(),
                TextColumn::make('updated_at')->label('Gewijzigd')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('view')->label('Bekijken')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Page $record): string => $record->url(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canPublish() && ! $record->isHome();
    }

    public static function canDeleteAny(): bool
    {
        return self::canPublish();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPage::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
