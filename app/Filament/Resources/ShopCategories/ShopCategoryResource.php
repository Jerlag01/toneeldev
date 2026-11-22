<?php

namespace App\Filament\Resources\ShopCategories;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ShopCategories\Pages\ManageShopCategories;
use App\Models\ShopCategory;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShopCategoryResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SHOP_PRODUCTS;

    protected static ?string $model = ShopCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'categorie';

    protected static ?string $pluralModelLabel = 'categorieën';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(100),
            Toggle::make('is_active')->label('Zichtbaar op de kaart')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable(),
                TextColumn::make('products_count')->label('Producten')->counts('products'),
                ToggleColumn::make('is_active')->label('Zichtbaar'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageShopCategories::route('/')];
    }
}
