<?php

/**
 * Praktikum - Authorization customer A vs customer B (AZ-*). Lihat testing/04-blackbox.md §1.
 * Kebutuhan: REQ-AZ-01/02/03, REQ-CT-04 (testing/03-test-plan.md §4).
 * Integration test lewat HTTP; nilai aktual: evidence/F4-actual/<ID>.json
 */

use Webkul\Checkout\Models\CartAddress;
use Webkul\Checkout\Models\CartItem;
use Webkul\Customer\Models\CustomerAddress;
use Webkul\Customer\Models\Wishlist;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;

uses(PraktikumHelpers::class);

/**
 * Siapkan A (dengan cart berisi item) dan B (dengan cart sendiri berisi item lain).
 *
 * @return array{0:\Webkul\Customer\Models\Customer,1:\Webkul\Customer\Models\Customer,2:CartItem,3:CartItem}
 */
function prkTwoCustomersWithCarts($test): array
{
    $productA = $test->makeProduct(10);
    $productB = $test->makeProduct(10);

    $a = $test->makeCustomer();
    $b = $test->makeCustomer();

    $test->actAsCustomer($a);
    $test->addToCart($productA, 2)->assertOk();
    $itemA = CartItem::query()->where('product_id', $productA->id)->firstOrFail();

    $test->actAsCustomer($b);
    $test->addToCart($productB, 1)->assertOk();
    $itemB = CartItem::query()->where('product_id', $productB->id)->firstOrFail();

    expect($itemA->cart_id)->not->toBe($itemB->cart_id);

    return [$a, $b, $itemA, $itemB];
}

/**
 * Buat order milik A lewat checkout API + invoice, lalu kembalikan [A, B, order, invoice].
 */
function prkOrderOfA($test): array
{
    $a = $test->makeCustomer();
    $b = $test->makeCustomer();

    $test->actAsCustomer($a);
    $order = $test->placeOrder($test->makeProduct(10), 1);

    $invoice = app(InvoiceRepository::class)->create([
        'order_id' => $order->id,
        'invoice' => ['items' => $order->items->pluck('qty_ordered', 'id')->all()],
    ]);

    expect($order->customer_id)->toBe($a->id);

    return [$a, $b, $order->refresh(), $invoice];
}

/*
|--------------------------------------------------------------------------
| Cart item milik orang lain
|--------------------------------------------------------------------------
*/

it('AZ-01 [EP-I] B tidak dapat menghapus item cart milik A (data A tetap)', function () {
    [$a, $b, $itemA] = prkTwoCustomersWithCarts($this);

    $r = $this->deleteJson(route('shop.api.checkout.cart.destroy'), ['cart_item_id' => $itemA->id]);
    $this->record('AZ-01', $r, ['item_A_masih_ada' => CartItem::query()->whereKey($itemA->id)->exists()]);

    expect(CartItem::query()->whereKey($itemA->id)->exists())->toBeTrue();
    expect(CartItem::query()->find($itemA->id)->quantity)->toBe(2);
});

it('AZ-02 [EP-I][H22] respons hapus item milik A tidak menyatakan "berhasil" (REQ-CT-04)', function () {
    [$a, $b, $itemA] = prkTwoCustomersWithCarts($this);

    $r = $this->deleteJson(route('shop.api.checkout.cart.destroy'), ['cart_item_id' => $itemA->id]);
    $this->record('AZ-02', $r, [
        'pesan_sukses' => trans('shop::app.checkout.cart.success-remove'),
        'item_A_masih_ada' => CartItem::query()->whereKey($itemA->id)->exists(),
    ]);

    expect(CartItem::query()->whereKey($itemA->id)->exists())->toBeTrue();
    expect($r->json('message'))->not->toBe(trans('shop::app.checkout.cart.success-remove'));
});

it('AZ-03 [EP-I] B tidak dapat mengubah qty item milik A', function () {
    [$a, $b, $itemA] = prkTwoCustomersWithCarts($this);

    $r = $this->putJson(route('shop.api.checkout.cart.update'), ['qty' => [$itemA->id => 5]]);
    $this->record('AZ-03', $r, ['qty_item_A' => CartItem::query()->find($itemA->id)?->quantity]);

    expect(CartItem::query()->find($itemA->id)->quantity)->toBe(2);
});

it('AZ-04 [EP-I] B tidak dapat menghapus-terpilih item milik A', function () {
    [$a, $b, $itemA] = prkTwoCustomersWithCarts($this);

    $r = $this->deleteJson(route('shop.api.checkout.cart.destroy_selected'), ['ids' => [$itemA->id]]);
    $this->record('AZ-04', $r, ['item_A_masih_ada' => CartItem::query()->whereKey($itemA->id)->exists()]);

    expect(CartItem::query()->whereKey($itemA->id)->exists())->toBeTrue();
});

it('AZ-05 [EP-I] B tidak dapat memindahkan item A ke wishlist', function () {
    [$a, $b, $itemA] = prkTwoCustomersWithCarts($this);

    $r = $this->postJson(route('shop.api.checkout.cart.move_to_wishlist'), ['ids' => [$itemA->id], 'qty' => [1]]);
    $this->record('AZ-05', $r, [
        'item_A_masih_ada' => CartItem::query()->whereKey($itemA->id)->exists(),
        'wishlist_B_untuk_produk_A' => Wishlist::query()->where('customer_id', $b->id)->where('product_id', $itemA->product_id)->count(),
    ]);

    expect(CartItem::query()->whereKey($itemA->id)->exists())->toBeTrue();
    expect(Wishlist::query()->where('customer_id', $b->id)->where('product_id', $itemA->product_id)->count())->toBe(0);
});

