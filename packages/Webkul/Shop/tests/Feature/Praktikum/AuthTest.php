<?php

/**
 * Praktikum - Authentication customer (AU-*). Lihat testing/04-blackbox.md §6.
 * Kebutuhan: REQ-AU-01..04. Aturan: RegistrationRequest.php:28-31, LoginRequest.php:16-17.
 * Nilai aktual: evidence/F4-actual/<ID>.json
 */

use Illuminate\Support\Facades\Hash;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Models\Customer;
use Webkul\Shop\Tests\Feature\Praktikum\Concerns\PraktikumHelpers;

uses(PraktikumHelpers::class);

const PRK_PASS = 'rahasia123';

function prkRegisterPayload(array $override = []): array
{
    return array_merge([
        'first_name' => 'Siti',
        'last_name' => 'Aminah',
        'email' => 'siti.'.uniqid().'@example.com',
        'password' => PRK_PASS,
        'password_confirmation' => PRK_PASS,
    ], $override);
}

function prkCustomerWithPassword($test, array $attr = []): Customer
{
    return $test->makeCustomer(array_merge(['password' => Hash::make(PRK_PASS)], $attr));
}

/*
|--------------------------------------------------------------------------
| Registrasi
|--------------------------------------------------------------------------
*/

it('AU-01 [EP-V] registrasi valid membuat akun dan redirect ke login', function () {
    $data = prkRegisterPayload();

    $r = $this->post(route('shop.customers.register.store'), $data);
    $this->record('AU-01', $r, ['location' => $r->headers->get('Location'), 'akun_dibuat' => Customer::query()->where('email', $data['email'])->exists()]);

    $r->assertRedirect(route('shop.customer.session.index'));
    expect(Customer::query()->where('email', $data['email'])->exists())->toBeTrue();
});

dataset('prk_register_invalid', [
    'AU-02 [BVA] password 5 karakter' => ['AU-02', ['password' => '12345', 'password_confirmation' => '12345'], 'password'],
    'AU-04 [EP-I] konfirmasi password berbeda' => ['AU-04', ['password_confirmation' => 'lainlain1'], 'password'],
    'AU-05 [EP-I] email tidak valid' => ['AU-05', ['email' => 'abc@'], 'email'],
    'AU-07 [EP-I] first_name kosong' => ['AU-07', ['first_name' => ''], 'first_name'],
]);

it('registrasi invalid ditolak 422', function (string $id, array $override, string $field) {
    $data = prkRegisterPayload($override);

    $r = $this->postJson(route('shop.customers.register.store'), $data);
    $this->record($id, $r, ['input' => array_diff_key($override, ['password' => 1, 'password_confirmation' => 1])]);

    $r->assertUnprocessable()->assertJsonValidationErrorFor($field);
    expect(Customer::query()->where('email', $data['email'])->exists())->toBeFalse();
})->with('prk_register_invalid');

it('AU-03 [BVA] password tepat 6 karakter diterima', function () {
    $data = prkRegisterPayload(['password' => '123456', 'password_confirmation' => '123456']);

    $r = $this->post(route('shop.customers.register.store'), $data);
    $this->record('AU-03', $r, ['akun_dibuat' => Customer::query()->where('email', $data['email'])->exists()]);

    $r->assertRedirect(route('shop.customer.session.index'));
    expect(Customer::query()->where('email', $data['email'])->exists())->toBeTrue();
});

