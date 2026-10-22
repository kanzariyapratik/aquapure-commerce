<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\RazorpayService;
use App\Services\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;

class ShoppingAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Test Filter', 'slug' => 'test-filter', 'sku' => 'TEST-001',
            'price' => 125, 'stock' => 20, 'active' => true,
        ]);
    }

    private function customer(string $email = 'customer@example.com'): User
    {
        return User::create(['name' => 'Customer', 'email' => $email, 'password' => 'StrongPassword123!', 'role' => 'customer']);
    }

    private function delivery(): array
    {
        return [
            'customer_name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '1234567890',
            'shipping_address' => '123 Test Street', 'city' => 'Test City', 'state' => 'Test State',
            'pincode' => '123456', 'type' => 'retail',
        ];
    }

    #[TestWith(['/', 'Store/Home'])]
    #[TestWith(['/products', 'Store/Products'])]
    #[TestWith(['/products/test-filter', 'Store/Product'])]
    #[TestWith(['/wholesale', 'Store/Wholesale'])]
    public function test_visitors_can_browse_public_pages_without_cart_access(string $path, string $component): void
    {
        $product = $this->product();
        $this->withSession(['cart' => [$product->id => ['quantity' => 3]]])->get($path)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)
                ->where('auth.user', null)->where('cartCount', 0)->missing('items'));
        $this->assertGuest();
    }

    #[TestWith(['GET', '/cart'])]
    #[TestWith(['POST', '/cart'])]
    #[TestWith(['PATCH', '/cart/1'])]
    public function test_guests_cannot_view_add_update_or_remove_cart_items(string $method, string $path): void
    {
        $product = $this->product();
        $cart = [$product->id => ['quantity' => 2]];
        $this->withSession(['cart' => $cart])->call($method, $path, [
            'product_id' => $product->id, 'quantity' => $method === 'PATCH' ? 0 : 1,
        ])->assertRedirect('/login');
        $this->assertSame($cart, session('cart'));
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    #[TestWith(['GET', '/checkout'])]
    #[TestWith(['POST', '/checkout'])]
    #[TestWith(['GET', '/orders'])]
    #[TestWith(['GET', '/orders/1'])]
    #[TestWith(['POST', '/orders/1/verify-payment'])]
    public function test_guests_cannot_access_orders_checkout_or_pay_even_with_valid_payloads(string $method, string $path): void
    {
        $product = $this->product();
        $owner = $this->customer();
        $order = app(OrderService::class)->create($owner, $this->delivery(), [$product->id => ['quantity' => 1]]);
        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldNotReceive('createOrder');
            $mock->shouldNotReceive('verify');
        });
        $this->withSession(['cart' => [$product->id => ['quantity' => 2]]])->call($method, $path, $this->delivery() + [
            'user_id' => $owner->id,
            'razorpay_payment_id' => 'payment-test', 'razorpay_order_id' => 'gateway-test', 'razorpay_signature' => 'signature-test',
        ])->assertRedirect('/login');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(19, $product->fresh()->stock);
    }

    public function test_json_requests_cannot_bypass_shopping_authentication(): void
    {
        $product = $this->product();
        $this->getJson('/cart')->assertUnauthorized();
        $this->postJson('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertUnauthorized();
        $this->patchJson('/cart/'.$product->id, ['quantity' => 1])->assertUnauthorized();
        $this->postJson('/checkout', $this->delivery())->assertUnauthorized();
        $this->assertNull(session('cart'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(20, $product->fresh()->stock);
    }

    public function test_customer_can_return_to_cart_after_login_and_manage_items(): void
    {
        $product = $this->product();
        $this->customer();
        $this->get('/cart')->assertRedirect('/login');
        $this->post('/login', ['email' => 'customer@example.com', 'password' => 'StrongPassword123!'])->assertRedirect('/cart');
        $this->from('/products/test-filter')->post('/cart', ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect('/products/test-filter')->assertSessionHasNoErrors();
        $this->get('/cart')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Cart')
            ->has('items', 1)->where('items.0.product.id', $product->id)->where('items.0.quantity', 2)->where('cartCount', 2));
        $this->from('/cart')->patch('/cart/'.$product->id, ['quantity' => 3])->assertRedirect('/cart');
        $this->assertSame(3, session('cart')[$product->id]['quantity']);
        $this->patch('/cart/'.$product->id, ['quantity' => 0])->assertSessionHasNoErrors();
        $this->assertSame([], session('cart'));
        $this->assertSame(20, $product->fresh()->stock);
    }

    public function test_customer_can_register_and_return_to_requested_cart(): void
    {
        $this->get('/cart')->assertRedirect('/login');
        $this->get('/register')->assertOk();
        $this->post('/register', [
            'name' => 'New Customer', 'email' => 'new@example.com',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect('/cart');
        $this->assertAuthenticated();
        $this->get('/cart')->assertOk();
    }

    public function test_two_factor_login_must_finish_before_cart_and_order_access(): void
    {
        $user = $this->customer();
        $product = $this->product();
        $twoFactor = app(TwoFactorAuthentication::class);
        $codes = $twoFactor->recoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $twoFactor->secret(), 'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $twoFactor->hashRecoveryCodes($codes),
        ])->save();
        $this->get('/cart')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123!'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 1])->assertRedirect('/login');
        $this->post('/checkout', $this->delivery())->assertRedirect('/login');
        $this->get('/cart')->assertRedirect('/login');
        $this->assertNull(session('cart'));
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertRedirect('/cart');
        $this->get('/cart')->assertOk();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_authenticated_checkout_creates_order_for_signed_in_customer_only(): void
    {
        $customer = $this->customer();
        $other = $this->customer('other@example.com');
        $product = $this->product();
        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('createOrder')->once()->with(25000, Mockery::type('string'))->andReturn(['id' => 'gateway-test']);
        });
        $this->actingAs($customer)->withSession(['cart' => [$product->id => ['quantity' => 2]]])
            ->get('/checkout')->assertOk();
        $this->post('/checkout', $this->delivery() + ['user_id' => $other->id])->assertSessionHasNoErrors()->assertRedirect();
        $order = Order::sole();
        $this->assertSame($customer->id, $order->user_id);
        $this->assertSame('gateway-test', $order->payment_provider_order_id);
        $this->assertSame(18, $product->fresh()->stock);
        $this->get('/orders/'.$order->id)->assertOk();
    }

    public function test_customer_cannot_view_or_pay_another_customers_order(): void
    {
        $owner = $this->customer();
        $other = $this->customer('other@example.com');
        $product = $this->product();
        $order = app(OrderService::class)->create($owner, $this->delivery(), [$product->id => ['quantity' => 1]]);
        $this->mock(RazorpayService::class, fn ($mock) => $mock->shouldNotReceive('verify'));
        $this->actingAs($other)->get('/orders/'.$order->id)->assertForbidden();
        $this->post('/orders/'.$order->id.'/verify-payment', [
            'razorpay_payment_id' => 'payment-test', 'razorpay_order_id' => 'gateway-test', 'razorpay_signature' => 'signature-test',
        ])->assertForbidden();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_logout_removes_cart_access_and_does_not_pass_cart_to_another_account(): void
    {
        $customer = $this->customer();
        $this->customer('other@example.com');
        $product = $this->product();
        $this->actingAs($customer)->withSession(['cart' => [$product->id => ['quantity' => 2]]])->post('/logout');
        $this->assertGuest();
        $this->assertNull(session('cart'));
        $this->get('/cart')->assertRedirect('/login');
        $this->post('/login', ['email' => 'other@example.com', 'password' => 'StrongPassword123!'])->assertRedirect('/cart');
        $this->get('/cart')->assertInertia(fn (Assert $page) => $page->has('items', 0)->where('cartCount', 0));
    }
}
