<?php

namespace App\Filament\Resources\Users;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Role;
use App\Models\User;
use App\Support\Mailing;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

/**
 * People who can sign in to the admin site. A new user gets a welcome mail with a link to choose a password.
 * Only administrators can hand out (or take away) an administrator role, or change another administrator.
 */
class UserResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::USERS;

    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'gebruiker';

    protected static ?string $pluralModelLabel = 'gebruikers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Users;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail (login)')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('phone')->label('Telefoon')->tel()->maxLength(50),
            Toggle::make('send_welcome')
                ->label('Welkomstmail sturen met een link om zelf een wachtwoord te kiezen')
                ->default(fn (): bool => Mailing::allows('welcome'))
                ->live()
                ->visible(fn (string $operation): bool => $operation === 'create')
                ->columnSpanFull(),
            TextInput::make('password')
                ->label('Wachtwoord')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation, Get $get): bool => $operation === 'create' && ! $get('send_welcome'))
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation, Get $get): ?string => match (true) {
                    $operation === 'edit' => 'Leeg laten om het wachtwoord niet te wijzigen.',
                    (bool) $get('send_welcome') => 'Mag leeg blijven: de gebruiker kiest zelf een wachtwoord via de welkomstmail.',
                    default => null,
                }),
            Toggle::make('is_active')
                ->label('Actief (mag aanmelden)')
                ->default(true)
                ->inline(false)
                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
            CheckboxList::make('roles')
                ->label('Rollen')
                ->relationship('roles', 'name', fn (Builder $query) => auth()->user()?->isAdmin() ? $query : $query->where('is_admin', false))
                ->descriptions(fn (): array => Role::query()->pluck('description', 'id')->filter()->all())
                ->helperText(fn (): ?string => auth()->user()?->isAdmin() ? null : 'Enkel een beheerder kan de beheerdersrol toekennen.')
                ->columns(2)
                ->columnSpanFull()
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('roles'))
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable()->description(fn (User $record): ?string => $record->phone),
                TextColumn::make('email')->label('E-mail')->searchable()->copyable(),
                TextColumn::make('roles.name')->label('Rollen')->badge(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
                TextColumn::make('last_login_at')->label('Laatst aangemeld')->since()->sortable()->placeholder('Nooit')
                    ->description(fn (User $record): ?string => $record->last_login_ip),
                TextColumn::make('welcome_sent_at')->label('Welkomstmail')->date('d/m/Y')->placeholder('Niet verstuurd')->toggleable(),
                TextColumn::make('created_at')->label('Sinds')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Actief'),
                SelectFilter::make('roles')->label('Rol')->relationship('roles', 'name'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (User $record): bool => static::canEdit($record)),
                ActionGroup::make([
                    self::mailAction('welcome', 'Welkomstmail opnieuw sturen', Heroicon::OutlinedEnvelope, reset: false),
                    self::mailAction('password', 'Wachtwoordlink sturen', Heroicon::OutlinedKey, reset: true),
                    DeleteAction::make(),
                ]),
            ]);
    }

    private static function mailAction(string $name, string $label, Heroicon $icon, bool $reset): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->requiresConfirmation()
            ->modalDescription(fn (User $record): string => 'Er gaat een mail naar '.$record->email.' met een link om '.($reset ? 'een nieuw' : 'een').' wachtwoord te kiezen.')
            ->visible(fn (User $record): bool => static::canEdit($record) && $record->is_active)
            ->action(function (User $record) use ($reset): void {
                try {
                    $record->sendWelcomeMail(reset: $reset);
                    Notification::make()->success()->title('Mail verstuurd naar '.$record->email)->send();
                } catch (Throwable $exception) {
                    Notification::make()->danger()->persistent()->title('Mail niet verstuurd')->body($exception->getMessage())->send();
                }
            });
    }

    /**
     * Non-administrators cannot touch administrators.
     */
    public static function canEdit(Model $record): bool
    {
        return static::userCan() && (auth()->user()?->isAdmin() || ! $record->isAdmin());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record) && ! $record->is(auth()->user());
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
