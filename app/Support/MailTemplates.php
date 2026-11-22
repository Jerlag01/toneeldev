<?php

namespace App\Support;

use App\Mail\WelcomeMail;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Mail\Mailable;
use InvalidArgumentException;
use Throwable;

/**
 * The website's mails as editable templates (Instellingen → E-mailsjablonen).
 *
 * A template is Markdown with {placeholders}. Text placeholders are filled in (escaped) anywhere; block
 * placeholders such as {knop} stand on a line of their own and become a button or a table.
 * Admin-written text is never compiled as Blade.
 */
class MailTemplates
{
    /**
     * Unsaved texts shown by the preview in the admin site.
     *
     * @var array<string, array{subject: string, body: string}>
     */
    public static array $overrides = [];

    /**
     * @return array<string, array{label: string, description: string, subject: string, body: string, placeholders: array<string, string>, blocks: array<string, string>}>
     */
    public static function definitions(): array
    {
        $signature = "Met vriendelijke groeten,<br>\n{site}";
        $userPlaceholders = [
            'naam' => 'naam van de gebruiker', 'voornaam' => 'voornaam van de gebruiker', 'email' => 'e-mailadres (login)',
            'rollen' => 'rollen van de gebruiker', 'beheer_url' => 'adres van het beheer', 'geldig' => 'hoelang de link geldig is',
            ...self::commonPlaceholders(),
        ];

        return [
            'welcome' => [
                'label' => 'Welkomstmail nieuwe gebruiker',
                'description' => 'Bij het aanmaken van een gebruiker (of via "Welkomstmail opnieuw sturen"), met een link om een wachtwoord te kiezen.',
                'subject' => 'Welkom bij het beheer van {site}',
                'body' => "# Welkom, {voornaam}!\n\nEr is een account voor je aangemaakt in het beheer van de website van **{site}**. Je meldt aan met **{email}**.\n\nKies eerst je eigen wachtwoord via de knop hieronder. Deze link is {geldig} geldig. Verlopen? Gebruik dan \"Wachtwoord vergeten\" op {beheer_url}.\n\n{knop}\n\nJe rollen: {rollen}\n\n".$signature,
                'placeholders' => $userPlaceholders,
                'blocks' => ['knop' => 'knop "Wachtwoord kiezen"'],
            ],
            'password_link' => [
                'label' => 'Nieuwe wachtwoordlink (door een beheerder)',
                'description' => 'Wanneer een beheerder bij Gebruikers op "Wachtwoordlink sturen" klikt.',
                'subject' => 'Kies een nieuw wachtwoord voor {site}',
                'body' => "Hallo {voornaam},\n\nEen beheerder van {site} heeft je een link gestuurd om een nieuw wachtwoord te kiezen voor je account **{email}**. De link is {geldig} geldig.\n\n{knop}\n\nHeb je hier niet om gevraagd? Dan mag je deze mail negeren; je huidige wachtwoord blijft werken.\n\n".$signature,
                'placeholders' => $userPlaceholders,
                'blocks' => ['knop' => 'knop "Nieuw wachtwoord kiezen"'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $key): array
    {
        return self::definitions()[$key] ?? throw new InvalidArgumentException("Onbekend e-mailsjabloon [{$key}].");
    }

    public static function sync(): void
    {
        foreach (self::definitions() as $key => $definition) {
            MailTemplate::query()->firstOrCreate(['key' => $key], ['subject' => $definition['subject'], 'body' => $definition['body']]);
        }
    }

    /**
     * A mail of this template filled in for the current user, for the preview.
     */
    public static function sample(string $key): ?Mailable
    {
        self::definition($key);
        $user = auth()->user() ?? User::query()->first();

        return $user ? new WelcomeMail($user, url('/admin/password-reset/reset?voorbeeld'), reset: $key === 'password_link') : null;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public static function current(string $key): array
    {
        $definition = self::definition($key);

        if (isset(self::$overrides[$key])) {
            return self::$overrides[$key];
        }

        try {
            $template = MailTemplate::query()->find($key);
        } catch (Throwable) {
            $template = null;
        }

        return [
            'subject' => filled($template?->subject) ? $template->subject : $definition['subject'],
            'body' => filled($template?->body) ? $template->body : $definition['body'],
        ];
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    public static function subject(string $key, array $values): string
    {
        return trim((string) preg_replace('/\s+/', ' ', self::fill(self::current($key)['subject'], $values, escape: false)));
    }

    /**
     * The body split into parts for the mail.template view: Markdown text, tables and buttons.
     *
     * @param  array<string, scalar|null>  $values
     * @param  array<string, list<array<string, mixed>>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function parts(string $key, array $values, array $blocks = []): array
    {
        $body = str_replace("\r\n", "\n", self::current($key)['body']);
        $names = array_keys(self::definition($key)['blocks']);

        if ($names === []) {
            return [['type' => 'markdown', 'markdown' => self::fill($body, $values)]];
        }

        $pattern = '/^[ \t]*\{('.implode('|', array_map('preg_quote', $names)).')\}[ \t]*$/m';
        $pieces = preg_split($pattern, $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = [];

        foreach ($pieces as $index => $piece) {
            if ($index % 2 === 1) {
                array_push($parts, ...($blocks[$piece] ?? []));
            } elseif (trim($piece) !== '') {
                $parts[] = ['type' => 'markdown', 'markdown' => self::fill(trim($piece, "\n"), $values)];
            }
        }

        return $parts;
    }

    /**
     * @return array{type: string, url: string, label: string}
     */
    public static function button(string $url, string $label): array
    {
        return ['type' => 'button', 'url' => $url, 'label' => $label];
    }

    /**
     * @param  array<string, scalar|null>  $rows
     * @return array{type: string, rows: array<string, scalar|null>}
     */
    public static function table(array $rows): array
    {
        return ['type' => 'table', 'rows' => $rows];
    }

    /**
     * @return array<string, string>
     */
    public static function commonValues(): array
    {
        return [
            'site' => settings()->siteName(),
            'vzw' => settings()->vzwName(),
            'email_vzw' => (string) settings()->get('vzw.email'),
            'website' => url('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function commonPlaceholders(): array
    {
        return ['site' => 'naam van de website', 'vzw' => 'naam van de vzw', 'email_vzw' => 'e-mailadres van de vzw', 'website' => 'adres van de website'];
    }

    /**
     * @param  array<string, scalar|null>  $values
     */
    private static function fill(string $text, array $values, bool $escape = true): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/i', function (array $match) use ($values, $escape): string {
            if (! array_key_exists($match[1], $values)) {
                return $match[0];
            }

            $value = (string) $values[$match[1]];

            return $escape ? e($value) : $value;
        }, $text);
    }
}
