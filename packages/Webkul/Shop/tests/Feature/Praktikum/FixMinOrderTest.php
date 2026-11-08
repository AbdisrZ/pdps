<?php

/**
 * FASE 5 - Regresi DEF-03: minimum order dengan opsi include_discount_amount
 * mengurangi tax_total (Checkout/src/Cart.php:797-798).
 *
 * Kebutuhan REQ-CO-05: jumlah pembanding minimum order = subtotal
 *   (+ pajak bila include_tax_to_amount) (- diskon bila include_discount_amount).
 *
 * Unit test: Cart::getOrderAmount() dengan model cart in-memory (tanpa HTTP).
 * Nilai yang dipakai bilangan bulat agar tidak bercampur dengan DEF-04 (cast int).
 */

use Webkul\Checkout\Models\Cart as CartModel;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;

uses(PraktikumHelpers::class);

dataset('fx1_min_order', [
    // id,      include_tax, include_discount, sub, tax, discount, expected
    'FX1-01 diskon=1, pajak 10, tanpa diskon -> 100 (defek: 90)' => ['FX1-01', '0', '1', 100, 10, 0, 100],
    'FX1-02 diskon=1, pajak 0, diskon 15 -> 85' => ['FX1-02', '0', '1', 100, 0, 15, 85],
    'FX1-03 pajak=1 + diskon=1, pajak 10, diskon 15 -> 95 (defek: 85)' => ['FX1-03', '1', '1', 100, 10, 15, 95],
    'FX1-04 pajak=1 saja, pajak 10 -> 110' => ['FX1-04', '1', '0', 100, 10, 15, 110],
    'FX1-05 tanpa opsi -> subtotal 100' => ['FX1-05', '0', '0', 100, 10, 15, 100],
]);

it('getOrderAmount sesuai REQ-CO-05 untuk kombinasi opsi pajak/diskon', function (
    string $id, string $includeTax, string $includeDiscount, int $sub, int $tax, int $discount, int $expected
) {
    $this->setConfig('sales.order_settings.minimum_order.include_tax_to_amount', $includeTax);
    $this->setConfig('sales.order_settings.minimum_order.include_discount_amount', $includeDiscount);

    cart()->setCart(new CartModel([
        'sub_total' => $sub,
        'tax_total' => $tax,
        'discount_amount' => $discount,
    ]));

    $actual = cart()->getOrderAmount();

    $this->record($id, null, [
        'include_tax_to_amount' => $includeTax,
        'include_discount_amount' => $includeDiscount,
        'sub_total' => $sub,
        'tax_total' => $tax,
        'discount_amount' => $discount,
        'expected' => $expected,
        'getOrderAmount' => $actual,
    ]);

    expect($actual)->toBe($expected);
})->with('fx1_min_order');