it('AU-06 [EP-I] email yang sudah terdaftar di channel yang sama ditolak 422', function () {
    $existing = prkCustomerWithPassword($this);

    $r = $this->postJson(route('shop.customers.register.store'), prkRegisterPayload(['email' => $existing->email]));
    $this->record('AU-06', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('email');
});

/*
|--------------------------------------------------------------------------
| Login web
|--------------------------------------------------------------------------
*/

it('AU-08 [EP-V] login web valid -> terautentikasi, redirect home', function () {
    $c = prkCustomerWithPassword($this);
    $this->actAsGuest();

    $r = $this->post(route('shop.customer.session.create'), ['email' => $c->email, 'password' => PRK_PASS]);
    $this->record('AU-08', $r, ['location' => $r->headers->get('Location'), 'login' => auth('customer')->check()]);

    $r->assertRedirect(route('shop.home.index'));
    $this->assertAuthenticatedAs($c, 'customer');
});

it('AU-09 [EP-I] password salah -> tidak login, flash invalid-credentials', function () {
    $c = prkCustomerWithPassword($this);
    $this->actAsGuest();

    $r = $this->post(route('shop.customer.session.create'), ['email' => $c->email, 'password' => 'salahsalah']);
    $this->record('AU-09', $r, ['flash_error' => session('error'), 'login' => auth('customer')->check()]);

    $this->assertGuest('customer');
    expect(session('error'))->toBe(trans('shop::app.customers.login-form.invalid-credentials'));
});

it('AU-10 [BVA] password login 5 karakter ditolak 422', function () {
    $r = $this->postJson(route('shop.customer.session.create'), ['email' => 'x@example.com', 'password' => '12345']);
    $this->record('AU-10', $r);

    $r->assertUnprocessable()->assertJsonValidationErrorFor('password');
});

it('AU-11 [EP-I] akun nonaktif (status=0) tidak dapat login', function () {
    $c = prkCustomerWithPassword($this, ['status' => 0]);
    $this->actAsGuest();

    $r = $this->post(route('shop.customer.session.create'), ['email' => $c->email, 'password' => PRK_PASS]);
    $this->record('AU-11', $r, ['flash_warning' => session('warning'), 'login' => auth('customer')->check()]);

    $this->assertGuest('customer');
    expect(session('warning'))->toBe(trans('shop::app.customers.login-form.not-activated'));
});

it('AU-12 [EP-I] akun belum terverifikasi tidak dapat login', function () {
    $c = prkCustomerWithPassword($this, ['is_verified' => 0]);
    $this->actAsGuest();

    $r = $this->post(route('shop.customer.session.create'), ['email' => $c->email, 'password' => PRK_PASS]);
    $this->record('AU-12', $r, ['flash_info' => session('info'), 'login' => auth('customer')->check()]);

    $this->assertGuest('customer');
    expect(session('info'))->toBe(trans('shop::app.customers.login-form.verify-first'));
});

/*
|--------------------------------------------------------------------------
| Login API (API/CustomerController.php:18-44)
|--------------------------------------------------------------------------
*/

it('AU-13 [EP-V] API login valid -> 200', function () {
    $c = prkCustomerWithPassword($this);
    $this->actAsGuest();

    $r = $this->postJson(route('shop.api.customers.session.create'), ['email' => $c->email, 'password' => PRK_PASS]);
    $this->record('AU-13', $r);

    $r->assertOk();
});

it('AU-14 [EP-I] API login password salah -> 403', function () {
    $c = prkCustomerWithPassword($this);
    $this->actAsGuest();

    $r = $this->postJson(route('shop.api.customers.session.create'), ['email' => $c->email, 'password' => 'salahsalah']);
    $this->record('AU-14', $r);

    $r->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Nonfungsional keamanan (REQ-AU-03/04 [asumsi])
|--------------------------------------------------------------------------
*/

it('AU-15 [BVA][H15] 10 percobaan login salah berturut-turut memicu rate limit (429)', function () {
    $c = prkCustomerWithPassword($this);
    $this->actAsGuest();

    $statusWeb = [];
    $statusApi = [];

    for ($i = 1; $i <= 10; $i++) {
        $statusWeb[] = $this->post(route('shop.customer.session.create'), ['email' => $c->email, 'password' => 'salah'.$i.'xx'])->status();
        $statusApi[] = $this->postJson(route('shop.api.customers.session.create'), ['email' => $c->email, 'password' => 'salah'.$i.'xx'])->status();
    }

    $this->record('AU-15', null, ['status_web' => $statusWeb, 'status_api' => $statusApi]);

    expect($statusWeb)->toContain(429);
    expect($statusApi)->toContain(429);
});

it('AU-16 [EP-I][H16] customer channel lain ditolak login API, sama seperti login web', function () {
    $other = Channel::factory()->create();
    $c = prkCustomerWithPassword($this, ['channel_id' => $other->id]);
    $this->actAsGuest();

    $web = $this->post(route('shop.customer.session.create'), ['email' => $c->email, 'password' => PRK_PASS]);
    $webLogin = auth('customer')->check();
    $this->actAsGuest();

    $api = $this->postJson(route('shop.api.customers.session.create'), ['email' => $c->email, 'password' => PRK_PASS]);
    $apiLogin = auth('customer')->check();

    $this->record('AU-16', $api, [
        'channel_customer' => $other->code,
        'channel_aktif' => core()->getCurrentChannelCode(),
        'web_login' => $webLogin,
        'api_status' => $api->status(),
        'api_login' => $apiLogin,
    ]);

    expect($webLogin)->toBeFalse();
    expect($apiLogin)->toBeFalse();
    $api->assertForbidden();
});