it('AZ-06 [EP-I] guest tanpa cart tidak dapat menghapus item milik A', function () {
    [$a, $b, $itemA] = prkTwoCustomersWithCarts($this);
    $this->actAsGuest();

    $r = $this->deleteJson(route('shop.api.checkout.cart.destroy'), ['cart_item_id' => $itemA->id]);
    $this->record('AZ-06', $r, ['item_A_masih_ada' => CartItem::query()->whereKey($itemA->id)->exists()]);

    expect(CartItem::query()->whereKey($itemA->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Order/invoice milik orang lain (OrderController.php)
|--------------------------------------------------------------------------
*/

it('AZ-07 [EP-V kontrol] A dapat melihat order miliknya sendiri', function () {
    [$a, $b, $order] = prkOrderOfA($this);
    $this->actAsCustomer($a);

    $r = $this->get(route('shop.customers.account.orders.view', $order->id));
    $this->record('AZ-07', $r);

    $r->assertOk();
});

it('AZ-08 [EP-I] B melihat order A -> 404', function () {
    [$a, $b, $order] = prkOrderOfA($this);
    $this->actAsCustomer($b);

    $r = $this->get(route('shop.customers.account.orders.view', $order->id));
    $this->record('AZ-08', $r);

    $r->assertNotFound();
});

it('AZ-09 [EP-I] B reorder order A -> 404, cart B tidak bertambah', function () {
    [$a, $b, $order] = prkOrderOfA($this);
    $this->actAsCustomer($b);

    $r = $this->get(route('shop.customers.account.orders.reorder', $order->id));
    $this->record('AZ-09', $r, ['cart_aktif_B' => $this->activeCartOf($b)?->id]);

    $r->assertNotFound();
    expect($this->activeCartOf($b))->toBeNull();
});

it('AZ-10 [EP-I] B membatalkan order A -> 404, status order tidak berubah', function () {
    [$a, $b, $order] = prkOrderOfA($this);
    $before = $order->status;
    $this->actAsCustomer($b);

    $r = $this->post(route('shop.customers.account.orders.cancel', $order->id));
    $this->record('AZ-10', $r, ['status_sebelum' => $before, 'status_sesudah' => Order::query()->find($order->id)->status]);

    $r->assertNotFound();
    expect(Order::query()->find($order->id)->status)->toBe($before);
});

it('AZ-11 [EP-I] B mencetak invoice A -> 404', function () {
    [$a, $b, $order, $invoice] = prkOrderOfA($this);
    $this->actAsCustomer($b);

    $r = $this->get(route('shop.customers.account.orders.print-invoice', $invoice->id));
    $this->record('AZ-11', $r, ['invoice_id' => $invoice->id, 'invoice_ada' => Invoice::query()->whereKey($invoice->id)->exists()]);

    $r->assertNotFound();
});

it('AZ-12 [EP-I] guest membuka daftar order -> redirect ke login', function () {
    $this->actAsGuest();

    $r = $this->get(route('shop.customers.account.orders.index'));
    $this->record('AZ-12', $r, ['location' => $r->headers->get('Location')]);

    $r->assertRedirect(route('shop.customer.session.index'));
});

/*
|--------------------------------------------------------------------------
| Alamat milik orang lain di checkout (H12, Cart.php:456)
|--------------------------------------------------------------------------
*/

it('AZ-13 [EP-I][H12] B tidak dapat merujuk alamat tersimpan milik A saat checkout (REQ-AZ-03)', function () {
    $a = $this->makeCustomer();
    $b = $this->makeCustomer();
    $addressA = CustomerAddress::factory()->create(['customer_id' => $a->id]);

    $this->actAsCustomer($b);
    $this->addToCart($this->makeProduct(10), 1)->assertOk();

    $r = $this->postAddress(['address_type' => 'customer', 'id' => $addressA->id]);

    $billing = CartAddress::query()
        ->where('customer_id', $b->id)
        ->where('address_type', CartAddress::ADDRESS_TYPE_BILLING)
        ->latest('id')
        ->first();

    $this->record('AZ-13', $r, [
        'alamat_A_id' => $addressA->id,
        'billing_B_parent_address_id' => $billing?->parent_address_id,
    ]);

    $ditolak = $r->status() >= 400 && $r->status() < 500;

    expect($ditolak || $billing?->parent_address_id !== $addressA->id)->toBeTrue();
});

it('AZ-14 [EP-V kontrol H12] B merujuk alamat miliknya sendiri -> 200, parent_address_id = alamat B', function () {
    $b = $this->makeCustomer();
    $addressB = CustomerAddress::factory()->create(['customer_id' => $b->id]);

    $this->actAsCustomer($b);
    $this->addToCart($this->makeProduct(10), 1)->assertOk();

    $r = $this->postAddress(['address_type' => 'customer', 'id' => $addressB->id]);

    $billing = CartAddress::query()
        ->where('customer_id', $b->id)
        ->where('address_type', CartAddress::ADDRESS_TYPE_BILLING)
        ->latest('id')
        ->first();

    $this->record('AZ-14', $r, ['alamat_B_id' => $addressB->id, 'billing_parent_address_id' => $billing?->parent_address_id]);

    $r->assertOk();
    expect($billing->parent_address_id)->toBe($addressB->id);
});
