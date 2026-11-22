<?php

namespace App\Filament\Pages\Bar;

use App\Enums\NavigationGroup;
use App\Models\ShopTable;
use App\Support\Permissions;
use App\Support\Settings as SiteSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Free design of the printed table cards: drag the logo, title, QR code, table number and text to any spot,
 * set sizes, colours, font, paper size and a background image. Saved in settings "qr".
 */
class QrDesigner extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Bar;

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'QR-ontwerp';

    protected static ?string $title = 'QR-codes ontwerpen';

    protected static ?string $slug = 'bar/qr-ontwerp';

    protected string $view = 'filament.bar.qr-designer';

    /** @var array<string, mixed> */
    public array $design = [];

    public string $sample = 'A1';

    /** @var TemporaryUploadedFile|null */
    public $backgroundUpload = null;

    /** @var TemporaryUploadedFile|null */
    public $logoUpload = null;

    public const FONTS = ['Playfair Display', 'Source Serif 4', 'Source Sans 3', 'Montserrat', 'Oswald', 'Bebas Neue', 'Lobster', 'Pacifico', 'Roboto Slab', 'Abril Fatface'];

    public const SIZES = ['100x148' => 'A6 staand (10 × 14,8 cm)', '148x100' => 'A6 liggend', '148x210' => 'A5 staand (14,8 × 21 cm)', '210x148' => 'A5 liggend', '85x55' => 'Visitekaartje (8,5 × 5,5 cm)', '100x100' => 'Vierkant 10 × 10 cm', '99x210' => 'Tafelstandaard DL (9,9 × 21 cm)'];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::SHOP_TABLES);
    }

    public function mount(): void
    {
        $this->design = (array) settings()->get('qr');
        $this->sample = ShopTable::query()->ordered()->first()?->label() ?? 'A1';
    }

    public function updatedBackgroundUpload(): void
    {
        $this->validate(['backgroundUpload' => ['image', 'max:10240']]);
        $this->design['background_image'] = $this->backgroundUpload->storePublicly('qr', 'public');
        $this->backgroundUpload = null;
    }

    public function updatedLogoUpload(): void
    {
        $this->validate(['logoUpload' => ['image', 'max:5120']]);
        $this->design['logo'] = $this->logoUpload->storePublicly('qr', 'public');
        $this->logoUpload = null;
    }

    public function removeBackground(): void
    {
        $this->design['background_image'] = null;
    }

    public function removeLogo(): void
    {
        $this->design['logo'] = null;
    }

    public function setPaper(string $size): void
    {
        if (preg_match('/^(\d+)x(\d+)$/', $size, $m)) {
            $this->design['width_mm'] = (int) $m[1];
            $this->design['height_mm'] = (int) $m[2];
        }
    }

    /**
     * Called from the drag handler in the preview.
     */
    public function moveElement(string $key, float $x, float $y): void
    {
        if (isset($this->design['elements'][$key])) {
            $this->design['elements'][$key]['x'] = round(max(0, min(100, $x)), 1);
            $this->design['elements'][$key]['y'] = round(max(0, min(100, $y)), 1);
        }
    }

    public function save(SiteSettings $settings): void
    {
        $design = $this->design;
        $design['width_mm'] = max(40, min(300, (float) $design['width_mm']));
        $design['height_mm'] = max(40, min(300, (float) $design['height_mm']));

        foreach (['background_color', 'text_color', 'accent_color', 'qr_color'] as $color) {
            $design[$color] = preg_match('/^#[0-9a-f]{6}$/i', (string) ($design[$color] ?? '')) ? $design[$color] : SiteSettings::defaults()['qr'][$color];
        }

        $design['font'] = in_array($design['font'] ?? null, self::FONTS, true) ? $design['font'] : 'Playfair Display';

        foreach ($design['elements'] as $key => $element) {
            $design['elements'][$key]['visible'] = (bool) ($element['visible'] ?? false);
            $design['elements'][$key]['size'] = max(1, min(100, (float) ($element['size'] ?? 10)));
            $design['elements'][$key]['x'] = max(0, min(100, (float) ($element['x'] ?? 50)));
            $design['elements'][$key]['y'] = max(0, min(100, (float) ($element['y'] ?? 50)));

            if (array_key_exists('text', $element)) {
                $design['elements'][$key]['text'] = mb_substr((string) $element['text'], 0, 120);
            }
        }

        // Old uploads that are no longer used are removed.
        $old = (array) settings()->get('qr');
        foreach (['background_image', 'logo'] as $file) {
            if (filled($old[$file] ?? null) && ($old[$file] ?? null) !== ($design[$file] ?? null)) {
                Storage::disk('public')->delete($old[$file]);
            }
        }

        $settings->save(['qr' => $design]);
        $this->design = (array) settings()->get('qr');

        Notification::make()->success()->title('Ontwerp opgeslagen')->send();
    }

    public function resetDesign(): void
    {
        $this->design = SiteSettings::defaults()['qr'];
        Notification::make()->info()->title('Standaardontwerp geladen')->body('Klik op Opslaan om het te bewaren.')->send();
    }

    public function sampleTable(): ShopTable
    {
        return ShopTable::findByLabel($this->sample) ?? new ShopTable(['row' => 'A', 'number' => 1]);
    }

    /**
     * Labels and which fields each element has.
     *
     * @return array<string, array{label: string, text: bool, hint?: string}>
     */
    public static function elementInfo(): array
    {
        return [
            'logo' => ['label' => 'Logo', 'text' => false],
            'title' => ['label' => 'Titel', 'text' => true],
            'qr' => ['label' => 'QR-code', 'text' => false],
            'table' => ['label' => 'Tafelnummer', 'text' => true, 'hint' => '{tafel} = A3, {rij} = A, {nummer} = 3'],
            'text' => ['label' => 'Extra tekst', 'text' => true],
        ];
    }
}
