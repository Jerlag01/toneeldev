<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Support\Mailing;
use App\Support\Permissions;
use App\Support\Seo;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * All site settings on one page. Each tab needs its own permission; a user only sees (and saves) the tabs they may change.
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Instellingen';

    protected static ?string $title = 'Instellingen';

    /**
     * Setting keys saved by each tab, with the permission it needs.
     *
     * @return array<string, array{permission: string, keys: list<string>}>
     */
    public static function tabKeys(): array
    {
        return [
            'general' => ['permission' => Permissions::SITE_SETTINGS, 'keys' => ['site_name', 'tagline', 'favicon', 'logo', 'accent_color']],
            'vzw' => ['permission' => Permissions::VZW, 'keys' => ['vzw']],
            'seo' => ['permission' => Permissions::SEO, 'keys' => ['seo']],
            'footer' => ['permission' => Permissions::FOOTER, 'keys' => ['footer', 'social_links']],
            'mail' => ['permission' => Permissions::MAIL, 'keys' => ['mail']],
            'security' => ['permission' => Permissions::SECURITY, 'keys' => ['security', 'stats']],
            'shop' => ['permission' => Permissions::SHOP_SETTINGS, 'keys' => ['shop', 'payments']],
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasAnyPermission(...array_column(self::tabKeys(), 'permission'));
    }

    private static function can(string $tab): bool
    {
        return (bool) auth()->user()?->hasPermission(self::tabKeys()[$tab]['permission']);
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(SiteSettings $settings): void
    {
        $values = $settings->all();

        // Stored secrets are never sent back to the browser.
        foreach (SiteSettings::secretKeys() as $secret) {
            Arr::set($values, $secret, null);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make('Algemeen')->id('algemeen')->icon(Heroicon::OutlinedHome)->visible(fn (): bool => self::can('general'))->schema([
                        Section::make('Website')
                            ->columns(2)
                            ->schema([
                                TextInput::make('site_name')->label('Naam van de website')->required()->maxLength(120),
                                TextInput::make('tagline')->label('Ondertitel / slogan')->maxLength(200),
                                FileUpload::make('favicon')->label('Favicon')
                                    ->helperText('Vierkant PNG of ICO, minstens 64 × 64 pixels (liefst 512 × 512). Leeg = standaard-icoon.')
                                    ->image()->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'])
                                    ->disk('public')->directory('settings')->visibility('public'),
                                FileUpload::make('logo')->label('Logo')
                                    ->helperText('Getoond in de menubalk en de footer. Leeg = enkel de naam.')
                                    ->image()->disk('public')->directory('settings')->visibility('public'),
                                ColorPicker::make('accent_color')->label('Accentkleur van de website')->helperText('Voor links, knoppen en titels.'),
                            ]),
                    ]),
                    Tab::make('Vzw')->id('vzw')->icon(Heroicon::OutlinedBuildingLibrary)->visible(fn (): bool => self::can('vzw'))->schema([
                        Section::make('Gegevens van de vzw')
                            ->description('Worden getoond in de footer (als dat aanstaat) en gebruikt voor Google (gestructureerde gegevens) en in mails.')
                            ->columns(2)
                            ->schema([
                                TextInput::make('vzw.name')->label('Officiële naam')->required()->maxLength(200),
                                TextInput::make('vzw.short_name')->label('Afkorting')->maxLength(30),
                                TextInput::make('vzw.company_number')->label('Ondernemingsnummer')->placeholder('0123.456.789')->maxLength(20),
                                TextInput::make('vzw.rpr')->label('RPR (rechtspersonenregister)')->placeholder('Ondernemingsrechtbank Antwerpen, afdeling Antwerpen')->maxLength(200),
                                TextInput::make('vzw.street')->label('Straat en nummer (maatschappelijke zetel)')->maxLength(200),
                                Grid::make(2)->schema([
                                    TextInput::make('vzw.postal_code')->label('Postcode')->maxLength(10),
                                    TextInput::make('vzw.city')->label('Gemeente')->maxLength(100),
                                ]),
                                TextInput::make('vzw.country')->label('Land')->maxLength(100),
                                TextInput::make('vzw.founded')->label('Opgericht in')->maxLength(10),
                                TextInput::make('vzw.email')->label('E-mailadres')->email()->maxLength(200),
                                TextInput::make('vzw.phone')->label('Telefoon')->tel()->maxLength(50),
                                TextInput::make('vzw.iban')->label('IBAN')->maxLength(40),
                                TextInput::make('vzw.bic')->label('BIC')->maxLength(20),
                            ]),
                    ]),
                    Tab::make('Zoekmachines')->id('seo')->icon(Heroicon::OutlinedMagnifyingGlass)->visible(fn (): bool => self::can('seo'))->schema([
                        Section::make('SEO')
                            ->description(fn (): string => Seo::indexable()
                                ? 'Deze site mag geïndexeerd worden. Sitemap voor Google Search Console: '.route('sitemap')
                                : 'Deze site wordt NIET geïndexeerd'.(app()->isProduction() ? ' (uitgeschakeld hieronder).' : ' (dit is geen productieomgeving: APP_ENV='.app()->environment().').'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('seo.indexable')->label('Zoekmachines mogen de site opnemen')->helperText('Werkt enkel op de productiesite; een testsite wordt nooit geïndexeerd.')->columnSpanFull(),
                                TextInput::make('seo.title_format')->label('Opmaak van de paginatitel')->helperText('{pagina} = titel van de pagina, {site} = naam van de website. De startpagina toont "naam - slogan".')->required()->maxLength(100),
                                TextInput::make('seo.keywords')->label('Trefwoorden (meta keywords)')->helperText('Gescheiden door komma\'s. Google gebruikt ze niet meer, andere zoekmachines soms wel.')->maxLength(255),
                                Textarea::make('seo.description')->label('Standaardbeschrijving (meta description)')->helperText('Voor pagina\'s zonder eigen beschrijving. Ideaal 120-160 tekens.')->rows(3)->maxLength(300)->columnSpanFull(),
                                FileUpload::make('seo.share_image')->label('Deelafbeelding (Open Graph)')->helperText('Getoond bij delen op Facebook, WhatsApp... Liefst 1200 × 630 pixels. Leeg = de groepsfoto van het verhaal.')->image()->disk('public')->directory('settings')->visibility('public')->columnSpanFull(),
                                TextInput::make('seo.google_verification')->label('Google Search Console-code')->helperText('Enkel de code uit content="..." van de HTML-tag.'),
                                TextInput::make('seo.bing_verification')->label('Bing Webmaster-code')->helperText('Enkel de code uit content="...".'),
                            ]),
                    ]),
                    Tab::make('Footer & sociale media')->id('footer')->icon(Heroicon::OutlinedQueueList)->visible(fn (): bool => self::can('footer'))->schema([
                        Section::make('Sociale media')
                            ->description('Worden getoond als iconen in de footer (en in het menu op de startpagina).')
                            ->schema([
                                Repeater::make('social_links')
                                    ->hiddenLabel()
                                    ->schema([
                                        Select::make('platform')->label('Platform')->options(self::platforms())->required(),
                                        TextInput::make('url')->label('Link')->url()->required()->maxLength(500),
                                        TextInput::make('label')->label('Eigen tekst (optioneel)')->maxLength(60),
                                    ])
                                    ->columns(3)
                                    ->reorderable()
                                    ->defaultItems(0)
                                    ->addActionLabel('Sociale media toevoegen'),
                            ]),
                        Section::make('Footer')
                            ->columns(2)
                            ->schema([
                                Textarea::make('footer.about')->label('Korte tekst over de vereniging')->rows(3)->maxLength(600)->columnSpanFull(),
                                Toggle::make('footer.show_vzw')->label('Vzw-gegevens tonen (naam, adres, ondernemingsnummer, e-mail)'),
                                Toggle::make('footer.show_social')->label('Sociale media tonen'),
                                TextInput::make('footer.copyright')->label('Copyrightregel')->helperText('{jaar}, {vzw} en {site} worden ingevuld.')->maxLength(200),
                                TextInput::make('footer.credits')->label('Extra regel (credits)')->maxLength(200),
                                Fieldset::make('Wie maakte de website')->columns(3)->columnSpanFull()->schema([
                                    TextInput::make('footer.made_by_text')->label('Tekst')->placeholder('Website door')->maxLength(60),
                                    TextInput::make('footer.made_by_name')->label('Naam')->maxLength(100)->helperText('Leeg = niet tonen.'),
                                    TextInput::make('footer.made_by_url')->label('Link (bv. GitHub)')->url()->maxLength(255),
                                ]),
                                Repeater::make('footer.columns')
                                    ->label('Kolommen met links')
                                    ->schema([
                                        TextInput::make('title')->label('Titel van de kolom')->required()->maxLength(60),
                                        Repeater::make('links')
                                            ->label('Links')
                                            ->schema(self::linkFields())
                                            ->columns(3)
                                            ->reorderable()
                                            ->defaultItems(1)
                                            ->addActionLabel('Link toevoegen'),
                                    ])
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                                    ->maxItems(4)
                                    ->reorderable()
                                    ->addActionLabel('Kolom toevoegen')
                                    ->columnSpanFull(),
                                Repeater::make('footer.bottom_links')
                                    ->label('Links in de onderste balk (privacy, voorwaarden...)')
                                    ->schema(self::linkFields())
                                    ->columns(3)
                                    ->reorderable()
                                    ->defaultItems(0)
                                    ->addActionLabel('Link toevoegen')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                    Tab::make('E-mail')->id('email')->icon(Heroicon::OutlinedEnvelope)->visible(fn (): bool => self::can('mail'))->schema([
                        $this->mailSection(),
                    ]),
                    Tab::make('Bar & betalingen')->id('bar')->icon(Heroicon::OutlinedShoppingBag)->visible(fn (): bool => self::can('shop'))->schema([
                        Section::make('Bestellen aan tafel')
                            ->description(new HtmlString('Bestelpagina: <a href="'.e(route('shop.index')).'" target="_blank" style="text-decoration:underline;">'.e(route('shop.index')).'</a> (de QR-codes voegen ?tafel=A1 toe).'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('shop.open')->label('Bestellen is open')->helperText('Uit = gasten zien de melding hieronder. Kan ook snel via Bar → Bestellingen.')->live()->columnSpanFull(),
                                TextInput::make('shop.closed_title')->label('Titel als bestellen gesloten is')->required()->maxLength(120),
                                Textarea::make('shop.closed_message')->label('Melding als bestellen gesloten is')->rows(3)->maxLength(1000),
                                TextInput::make('shop.title')->label('Titel van de bestelpagina')->required()->maxLength(120),
                                Textarea::make('shop.intro')->label('Inleiding')->rows(2)->maxLength(500),
                                TextInput::make('shop.currency_singular')->label('Munt (enkelvoud)')->required()->maxLength(20),
                                TextInput::make('shop.currency_plural')->label('Munt (meervoud)')->required()->maxLength(20),
                                TextInput::make('shop.bonnen_label')->label('Naam van de betaalmethode bonnen')->required()->maxLength(80),
                                Textarea::make('shop.bonnen_instructions')->label('Uitleg bij bonnen')->rows(2)->maxLength(300),
                                Toggle::make('shop.require_name')->label('Naam verplicht'),
                                Toggle::make('shop.allow_note')->label('Opmerking toelaten'),
                                TextInput::make('shop.max_quantity')->label('Maximum per product per bestelling')->numeric()->integer()->minValue(1)->maxValue(99)->required(),
                                TextInput::make('shop.max_open_per_ip')->label('Maximum openstaande bestellingen per gast (IP-adres)')->helperText('Tegen misbruik. 0 = geen limiet.')->numeric()->integer()->minValue(0)->maxValue(50)->required(),
                                TextInput::make('shop.board_refresh')->label('Bestellingen vernieuwen elke ... seconden')->numeric()->integer()->minValue(2)->maxValue(60)->required(),
                                TextInput::make('shop.stage_label')->label('Voorkant op het tafelplan')->maxLength(40),
                                TextInput::make('shop.back_label')->label('Achterkant op het tafelplan')->maxLength(40),
                            ]),
                        Section::make('Online betalen')
                            ->description('Bonnen is altijd de standaard. Zet Stripe en/of SumUp aan om ook online betalen aan te bieden; zulke bestellingen verschijnen pas bij de bar als ze betaald zijn, met "Online betaald".')
                            ->columns(2)
                            ->schema([
                                TextInput::make('shop.bon_value')->label('Waarde van 1 bon (voor online betalen)')->numeric()->minValue(0.01)->step(0.01)->prefix('€')->required(),
                            ]),
                        Section::make('Stripe')
                            ->description(new HtmlString('Bancontact, kaarten, ... Maak in het Stripe-dashboard een webhook naar <code>'.e(route('webhooks.stripe')).'</code> met het event <code>checkout.session.completed</code>.'))
                            ->columns(2)
                            ->schema([
                                Toggle::make('payments.stripe_enabled')->label('Stripe gebruiken')->columnSpanFull(),
                                TextInput::make('payments.stripe_secret')->label('Secret key (sk_...)')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('payments.stripe_secret')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Wordt versleuteld opgeslagen.'),
                                TextInput::make('payments.stripe_webhook_secret')->label('Webhook signing secret (whsec_...)')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('payments.stripe_webhook_secret')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Optioneel: zonder webhook wordt de status gecontroleerd wanneer de gast terugkeert.'),
                            ]),
                        Section::make('SumUp')
                            ->description('Hosted checkout van SumUp. De status wordt altijd bij SumUp nagevraagd.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('payments.sumup_enabled')->label('SumUp gebruiken')->columnSpanFull(),
                                TextInput::make('payments.sumup_api_key')->label('API key (sup_sk_...)')->password()->revealable()->autocomplete('new-password')
                                    ->helperText(fn (): string => filled(settings()->get('payments.sumup_api_key')) ? 'Opgeslagen. Leeg laten om te behouden.' : 'Wordt versleuteld opgeslagen.'),
                                TextInput::make('payments.sumup_merchant_code')->label('Merchant code'),
                            ]),
                    ]),
                    Tab::make('Beveiliging & statistieken')->id('beveiliging')->icon(Heroicon::OutlinedShieldCheck)->visible(fn (): bool => self::can('security'))->schema([
                        Section::make('Automatisch blokkeren')
                            ->description('Elke aanmelding wordt bijgehouden bij Beveiliging → Aanmeldpogingen, met het land van het IP-adres.')
                            ->columns(3)
                            ->schema([
                                Toggle::make('security.auto_block')->label('IP-adres automatisch blokkeren na te veel mislukte aanmeldingen')->columnSpanFull()->live(),
                                TextInput::make('security.max_attempts')->label('Na ... mislukte pogingen')->numeric()->integer()->minValue(1)->required()->visible(fn (Get $get): bool => (bool) $get('security.auto_block')),
                                TextInput::make('security.window_minutes')->label('Binnen ... minuten')->numeric()->integer()->minValue(1)->required()->visible(fn (Get $get): bool => (bool) $get('security.auto_block')),
                                TextInput::make('security.block_minutes')->label('Blokkeren voor ... minuten')->helperText('0 = permanent')->numeric()->integer()->minValue(0)->required()->visible(fn (Get $get): bool => (bool) $get('security.auto_block')),
                                Textarea::make('security.whitelist')
                                    ->label('Nooit blokkeren (whitelist)')
                                    ->helperText(fn (): string => 'IP-adressen of reeksen (CIDR), één per lijn. Je huidige IP-adres is '.request()->ip().'.')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TextInput::make('security.retention_days')->label('Aanmeldpogingen bewaren (dagen)')->numeric()->integer()->minValue(30)->required(),
                            ]),
                        Section::make('Bezoekersstatistieken')
                            ->description('Er worden geen cookies gebruikt. Een IP-adres is wel een persoonsgegeven: vermeld het bewaren ervan en de bewaartermijn in je privacyverklaring.')
                            ->columns(2)
                            ->schema([
                                Toggle::make('stats.enabled')->label('Bezoeken tellen'),
                                Toggle::make('stats.store_ip')->label('IP-adres en browser van elk bezoek bewaren'),
                                Toggle::make('stats.exclude_logged_in')->label('Aangemelde gebruikers niet meetellen'),
                                TextInput::make('stats.retention_days')->label('Bezoeken bewaren (dagen)')->numeric()->integer()->minValue(30)->required(),
                            ]),
                    ]),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function platforms(): array
    {
        return [
            'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube',
            'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp', 'spotify' => 'Spotify',
            'email' => 'E-mail (mailto:)', 'website' => 'Andere website',
        ];
    }

    /**
     * @return list<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private static function linkFields(): array
    {
        return [
            TextInput::make('label')->label('Tekst')->required()->maxLength(80),
            TextInput::make('url')->label('Link')->required()->maxLength(500)->placeholder('/privacy, /#geschiedenis of https://...'),
            Toggle::make('new_tab')->label('In nieuw venster')->inline(false),
        ];
    }

    private function mailSection(): Section
    {
        $usesSmtp = fn (Get $get): bool => $get('mail.mailer') === 'smtp';

        return Section::make('E-mail')
            ->description(new HtmlString('Hoe de website mails verstuurt (welkomstmails, wachtwoord vergeten, ...). Gebruik "Testmail versturen" bovenaan na het opslaan. De teksten pas je aan bij <a href="'.MailTemplateResource::getUrl().'" style="text-decoration:underline;">Instellingen → E-mailsjablonen</a>.'))
            ->columns(2)
            ->schema([
                Toggle::make('mail.enabled')
                    ->label('E-mails versturen')
                    ->helperText('Uit = de website verstuurt geen enkele mail meer (ook geen wachtwoord-vergeten). Ze worden enkel in het logbestand genoteerd. De testmail werkt wel.')
                    ->live()
                    ->columnSpanFull(),
                Fieldset::make('Automatische mails')
                    ->visible(fn (Get $get): bool => (bool) $get('mail.enabled'))
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(collect(Mailing::automaticTypes())
                        ->map(fn (string $label, string $type) => Toggle::make('mail.auto_'.$type)->label($label))
                        ->values()
                        ->all()),
                Select::make('mail.mailer')
                    ->label('Verzendmethode')
                    ->options(['smtp' => 'SMTP', 'sendmail' => 'Sendmail (server)', 'log' => 'Niet versturen, enkel loggen'])
                    ->placeholder('Standaard (.env)')
                    ->live(),
                TextInput::make('mail.host')->label('SMTP-server')->placeholder('smtp.example.com')->required($usesSmtp)->visible($usesSmtp),
                TextInput::make('mail.port')->label('Poort')->numeric()->minValue(1)->maxValue(65535)->required($usesSmtp)->visible($usesSmtp),
                Select::make('mail.scheme')
                    ->label('Beveiliging')
                    ->options(['smtp' => 'STARTTLS (meestal poort 587)', 'smtps' => 'SSL/TLS (meestal poort 465)'])
                    ->placeholder('Automatisch')
                    ->visible($usesSmtp),
                TextInput::make('mail.username')->label('Gebruikersnaam')->autocomplete('off')->visible($usesSmtp),
                TextInput::make('mail.password')
                    ->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->helperText(fn (): string => filled(settings()->get('mail.password')) ? 'Er is een wachtwoord opgeslagen. Leeg laten om het te behouden.' : 'Wordt versleuteld opgeslagen.')
                    ->visible($usesSmtp),
                TextInput::make('mail.from_address')->label('Afzender (e-mailadres)')->email()->placeholder(fn (): string => (string) (settings()->get('vzw.email') ?: config('mail.from.address'))),
                TextInput::make('mail.from_name')->label('Afzender (naam)')->placeholder(fn (): string => settings()->siteName()),
                TextInput::make('mail.reply_to')->label('Antwoorden naar (reply-to)')->email()->placeholder(fn (): string => (string) settings()->get('vzw.email')),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Opslaan')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(SiteSettings $settings): void
    {
        $state = $this->form->getState();
        $data = [];

        // Only the tabs this user may change are saved, whatever the browser sends.
        foreach (self::tabKeys() as $tab => $definition) {
            if (! self::can($tab)) {
                continue;
            }

            foreach ($definition['keys'] as $key) {
                if (array_key_exists($key, $state)) {
                    $data[$key] = $state[$key];
                }
            }
        }

        foreach (['favicon', 'logo'] as $file) {
            $old = $settings->get($file);

            if (array_key_exists($file, $data) && filled($old) && $old !== $data[$file]) {
                Storage::disk('public')->delete($old);
            }
        }

        // Nested groups keep the keys hidden fields did not send.
        foreach (SiteSettings::groups() as $group) {
            if (array_key_exists($group, $data)) {
                $data[$group] = [...(array) $settings->get($group), ...(array) $data[$group]];
            }
        }

        if (isset($data['mail'])) {
            $data['mail']['port'] = filled($data['mail']['port'] ?? null) ? (int) $data['mail']['port'] : null;
        }

        // Secrets: a new value is encrypted, an empty field keeps the stored one.
        foreach (SiteSettings::secretKeys() as $secret) {
            if (array_key_exists(explode('.', $secret)[0], $data)) {
                $value = Arr::get($state, $secret);
                Arr::set($data, $secret, filled($value) ? Crypt::encryptString($value) : $settings->get($secret));
            }
        }

        if (isset($data['shop'])) {
            $data['shop']['bon_value'] = round(max(0.01, (float) $data['shop']['bon_value']), 2);
            $data['shop']['max_quantity'] = (int) $data['shop']['max_quantity'];
            $data['shop']['max_open_per_ip'] = (int) ($data['shop']['max_open_per_ip'] ?? 5);
            $data['shop']['board_refresh'] = (int) $data['shop']['board_refresh'];
        }

        if (isset($data['security'])) {
            foreach (['max_attempts', 'window_minutes', 'block_minutes', 'retention_days'] as $number) {
                $data['security'][$number] = (int) ($data['security'][$number] ?? 0);
            }
            $data['stats']['retention_days'] = (int) ($data['stats']['retention_days'] ?? 730);
        }

        if (isset($data['social_links'])) {
            $data['social_links'] = array_values((array) $data['social_links']);
        }

        if (isset($data['footer'])) {
            $data['footer']['columns'] = array_values(array_map(fn (array $column): array => [...$column, 'links' => array_values((array) ($column['links'] ?? []))], (array) ($data['footer']['columns'] ?? [])));
            $data['footer']['bottom_links'] = array_values((array) ($data['footer']['bottom_links'] ?? []));
        }

        $settings->save($data);
        foreach (SiteSettings::secretKeys() as $secret) {
            Arr::set($this->data, $secret, null);
        }

        Notification::make()->success()->title('Instellingen opgeslagen')->send();
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestMail')
                ->label('Testmail versturen')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->visible(fn (): bool => self::can('mail'))
                ->modalDescription('Verstuurt een testmail met de opgeslagen instellingen. Sla wijzigingen dus eerst op.')
                ->schema([
                    TextInput::make('recipient')->label('Naar')->email()->required()->default(fn (): ?string => auth()->user()?->email),
                ])
                ->modalSubmitActionLabel('Versturen')
                ->action(function (array $data, SiteSettings $settings): void {
                    // The mailer may already be built with older settings during this request.
                    Mail::purge();
                    $settings->applyMailConfig();

                    try {
                        Mail::raw(
                            'Dit is een testmail van de website van '.$settings->siteName().'. De mailinstellingen werken.',
                            fn ($message) => $message->to($data['recipient'])->subject($settings->siteName().' - testmail')
                                ->getSymfonyMessage()->getHeaders()->addTextHeader(Mailing::TEST_HEADER, '1'),
                        );

                        Notification::make()->success()->title('Testmail verstuurd naar '.$data['recipient'])
                            ->body(collect([
                                config('mail.default') === 'log' ? 'Let op: de verzendmethode is "loggen", de mail staat enkel in storage/logs/laravel.log.' : null,
                                Mailing::enabled() ? null : 'E-mails versturen staat uit: andere mails worden niet verstuurd.',
                            ])->filter()->implode(' ') ?: null)
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->persistent()->title('Testmail mislukt')->body($exception->getMessage())->send();
                    }
                }),
        ];
    }
}
