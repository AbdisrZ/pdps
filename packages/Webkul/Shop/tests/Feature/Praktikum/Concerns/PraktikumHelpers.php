<?php

namespace Webkul\Shop\Tests\Feature\Praktikum\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Webkul\Checkout\Models\Cart as CartModel;
use Webkul\Core\Models\CoreConfig;
use Webkul\Customer\Contracts\Customer as CustomerContract;
use Webkul\Faker\Helpers\Customer as CustomerFaker;
use Webkul\Faker\Helpers\Product as ProductFaker;
use Webkul\Product\Helpers\Indexers\Inventory as InventoryIndexer;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Sales\Models\Order;

/**
 * Helper bersama untuk test praktikum (folder Praktikum/).
 *
 * Catatan penting: singleton `cart` bertahan antar-request di dalam satu test,
 * jadi setiap pergantian pengguna HARUS lewat actAsCustomer()/actAsGuest()
 * (pola yang sama dengan test bawaan: cart()->setCart(...) setelah login).
 */
trait PraktikumHelpers
{
    /**
     * Buat produk simple aktif (manage_stock=1, guest_checkout=1) dengan stok tertentu.
     *
     * @param  array<string, array>  $attributeValues  override nilai atribut, mis. ['price' => ['float_value' => 100]]
     */
    public function makeProduct(int $stock = 5, array $attributeValues = []): Product
    {
        $product = (new ProductFaker([
            'attribute_value' => $attributeValues,
        ]))
            ->getSimpleProductFactory()
            ->create();

        $this->setStock($product, $stock);

        return $this->freshProduct($product);
    }

    /**
     * Set stok sumber inventory default lalu reindex product_inventory_indices.
     */
    public function setStock(Product $product, int $qty): void
    {
        DB::table('product_inventories')
            ->where('product_id', $product->id)
            ->update(['qty' => $qty]);

        $this->reindexInventory($product);
    }

    public function reindexInventory(Product $product): void
    {
        $products = app(ProductRepository::class)->whereIn('id', [$product->id])->get();

        app(InventoryIndexer::class)->reindexRows($products);
    }

    public function freshProduct(Product $product): Product
    {
        return app(ProductRepository::class)->find($product->id);
    }

    /**
     * Set nilai konfigurasi. Satu baris dengan channel 'default' dan locale 'en'
     * cocok untuk field channel-based, locale-based, maupun global
     * (lihat Core/src/SystemConfig.php:163-194).
     */
    public function setConfig(string $code, mixed $value): void
    {
        CoreConfig::query()->where('code', $code)->delete();

        CoreConfig::query()->create([
            'code' => $code,
            'value' => $value,
            'channel_code' => 'default',
            'locale_code' => 'en',
        ]);
    }

    public function makeCustomer(array $attributes = []): CustomerContract
    {
        return (new CustomerFaker)->factory()->create($attributes);
    }

    /**
     * Login sebagai customer dan sinkronkan singleton cart dengan cart aktif miliknya.
     */
    public function actAsCustomer(CustomerContract $customer): CustomerContract
    {
        $this->actingAs($customer, 'customer');

        cart()->resetCart();

        cart()->initCart($customer);

        return $customer;
    }

    /**
     * Keluar sebagai guest baru tanpa cart.
     */
    public function actAsGuest(): void
    {
        auth()->guard('customer')->forgetUser();

        session()->forget('cart');

        cart()->resetCart();
    }

    public function addToCart(Product $product, int $quantity = 1, array $extra = []): TestResponse
    {
        return $this->postJson(route('shop.api.checkout.cart.store'), array_merge([
            'product_id' => $product->id,
            'quantity' => $quantity,
        ], $extra));
    }

    /**
     * Data alamat billing valid dasar (lihat 04-blackbox.md §4).
     */
    public function billing(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'email' => 'budi.santoso@example.com',
            'address' => ['Jl. Uji Coba No. 1'],
            'city' => 'Bandung',
            'country' => 'ID',
            'state' => 'Jawa Barat',
            'postcode' => '40115',
            'phone' => '081234567890',
            'use_for_shipping' => 1,
        ], $override);
    }

    public function postAddress(array $billing = [], array $extraPayload = []): TestResponse
    {
        return $this->postJson(route('shop.checkout.onepage.addresses.store'), array_merge([
            'billing' => $this->billing($billing),
        ], $extraPayload));
    }

    /**
     * Checkout lengkap lewat API untuk pengguna aktif saat ini; mengembalikan order baru.
     */
    public function placeOrder(Product $product, int $quantity = 1): Order
    {
        $this->addToCart($product, $quantity)->assertOk();

        $this->postAddress()->assertOk();

        $this->postJson(route('shop.checkout.onepage.shipping_methods.store'), [
            'shipping_method' => 'flatrate_flatrate',
        ])->assertOk();

        $this->postJson(route('shop.checkout.onepage.payment_methods.store'), [
            'payment' => ['method' => 'cashondelivery'],
        ])->assertOk();

        $this->postJson(route('shop.checkout.onepage.orders.store'))->assertOk();

        return Order::query()->latest('id')->firstOrFail();
    }

    /**
     * Simpan nilai AKTUAL (status, body, data tambahan) sebagai bukti mentah:
     * evidence/F4-actual/<ID>.json. Dipanggil SEBELUM assertion agar tetap
     * tercatat walaupun test gagal. Run berikutnya menimpa file yang sama;
     * riwayat run disimpan terpisah lewat log JUnit.
     */
    public function record(string $id, ?TestResponse $response = null, array $extra = []): void
    {
        $dir = base_path('lampiran-teknis/evidence/F4-actual');

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $data = ['id' => $id, 'waktu' => now()->toDateTimeString()];

        if ($response) {
            $content = $response->baseResponse->getContent();
            $json = json_decode((string) $content, true);

            $data['status'] = $response->status();
            $data['body'] = is_array($json)
                ? $this->trimBody($json)
                : mb_substr(strip_tags((string) $content), 0, 600);

            if ($response->exception) {
                $data['exception'] = get_class($response->exception).': '.mb_substr($response->exception->getMessage(), 0, 300);
            }
        }

        file_put_contents(
            $dir.DIRECTORY_SEPARATOR.$id.'.json',
            json_encode($data + $extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Ringkas body JSON: buang trace dan data cart yang panjang.
     */
    protected function trimBody(array $json): array
    {
        unset($json['trace']);

        if (isset($json['data']) && is_array($json['data'])) {
            $json['data'] = array_intersect_key($json['data'], array_flip([
                'id', 'items_count', 'items_qty', 'coupon_code', 'grand_total', 'redirect', 'redirect_url', 'message',
            ])) ?: '[dipangkas]';
        }

        return $json;
    }

    public function activeCartOf(CustomerContract $customer): ?CartModel
    {
        return CartModel::query()
            ->where('customer_id', $customer->id)
            ->where('is_active', 1)
            ->first();
    }
}
