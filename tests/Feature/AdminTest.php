<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Pages\StatisticsPage;
use App\Filament\Resources\BlockedIps\BlockedIpResource;
use App\Filament\Resources\LoginAttempts\LoginAttemptResource;
use App\Filament\Resources\MailTemplates\MailTemplateResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\PageViews\PageViewResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Mail\WelcomeMail;
use App\Models\Page;
use App\Models\PageView;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_open_every_admin_page(): void
    {
        PageView::query()->create(['visitor' => 'abc', 'ip' => '81.82.83.84', 'path' => '/', 'source' => 'direct', 'country_code' => 'BE', 'device' => 'desktop', 'browser' => 'Chrome']);
        $this->actingAs($this->admin());

        $urls = [
            '/admin', PageResource::getUrl(), PageResource::getUrl('create'), PageResource::getUrl('edit', ['record' => Page::query()->where('slug', 'home')->first()]),
            StatisticsPage::getUrl(), PageViewResource::getUrl(), Settings::getUrl(), MailTemplateResource::getUrl(),
            MailTemplateResource::getUrl('edit', ['record' => 'welcome']), UserResource::getUrl(), RoleResource::getUrl(),
            LoginAttemptResource::getUrl(), BlockedIpResource::getUrl(),
            \App\Filament\Resources\Productions\ProductionResource::getUrl(), \App\Filament\Resources\Productions\ProductionResource::getUrl('create'),
            \App\Filament\Resources\Productions\ProductionResource::getUrl('edit', ['record' => \App\Models\Production::query()->first()]),
            \App\Filament\Pages\Bar\OrderBoard::getUrl(), \App\Filament\Pages\Bar\TablePlan::getUrl(), \App\Filament\Pages\Bar\SalesStats::getUrl(),
            \App\Filament\Pages\Bar\QrDesigner::getUrl(), \App\Filament\Resources\Orders\OrderResource::getUrl(),
            \App\Filament\Resources\Products\ProductResource::getUrl(), \App\Filament\Resources\ShopCategories\ShopCategoryResource::getUrl(),
            \App\Filament\Resources\ShopTables\ShopTableResource::getUrl(), route('admin.qr.print'), \App\Filament\Pages\Bar\FloorPlanEditor::getUrl(),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(StatisticsPage::getUrl())->assertSee('81.82.83.84')->assertSee('België');
    }

    public function test_permissions_limit_what_a_user_sees(): void
    {
        $writer = $this->user(['pages']);
        $this->actingAs($writer);

        $this->get(PageResource::getUrl())->assertOk();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(StatisticsPage::getUrl())->assertForbidden();
        $this->get(PageViewResource::getUrl())->assertForbidden();
        $this->get(Settings::getUrl())->assertForbidden();
        $this->get(BlockedIpResource::getUrl())->assertForbidden();
        $this->get(RoleResource::getUrl())->assertForbidden();
    }

    public function test_settings_only_save_tabs_the_user_may_change(): void
    {
        $this->actingAs($this->user(['footer']));

        Livewire::test(Settings::class)
            ->set('data.site_name', 'Gehackt')
            ->set('data.footer.about', 'Nieuwe footertekst')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nieuwe footertekst', settings()->get('footer.about'));
        $this->assertSame('Het Vrolijk Toneel Initiatief', settings()->siteName());
    }

    public function test_admin_saves_all_settings(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->set('data.site_name', 'Nieuwe naam')
            ->set('data.seo.description', 'Nieuwe beschrijving')
            ->set('data.vzw.company_number', '0123.456.789')
            ->set('data.mail.mailer', 'smtp')
            ->set('data.mail.host', 'smtp.example.com')
            ->set('data.mail.port', 587)
            ->set('data.mail.password', 'geheim')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nieuwe naam', settings()->siteName());
        $this->assertSame('0123.456.789', settings()->get('vzw.company_number'));
        $this->assertSame('geheim', settings()->secret('mail.password'));
        $this->assertNotSame('geheim', settings()->get('mail.password'));
    }

    public function test_new_user_gets_a_welcome_mail(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());
        $editor = Role::query()->where('name', 'Redacteur')->first();

        Livewire::test(ManageUsers::class)
            ->callAction('create', [
                'name' => 'Jan Peeters',
                'email' => 'jan@example.com',
                'send_welcome' => true,
                'is_active' => true,
                'roles' => [$editor->id],
            ])
            ->assertHasNoActionErrors();

        $user = User::query()->where('email', 'jan@example.com')->sole();
        $this->assertNotNull($user->welcome_sent_at);
        $this->assertTrue($user->roles->contains($editor));

        Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail) use ($user): bool {
            $html = $mail->render();

            return $mail->hasTo('jan@example.com') && $mail->user->is($user) && str_contains($html, 'Welkom, Jan!') && str_contains($html, 'password-reset');
        });
    }

    public function test_user_manager_cannot_hand_out_the_admin_role(): void
    {
        $manager = $this->user(['users']);
        $admin = $this->admin();
        $adminRole = Role::query()->where('is_admin', true)->first();
        $this->actingAs($manager);

        $this->assertFalse(UserResource::canEdit($admin));

        Livewire::test(ManageUsers::class)
            ->callAction('create', [
                'name' => 'Stiekem',
                'email' => 'stiekem@example.com',
                'send_welcome' => false,
                'password' => 'wachtwoord123',
                'is_active' => true,
                'roles' => [$adminRole->id],
            ]);

        $this->assertFalse((bool) User::query()->where('email', 'stiekem@example.com')->first()?->isAdmin());
    }

    public function test_failed_logins_are_logged_and_block_the_ip(): void
    {
        settings()->save(['security' => [...settings()->get('security'), 'max_attempts' => 3]]);
        $blocker = app(\App\Support\Security\IpBlocker::class);

        foreach (range(1, 3) as $i) {
            $blocker->record('x@example.com', '198.51.100.7', false);
        }

        $this->assertNotNull($blocker->blockFor('198.51.100.7'));
    }
}
