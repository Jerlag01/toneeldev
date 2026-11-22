<?php

namespace App\Support;

/**
 * Every permission a role can grant, grouped as shown in the role form. Admin roles have them all.
 */
class Permissions
{
    public const PAGES = 'pages';

    public const PAGES_PUBLISH = 'pages_publish';

    public const STATISTICS = 'statistics';

    public const PRODUCTIONS = 'productions';

    public const SHOP_ORDERS = 'shop_orders';

    public const SHOP_PRODUCTS = 'shop_products';

    public const SHOP_TABLES = 'shop_tables';

    public const SHOP_STATS = 'shop_stats';

    public const SHOP_SETTINGS = 'shop_settings';

    public const SITE_SETTINGS = 'site_settings';

    public const SEO = 'seo';

    public const FOOTER = 'footer';

    public const VZW = 'vzw';

    public const MAIL = 'mail';

    public const SECURITY = 'security';

    public const USERS = 'users';

    public const ROLES = 'roles';

    /**
     * @return array<string, array<string, string>> group label => [permission => label]
     */
    public static function grouped(): array
    {
        return [
            'Website' => [
                self::PAGES => 'Pagina\'s en het verhaal bewerken',
                self::PAGES_PUBLISH => 'Pagina\'s publiceren, verwijderen en in het menu zetten',
                self::PRODUCTIONS => 'Voorstellingen (producties) beheren',
                self::STATISTICS => 'Bezoekersstatistieken bekijken (met IP-adressen)',
            ],
            'Bar & bestellingen' => [
                self::SHOP_ORDERS => 'Bestellingen opvolgen en afsluiten, tafelplan',
                self::SHOP_PRODUCTS => 'Producten, categorieën, prijzen en stock (uitverkocht)',
                self::SHOP_TABLES => 'Tafels en QR-codes (ontwerp en afdrukken)',
                self::SHOP_STATS => 'Verkoopstatistieken',
                self::SHOP_SETTINGS => 'Bestellen open/dicht, betaalmethodes (Stripe, SumUp)',
            ],
            'Instellingen' => [
                self::SITE_SETTINGS => 'Algemeen: websitenaam, logo en favicon',
                self::SEO => 'Zoekmachines (SEO-tags)',
                self::FOOTER => 'Footer, footerlinks en sociale media',
                self::VZW => 'Gegevens van de vzw',
                self::MAIL => 'E-mailinstellingen en e-mailsjablonen',
            ],
            'Beheer' => [
                self::USERS => 'Gebruikers beheren en welkomstmails sturen',
                self::ROLES => 'Rollen en rechten beheren',
                self::SECURITY => 'Beveiliging: aanmeldpogingen en IP-blokkering',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::grouped())));
    }

    /**
     * @return array<string, array{description: string, is_admin: bool, permissions: list<string>}>
     */
    public static function defaultRoles(): array
    {
        return [
            'Beheerder' => ['description' => 'Alles, ook gebruikers, rollen en instellingen.', 'is_admin' => true, 'permissions' => []],
            'Redacteur' => ['description' => 'Pagina\'s en voorstellingen bewerken en publiceren, statistieken bekijken.', 'is_admin' => false, 'permissions' => [self::PAGES, self::PAGES_PUBLISH, self::PRODUCTIONS, self::STATISTICS, self::FOOTER]],
            'Bar' => ['description' => 'Bestellingen opvolgen en afsluiten, producten op uitverkocht zetten.', 'is_admin' => false, 'permissions' => [self::SHOP_ORDERS, self::SHOP_PRODUCTS]],
            'Barverantwoordelijke' => ['description' => 'Alles van de bar: producten, tafels, QR-codes, statistieken en betaalinstellingen.', 'is_admin' => false, 'permissions' => [self::SHOP_ORDERS, self::SHOP_PRODUCTS, self::SHOP_TABLES, self::SHOP_STATS, self::SHOP_SETTINGS]],
            'Schrijver' => ['description' => 'Enkel pagina\'s bewerken, niet publiceren.', 'is_admin' => false, 'permissions' => [self::PAGES]],
            'Bestuur' => ['description' => 'Statistieken, vzw-gegevens en instellingen, geen gebruikers.', 'is_admin' => false, 'permissions' => [self::STATISTICS, self::VZW, self::SITE_SETTINGS, self::SEO, self::FOOTER, self::MAIL]],
        ];
    }
}
