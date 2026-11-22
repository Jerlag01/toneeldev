<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Site-wide settings stored as key/value rows (Systeem → Instellingen), with defaults for Het Vrolijk Toneel Initiatief.
 */
class Settings
{
    private const CACHE_KEY = 'toneel.settings';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $values = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            // General
            'site_name' => 'Het Vrolijk Toneel Initiatief',
            'tagline' => 'De geschiedenis, het heden, de toekomst en achter de schermen',
            'favicon' => null,
            'logo' => null,
            'accent_color' => '#e4572e',
            // The organisation (vzw)
            'vzw' => [
                'name' => 'Vrolijk Toneel Initiatief vzw',
                'short_name' => 'VTI',
                'company_number' => null,
                'rpr' => null,
                'street' => null,
                'postal_code' => null,
                'city' => 'Kontich',
                'country' => 'België',
                'email' => null,
                'phone' => null,
                'iban' => null,
                'bic' => null,
                'founded' => '1997',
            ],
            // Social media: list of {platform, url, label}
            'social_links' => [
                ['platform' => 'facebook', 'url' => 'https://www.facebook.com/Vrolijk-Toneel-Initiatief-1405183079697953/', 'label' => null],
            ],
            // Footer
            'footer' => [
                'about' => 'Sinds 1997 brengt Het Vrolijk Toneel Initiatief elk jaar een toneelstuk op de planken in Kontich, met acteurs, een technische ploeg en heel veel vrienden.',
                'show_vzw' => true,
                'show_social' => true,
                'copyright' => '© {jaar} {vzw}. Alle rechten voorbehouden.',
                'credits' => 'Verhaal: Walter Van Goethem',
                // "Website door ..." with a link (e.g. GitHub)
                'made_by_text' => 'Website door',
                'made_by_name' => 'Jeroen Lagaet',
                'made_by_url' => 'https://github.com/Jerlag01',
                // list of {title, links: list of {label, url, new_tab}}
                'columns' => [
                    ['title' => 'Het verhaal', 'links' => [
                        ['label' => 'Geschiedenis', 'url' => '/#geschiedenis', 'new_tab' => false],
                        ['label' => 'Heden', 'url' => '/#heden', 'new_tab' => false],
                        ['label' => 'Achter de schermen', 'url' => '/#achter-de-schermen', 'new_tab' => false],
                        ['label' => 'Het Goede Doel', 'url' => '/#goede-doel', 'new_tab' => false],
                    ]],
                ],
                // links in the bottom bar, list of {label, url, new_tab}
                'bottom_links' => [
                    ['label' => 'Privacy', 'url' => '/privacy', 'new_tab' => false],
                ],
            ],
            // Search engines and social sharing. Indexing is only ever allowed on the production site.
            'seo' => [
                'indexable' => true,
                'title_format' => '{pagina} | {site}',
                'description' => 'Het Vrolijk Toneel Initiatief (VTI) uit Kontich speelt sinds 1997 elk jaar toneel. Lees het verhaal: de geschiedenis, het heden, de toekomst en achter de schermen.',
                'keywords' => 'toneel, Kontich, toneelvereniging, VTI, Vrolijk Toneel Initiatief, Altenazaal',
                'share_image' => null,
                'google_verification' => null,
                'bing_verification' => null,
                'head_code' => null,
            ],
            // The bar: ordering from the tables (/bestel).
            'shop' => [
                'open' => true,
                'title' => 'Bestellen aan tafel',
                'intro' => 'Kies wat je wil drinken of eten. We brengen het naar je tafel.',
                'closed_title' => 'De bestellingen zijn gesloten',
                'closed_message' => 'Er kan op dit moment niet besteld worden. Tijdens de pauze en na de voorstelling ben je welkom aan de bar!',
                'currency_singular' => 'bon',
                'currency_plural' => 'bonnen',
                // Value of one bon in euro, for online payments.
                'bon_value' => 1.00,
                'bonnen_label' => 'Betalen met bonnen',
                'bonnen_instructions' => 'Hou je bonnen klaar: je geeft ze af wanneer je bestelling gebracht wordt.',
                'require_name' => true,
                'allow_note' => true,
                'max_quantity' => 20,
                // Open (not yet delivered) orders per IP address; 0 = no limit.
                'max_open_per_ip' => 5,
                'board_refresh' => 5,
                'stage_label' => 'Podium',
                'back_label' => 'Technische toren',
            ],
            // Online payments for the bar. Secrets are stored encrypted.
            'payments' => [
                'stripe_enabled' => false,
                'stripe_secret' => null,
                'stripe_webhook_secret' => null,
                'sumup_enabled' => false,
                'sumup_api_key' => null,
                'sumup_merchant_code' => null,
            ],
            // Design of the printed table QR cards (free layout: every element has a position in % of the card).
            'qr' => [
                'width_mm' => 100,
                'height_mm' => 148,
                'per_page' => 'auto',
                'background_color' => '#ffffff',
                'background_image' => null,
                'text_color' => '#1d1a18',
                'accent_color' => '#b4232a',
                'font' => 'Playfair Display',
                'qr_color' => '#1d1a18',
                'border' => true,
                'elements' => [
                    'logo' => ['visible' => true, 'x' => 50, 'y' => 12, 'size' => 22],
                    'title' => ['visible' => true, 'x' => 50, 'y' => 28, 'size' => 9, 'text' => 'Bestel aan je tafel'],
                    'qr' => ['visible' => true, 'x' => 50, 'y' => 55, 'size' => 55],
                    'table' => ['visible' => true, 'x' => 50, 'y' => 82, 'size' => 16, 'text' => 'Tafel {tafel}'],
                    'text' => ['visible' => true, 'x' => 50, 'y' => 93, 'size' => 4, 'text' => 'Scan de code, kies en betaal met bonnen'],
                ],
            ],
            // Login logging and automatic IP blocking.
            'security' => [
                'auto_block' => true,
                'max_attempts' => 5,
                'window_minutes' => 15,
                'block_minutes' => 60,
                'whitelist' => null,
                'retention_days' => 365,
            ],
            // Visitor statistics.
            'stats' => [
                'enabled' => true,
                'store_ip' => true,
                'exclude_logged_in' => true,
                'retention_days' => 730,
            ],
            // Empty mailer = the .env configuration stays in effect. The password is stored encrypted.
            'mail' => [
                'enabled' => true,
                'auto_welcome' => true,
                'mailer' => null,
                'host' => null,
                'port' => 587,
                'scheme' => null,
                'username' => null,
                'password' => null,
                'from_address' => null,
                'from_name' => null,
                'reply_to' => null,
            ],
        ];
    }

    /**
     * Groups stored as one row whose keys are merged with the defaults.
     *
     * @return list<string>
     */
    public static function groups(): array
    {
        return ['vzw', 'footer', 'seo', 'security', 'stats', 'mail', 'shop', 'payments', 'qr'];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values === null) {
            try {
                $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
            } catch (Throwable) {
                $stored = [];
            }

            $defaults = self::defaults();
            $this->values = array_replace($defaults, $stored);

            foreach (self::groups() as $key) {
                $this->values[$key] = array_replace($defaults[$key], (array) ($stored[$key] ?? []));
            }

            // Every QR element keeps its default keys when only some were saved.
            foreach ($defaults['qr']['elements'] as $name => $element) {
                $this->values['qr']['elements'][$name] = array_replace($element, (array) ($this->values['qr']['elements'][$name] ?? []));
            }
        }

        return $this->values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        self::flush();
        $this->values = null;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Keys of settings stored encrypted (never sent back to a form).
     *
     * @return list<string>
     */
    public static function secretKeys(): array
    {
        return ['mail.password', 'payments.stripe_secret', 'payments.stripe_webhook_secret', 'payments.sumup_api_key'];
    }

    public function secret(string $key): ?string
    {
        $value = $this->get($key);

        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Overrides the mail configuration with the values set in the admin site.
     * When no mailer is chosen there, the .env configuration stays in effect.
     */
    public function applyMailConfig(): void
    {
        $mail = (array) $this->get('mail');

        config(array_filter([
            'mail.from.address' => $mail['from_address'] ?: $this->get('vzw.email'),
            'mail.from.name' => $mail['from_name'] ?: $this->siteName(),
        ], fn (mixed $value): bool => filled($value)));

        if (blank($mail['mailer'] ?? null)) {
            return;
        }

        config(array_filter([
            'mail.default' => $mail['mailer'],
            'mail.mailers.smtp.host' => $mail['host'] ?? null,
            'mail.mailers.smtp.port' => $mail['port'] ?? null,
            'mail.mailers.smtp.username' => $mail['username'] ?? null,
            'mail.mailers.smtp.password' => $this->secret('mail.password'),
            'mail.mailers.smtp.scheme' => $mail['scheme'] ?? null,
        ], fn (mixed $value): bool => filled($value)));
    }

    public function siteName(): string
    {
        return (string) $this->get('site_name');
    }

    public function vzwName(): string
    {
        return (string) ($this->get('vzw.name') ?: $this->siteName());
    }

    /**
     * "Straat 1, 2550 Kontich" (empty parts left out).
     */
    public function address(): string
    {
        $vzw = (array) $this->get('vzw');
        $city = trim(($vzw['postal_code'] ?? '').' '.($vzw['city'] ?? ''));

        return implode(', ', array_filter([trim((string) ($vzw['street'] ?? '')), $city]));
    }

    /**
     * "1 bon", "3 bonnen", "2,5 bonnen".
     */
    public function bonnen(float|int|string|null $amount): string
    {
        $amount = (float) $amount;
        $number = fmod($amount, 1.0) === 0.0 ? number_format($amount, 0, ',', '.') : number_format($amount, 2, ',', '.');

        return $number.' '.($amount == 1 ? $this->get('shop.currency_singular', 'bon') : $this->get('shop.currency_plural', 'bonnen'));
    }

    public function faviconUrl(): string
    {
        $favicon = $this->get('favicon');

        return filled($favicon) ? Storage::disk('public')->url($favicon) : asset('images/favicon.png');
    }

    public function logoUrl(): ?string
    {
        $logo = $this->get('logo');

        return filled($logo) ? Storage::disk('public')->url($logo) : null;
    }

    /**
     * Fills {jaar}, {vzw} and {site} in short footer texts.
     */
    public function fillText(?string $text): string
    {
        return strtr((string) $text, ['{jaar}' => (string) now()->year, '{vzw}' => $this->vzwName(), '{site}' => $this->siteName()]);
    }
}
