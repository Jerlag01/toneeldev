<?php

namespace App\Enums;

use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Bar = 'bar';
    case Website = 'website';
    case Settings = 'settings';
    case Users = 'users';
    case Security = 'security';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bar => 'Bar & bestellingen',
            self::Website => 'Website',
            self::Settings => 'Instellingen',
            self::Users => 'Gebruikers',
            self::Security => 'Beveiliging',
        };
    }

    /**
     * @return list<FilamentNavigationGroup>
     */
    public static function navigationGroups(): array
    {
        return array_map(
            fn (self $case): FilamentNavigationGroup => FilamentNavigationGroup::make(fn (): string => $case->getLabel()),
            self::cases(),
        );
    }
}
