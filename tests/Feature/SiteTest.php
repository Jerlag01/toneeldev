<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\Page;
use App\Models\PageView;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_home_shows_productions_and_links_to_the_story(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Voorbije voorstellingen')
            ->assertSee('Verkens')
            ->assertSee('href="/story"', false)
            ->assertSee('Website door')
            ->assertSee('https://github.com/Jerlag01', false);

        $this->get('/verhaal')->assertRedirect('/story');
    }

    public function test_story_lives_on_its_own_page(): void
    {
        $this->get('/story')
            ->assertOk()
            ->assertSee('Het Vrolijk Toneel Initiatief')
            ->assertSee('Geschiedenis')
            ->assertSee('Over Olifantjes')
            ->assertSee('Vroeger hé, dan moeste wij werke voor ne patat!')
            ->assertSee('Het Goede Doel Pieter Van de Poel')
            ->assertSee('data-youtube="b4GpJ4FHz3g"', false)
            ->assertSee('href="#geschiedenis"', false)
            ->assertSee('noindex, nofollow', false);
    }

    public function test_settings_change_the_public_site(): void
    {
        settings()->save([
            'site_name' => 'VTI Test',
            'vzw' => [...settings()->get('vzw'), 'company_number' => '0999.888.777', 'email' => 'info@example.com'],
            'social_links' => [['platform' => 'instagram', 'url' => 'https://instagram.com/vti', 'label' => null]],
            'footer' => [...settings()->get('footer'), 'copyright' => '© {jaar} {vzw}', 'bottom_links' => [['label' => 'Cookies', 'url' => '/cookies', 'new_tab' => false]]],
        ]);

        $this->get('/story')
            ->assertOk()
            ->assertSee('VTI Test')
            ->assertSee('0999.888.777')
            ->assertSee('https://instagram.com/vti', false)
            ->assertSee('© '.now()->year.' Vrolijk Toneel Initiatief vzw')
            ->assertSee('Cookies');
    }

    public function test_unpublished_pages_are_hidden_from_visitors(): void
    {
        Page::query()->create(['title' => 'Geheim', 'slug' => 'geheim', 'is_published' => false, 'blocks' => []]);

        $this->get('/geheim')->assertNotFound();
        $this->actingAs($this->user(['pages']))->get('/geheim')->assertOk()->assertSee('nog niet gepubliceerd');
    }

    public function test_page_views_are_stored_with_ip_and_country_fields(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '81.82.83.84'])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1', 'Referer' => 'https://www.google.com/'])
            ->get('/story')
            ->assertOk();

        $view = PageView::query()->sole();
        $this->assertSame('81.82.83.84', $view->ip);
        $this->assertSame('search', $view->source);
        $this->assertSame('mobile', $view->device);
        $this->assertSame('Safari', $view->browser);
    }

    public function test_ip_is_not_stored_when_switched_off_and_bots_are_ignored(): void
    {
        settings()->save(['stats' => [...settings()->get('stats'), 'store_ip' => false]]);

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Firefox/120.0'])->get('/');
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->get('/');

        $this->assertSame(1, PageView::query()->count());
        $this->assertNull(PageView::query()->value('ip'));
    }

    public function test_blocked_ip_gets_no_access(): void
    {
        BlockedIp::query()->create(['ip' => '203.0.113.0/24', 'reason' => 'test']);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/admin/login')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get('/')->assertOk();
    }

    public function test_robots_and_sitemap(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/privacy'));
    }
}
