<?php

namespace App\Filament\Resources\Products;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * The menu of the bar with prices (in bonnen) and stock. "Uitverkocht" takes a product off the menu at once.
 */
class ProductResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SHOP_PRODUCTS;

    protected static ?string $model = Product::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'product';

    protected static ?string $pluralModelLabel = 'producten';

    protected static ?string $navigationLabel = 'Producten & stock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $soldOut = Product::query()->where('is_active', true)->where(fn ($q) => $q->where('sold_out', true)->orWhere(fn ($q) => $q->where('track_stock', true)->where('stock', '<=', 0)))->count();

        return $soldOut > 0 ? (string) $soldOut : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Uitverkochte producten';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(100),
            Select::make('shop_category_id')->label('Categorie')->relationship('category', 'name')->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->label('Naam')->required()]),
            Textarea::make('description')->label('Omschrijving')->rows(2)->maxLength(500)->columnSpanFull(),
            TextInput::make('price')->label('Prijs')->numeric()->minValue(0)->step(0.5)->required()->suffix(fn (): string => (string) settings()->get('shop.currency_plural', 'bonnen'))
                ->helperText(fn (): string => 'Online betalen: 1 '.settings()->get('shop.currency_singular', 'bon').' = € '.number_format((float) settings()->get('shop.bon_value', 1), 2, ',', '.')),
            FileUpload::make('image')->label('Foto (optioneel)')->image()->imageEditor()->disk('public')->directory('products')->visibility('public')->maxSize(5120),
            Toggle::make('is_active')->label('Op de kaart')->default(true),
            Toggle::make('sold_out')->label('Uitverkocht')->helperText('Blijft zichtbaar als "uitverkocht" maar kan niet besteld worden.'),
            Toggle::make('track_stock')->label('Stock bijhouden')->live()->helperText('Bij 0 wordt het product automatisch uitverkocht.'),
            TextInput::make('stock')->label('Stock')->numeric()->integer()->minValue(0)->visible(fn (Get $get): bool => (bool) $get('track_stock'))->required(fn (Get $get): bool => (bool) $get('track_stock')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->defaultGroup(Group::make('category.name')->label('Categorie')->collapsible())
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->imageSize(40),
                TextColumn::make('name')->label('Naam')->searchable()->description(fn (Product $record): ?string => $record->description),
                TextColumn::make('price')->label('Prijs')->formatStateUsing(fn ($state): string => settings()->bonnen($state))->sortable(),
                ToggleColumn::make('is_active')->label('Op de kaart'),
                ToggleColumn::make('sold_out')->label('Uitverkocht')->onColor('danger'),
                TextInputColumn::make('stock')->label('Stock')->type('number')->rules(['nullable', 'integer', 'min:0'])
                    ->disabled(fn (Product $record): bool => ! $record->track_stock)
                    ->placeholder(fn (Product $record): string => $record->track_stock ? '0' : 'niet bijgehouden'),
                TextColumn::make('sold_today')->label('Vandaag verkocht')
                    ->state(fn (Product $record): int => (int) $record->orderItems()->whereHas('order', fn ($q) => $q->sold()->whereDate('created_at', today()))->sum('quantity')),
            ])
            ->filters([
                SelectFilter::make('shop_category_id')->label('Categorie')->relationship('category', 'name'),
                TernaryFilter::make('sold_out')->label('Uitverkocht'),
                TernaryFilter::make('is_active')->label('Op de kaart'),
            ])
            ->headerActions([
                Action::make('allAvailable')->label('Alles weer beschikbaar')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->requiresConfirmation()->modalDescription('Zet "uitverkocht" bij alle producten uit (stock blijft zoals hij is).')
                    ->action(function (): void {
                        $count = Product::query()->where('sold_out', true)->update(['sold_out' => false]);
                        Notification::make()->success()->title($count.' product(en) weer beschikbaar')->send();
                    }),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('soldOut')->label('Uitverkocht zetten')->icon(Heroicon::OutlinedNoSymbol)->action(fn (Collection $records) => $records->each->update(['sold_out' => true]))->deselectRecordsAfterCompletion(),
                BulkAction::make('available')->label('Beschikbaar zetten')->icon(Heroicon::OutlinedCheck)->action(fn (Collection $records) => $records->each->update(['sold_out' => false]))->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
