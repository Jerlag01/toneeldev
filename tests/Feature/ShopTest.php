<?php

namespace Tests\Feature;

use App\Filament\Pages\Bar\OrderBoard;
use App\Filament\Pages\Bar\SalesStats;
use App\Filament\Pages\Bar\TablePlan;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShopTable;
use App\Support\Shop\OrderService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function product(string $name): Product
    {
        return Product::query()->where('name', $name)->firstOrFail();
    }

    public function test_qr_link_prefills_the_table(): void
    {
        $this->get('/bestel?tafel=B3')->assertOk()->assertSee('Tafel B3')->assertSee('Cola')->assertSee('5 bonnen');
        $this->get('/bestel')->assertOk()->assertSee('Kies je tafel');
    }

    public function test_guest_orders_with_bonnen_and_it_shows_on_the_board(): void
    {
        $cola = $this->product('Cola');
        $duvel = $this->product('Duvel');

        $response = $this->postJson('/bestel', [
            'table' => 'B3', 'name' => 'Sarah', 'payment_method' => 'bonnen', 'note' => 'zonder ijs',
            'items' => [$cola->id => 2, $duvel->id => 1],
        ])->assertOk();

        $order = Order::query()->with('items')->sole();
        $this->assertSame('open', $order->status);
        $this->assertSame('B3', $order->table_label);
        $this->assertEquals(19, (float) $order->total);
        $response->assertJson(['redirect' => $order->publicUrl()]);

        $this->get($order->publicUrl())->assertOk()->assertSee('19 bonnen')->assertSee('Geef af bij het brengen');

        $this->actingAs($this->user(['shop_orders']));
        Livewire::test(OrderBoard::class)->assertSee('B3')->assertSee('Sarah')->assertSeeHtml('<b>2&times;</b>')->assertSee('Duvel')->assertSee('19 bonnen')->assertSee('zonder ijs');
        Livewire::test(TablePlan::class)->call('select', $order->shop_table_id)->assertSee('Sarah');

        Livewire::test(OrderBoard::class)->call('closeOrder', $order->id)->assertDontSee('Sarah');
        $this->assertSame('closed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->closed_at);
        $this->assertSame(1, Order::query()->count(), 'closing never deletes');
    }

    public function test_sold_out_and_stock(): void
    {
        $cola = $this->product('Cola');
        $cola->update(['sold_out' => true]);

        $this->postJson('/bestel', ['table' => 'A1', 'name' => 'X', 'payment_method' => 'bonnen', 'items' => [$cola->id => 1]])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        $fanta = $this->product('Fanta');
        $fanta->update(['track_stock' => true, 'stock' => 2]);

        $this->postJson('/bestel', ['table' => 'A1', 'name' => 'X', 'payment_method' => 'bonnen', 'items' => [$fanta->id => 3]])->assertStatus(422);
        $this->postJson('/bestel', ['table' => 'A1', 'name' => 'X', 'payment_method' => 'bonnen', 'items' => [$fanta->id => 2]])->assertOk();

        $this->assertSame(0, $fanta->fresh()->stock);
        $this->assertFalse($fanta->fresh()->isOrderable());
        $this->get('/bestel?tafel=A1')->assertSee('Uitverkocht');
    }

    public function test_closed_shop_shows_the_message_and_refuses_orders(): void
    {
        settings()->save(['shop' => [...settings()->get('shop'), 'open' => false, 'closed_message' => 'Pauze! Bar is dicht.']]);

        $this->get('/bestel?tafel=A1')->assertOk()->assertSee('Pauze! Bar is dicht.')->assertDontSee('Bestelling plaatsen');
        $this->postJson('/bestel', ['table' => 'A1', 'name' => 'X', 'payment_method' => 'bonnen', 'items' => [$this->product('Cola')->id => 1]])->assertStatus(422);
    }

    public function test_online_payment_is_hidden_until_paid_then_marked_as_paid(): void
    {
        settings()->save(['payments' => [...settings()->get('payments'), 'stripe_enabled' => true, 'stripe_secret' => \Illuminate\Support\Facades\Crypt::encryptString('sk_test')]]);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_123', 'url' => 'https://checkout.stripe.com/pay/cs_123']),
            'api.stripe.com/v1/checkout/sessions/cs_123' => Http::sequence()
                ->push(['id' => 'cs_123', 'payment_status' => 'unpaid', 'status' => 'open'])
                ->push(['id' => 'cs_123', 'payment_status' => 'paid', 'status' => 'complete', 'payment_intent' => 'pi_1']),
        ]);

        $this->get('/bestel?tafel=C2')->assertSee('Online betalen (Bancontact, kaart)');

        $this->postJson('/bestel', ['table' => 'C2', 'name' => 'Online Olga', 'payment_method' => 'stripe', 'items' => [$this->product('Cava')->id => 2]])
            ->assertOk()->assertJson(['redirect' => 'https://checkout.stripe.com/pay/cs_123']);

        $order = Order::query()->sole();
        $this->assertSame(Order::PENDING, $order->status);
        $this->assertEquals(18, (float) $order->total_eur);

        $this->actingAs($this->user(['shop_orders']));
        Livewire::test(OrderBoard::class)->assertDontSee('Online Olga');

        $this->get($order->publicUrl())->assertSee('Wachten op je betaling');
        $this->get($order->publicUrl().'?betaald=1')->assertSee('Online betaald');

        $this->assertSame(Order::OPEN, $order->fresh()->status);
        Livewire::test(OrderBoard::class)->assertSee('Online Olga')->assertSee('Online betaald');
    }

    public function test_failed_online_payment_puts_stock_back(): void
    {
        $product = $this->product('Duvel');
        $product->update(['track_stock' => true, 'stock' => 5]);
        settings()->save(['payments' => [...settings()->get('payments'), 'sumup_enabled' => true, 'sumup_merchant_code' => 'M1', 'sumup_api_key' => \Illuminate\Support\Facades\Crypt::encryptString('key')]]);

        Http::fake([
            'api.sumup.com/v0.1/checkouts' => Http::response(['id' => 'chk_1', 'hosted_checkout_url' => 'https://pay.sumup.com/chk_1']),
            'api.sumup.com/v0.1/checkouts/chk_1' => Http::response(['id' => 'chk_1', 'status' => 'FAILED']),
        ]);

        $this->postJson('/bestel', ['table' => 'C2', 'name' => 'Y', 'payment_method' => 'sumup', 'items' => [$product->id => 2]])->assertOk();
        $this->assertSame(3, $product->fresh()->stock);

        app(OrderService::class)->sync(Order::query()->sole());

        $this->assertSame(Order::CANCELLED, Order::query()->sole()->status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_sales_statistics_per_product_and_day(): void
    {
        $service = app(OrderService::class);
        $table = ShopTable::findByLabel('A2');
        $service->place($table, 'A', [$this->product('Cola')->id => 3], 'bonnen');
        $service->place($table, 'B', [$this->product('Cola')->id => 1, $this->product('Hotdog')->id => 2], 'bonnen');

        $this->actingAs($this->user(['shop_stats']));
        $data = Livewire::test(SalesStats::class)->instance()->data();

        $cola = collect($data['rows'])->firstWhere('name', 'Cola');
        $this->assertSame(4, $cola['quantity']);
        $this->assertSame(4, $cola['per_day'][today()->toDateString()]);
        $this->assertSame(2, $data['totals']['orders']);
        $this->assertEquals(30, $data['totals']['bonnen']);
    }

    public function test_bar_role_cannot_change_payment_settings_or_tables(): void
    {
        $this->actingAs($this->user(['shop_orders', 'shop_products']));

        $this->get(ProductResource::getUrl())->assertOk();
        $this->get(OrderBoard::getUrl())->assertOk();
        $this->get(\App\Filament\Resources\ShopTables\ShopTableResource::getUrl())->assertForbidden();
        $this->get(route('admin.qr.print'))->assertForbidden();
        $this->get(SalesStats::getUrl())->assertForbidden();
    }

    public function test_qr_print_contains_a_code_per_table(): void
    {
        $this->actingAs($this->user(['shop_tables']));

        $html = $this->get(route('admin.qr.print'))->assertOk()->assertSee('Tafel A1')->assertSee('Tafel F5')->getContent();
        $this->assertSame(31, substr_count($html, 'class="qr-card"'));
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_bar_cancels_an_order_from_the_board_and_stock_comes_back(): void
    {
        $fanta = $this->product('Fanta');
        $fanta->update(['track_stock' => true, 'stock' => 10]);
        $order = app(OrderService::class)->place(ShopTable::findByLabel('D4'), 'Grappenmaker', [$fanta->id => 8], 'bonnen', null, '203.0.113.50');
        $this->assertSame(2, $fanta->fresh()->stock);

        $this->actingAs($this->user(['shop_orders']));
        Livewire::test(OrderBoard::class)
            ->assertSee('Grappenmaker')
            ->callAction(\Filament\Actions\Testing\TestAction::make('cancelOrder')->arguments(['order' => $order->id]), ['reason' => 'excessive', 'note' => '8 fanta voor 1 persoon'])
            ->assertHasNoActionErrors()
            ->assertDontSee('Grappenmaker');

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertSame('excessive', $order->cancel_reason);
        $this->assertSame(10, $fanta->fresh()->stock);
        $this->assertNull(\App\Models\BlockedIp::query()->first(), 'no IP block without the security permission');

        $this->get($order->publicUrl())->assertSee('geannuleerd door de bar');

        $data = Livewire::actingAs($this->user(['shop_stats']))->test(SalesStats::class)->instance()->data();
        $this->assertSame(0, $data['totals']['quantity'], 'cancelled orders do not count');
    }

    public function test_abuse_blocks_the_ip_and_limits_open_orders(): void
    {
        settings()->save(['shop' => [...settings()->get('shop'), 'max_open_per_ip' => 2]]);
        $cola = $this->product('Cola')->id;

        foreach ([1, 2] as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
                ->postJson('/bestel', ['table' => 'A1', 'name' => 'Spam', 'payment_method' => 'bonnen', 'items' => [$cola => 1]])->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->postJson('/bestel', ['table' => 'A1', 'name' => 'Spam', 'payment_method' => 'bonnen', 'items' => [$cola => 1]])
            ->assertStatus(422)->assertJsonValidationErrors('order');

        $order = Order::query()->first();
        $this->actingAs($this->user(['shop_orders', 'security']));

        Livewire::test(\App\Filament\Pages\Bar\TablePlan::class)
            ->callAction(\Filament\Actions\Testing\TestAction::make('cancelOrder')->arguments(['order' => $order->id]), ['reason' => 'abuse', 'block_ip' => true])
            ->assertHasNoActionErrors();

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertNotNull(app(\App\Support\Security\IpBlocker::class)->blockFor('198.51.100.20'));
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->get('/bestel')->assertForbidden();
    }

    public function test_paid_online_order_can_be_refunded_on_cancel(): void
    {
        settings()->save(['payments' => [...settings()->get('payments'), 'stripe_enabled' => true, 'stripe_secret' => \Illuminate\Support\Facades\Crypt::encryptString('sk_test')]]);
        Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);

        $order = app(OrderService::class)->place(ShopTable::findByLabel('A1'), 'Z', [$this->product('Cava')->id => 1], 'stripe');
        $order->update(['status' => Order::OPEN, 'payment_status' => 'paid', 'provider_transaction' => 'pi_9', 'paid_at' => now()]);

        $warnings = app(OrderService::class)->cancelByStaff($order, 'customer', null, null, refund: true);

        $this->assertSame([], $warnings);
        $this->assertNotNull($order->fresh()->refunded_at);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'refunds') && $request['payment_intent'] === 'pi_9');
    }

    public function test_only_bar_managers_can_delete_orders_for_good(): void
    {
        $order = app(OrderService::class)->place(ShopTable::findByLabel('A1'), 'Test', [$this->product('Cola')->id => 1], 'bonnen');

        $this->actingAs($this->user(['shop_orders']));
        $this->assertFalse(\App\Filament\Resources\Orders\OrderResource::canDelete($order));

        $this->actingAs($this->user(['shop_orders', 'shop_settings']));
        Livewire::test(\App\Filament\Resources\Orders\Pages\ListOrders::class, ['activeTab' => 'all'])
            ->callAction(\Filament\Actions\Testing\TestAction::make('delete')->table($order));

        $this->assertNull(Order::query()->find($order->id));
    }

    public function test_floor_plan_is_edited_and_shown_live_with_orders(): void
    {
        $this->actingAs($this->user(['shop_tables', 'shop_orders']));
        $suggestion = Livewire::test(\App\Filament\Pages\Bar\FloorPlanEditor::class)->instance()->suggestion();

        $this->assertCount(31, $suggestion['tables']);
        $this->assertSame('stage', $suggestion['elements'][0]['type']);
        $a1 = collect($suggestion['tables'])->firstWhere('label', 'A1');
        $a5 = collect($suggestion['tables'])->firstWhere('label', 'A5');
        $b1 = collect($suggestion['tables'])->firstWhere('label', 'B1');
        $this->assertLessThan($a5['x'], $a1['x'], 'number 1 is on the left');
        $this->assertLessThan($b1['y'], $a1['y'], 'row A is nearest to the stage');

        // A door added by hand, one table taken off the plan.
        $suggestion['elements'][] = ['id' => null, 'type' => 'door', 'label' => 'Zijdeur', 'x' => 1, 'y' => 1, 'w' => 3, 'h' => 1, 'color' => null];
        $suggestion['tables'] = collect($suggestion['tables'])->map(fn ($t) => $t['label'] === 'T1' ? [...$t, 'x' => null, 'y' => null] : $t)->all();

        Livewire::test(\App\Filament\Pages\Bar\FloorPlanEditor::class)->call('savePlan', $suggestion);

        $this->assertTrue(\App\Models\FloorElement::query()->where('label', 'Zijdeur')->exists());
        $this->assertFalse(ShopTable::findByLabel('T1')->isPlaced());
        $this->assertTrue(ShopTable::findByLabel('A1')->isPlaced());

        app(OrderService::class)->place(ShopTable::findByLabel('A1'), 'Plan', [$this->product('Cola')->id => 1], 'bonnen');

        Livewire::test(TablePlan::class)
            ->assertSee('Zijdeur')
            ->assertSeeHtml('fp-table')
            ->assertSee('Niet op het grondplan')
            ->call('select', ShopTable::findByLabel('A1')->id)
            ->assertSee('Plan');

        // Saving again with the door removed deletes it.
        $data = \App\Support\Shop\FloorPlan::data();
        $data['elements'] = array_values(array_filter($data['elements'], fn ($e) => $e['label'] !== 'Zijdeur'));
        Livewire::test(\App\Filament\Pages\Bar\FloorPlanEditor::class)->call('savePlan', $data);
        $this->assertFalse(\App\Models\FloorElement::query()->where('label', 'Zijdeur')->exists());
    }
}
