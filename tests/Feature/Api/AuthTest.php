<?php

use App\Models\Country;
use App\Models\User;
use App\Services\Sms\SmsSender;

beforeEach(function () {
    $this->country = Country::factory()->ivoryCoast();
    $this->sms = Mockery::spy(SmsSender::class);
    $this->app->instance(SmsSender::class, $this->sms);
});

it('lists only active countries', function () {
    Country::factory()->create(['iso2' => 'SN', 'iso3' => 'SEN', 'dial_code' => '+221', 'is_active' => false]);

    $this->getJson('/api/countries')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.dial_code', '+225');
});

it('registers with a phone number and password and sends a verification code', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Awa',
        'country_id' => $this->country->id,
        'phone' => '07 01 02 03 04',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])
        ->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'phone']])
        ->assertJsonPath('user.phone', '+2250701020304')
        ->assertJsonPath('user.phone_verified', false);

    $this->sms->shouldHaveReceived('send')->once();
});

it('validates the phone length for the selected country', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Awa',
        'country_id' => $this->country->id,
        'phone' => '0701',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertJsonValidationErrors('phone');
});

it('refuses an inactive country', function () {
    $senegal = Country::factory()->create(['iso2' => 'SN', 'iso3' => 'SEN', 'dial_code' => '+221', 'is_active' => false]);

    $this->postJson('/api/auth/login', ['country_id' => $senegal->id, 'phone' => '771234567', 'password' => 'x'])
        ->assertJsonValidationErrors('country_id');
});

it('logs in with phone and password', function () {
    User::factory()->create(['phone' => '+2250701020304']);

    $this->postJson('/api/auth/login', ['country_id' => $this->country->id, 'phone' => '0701020304', 'password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['token']);

    $this->postJson('/api/auth/login', ['country_id' => $this->country->id, 'phone' => '0701020304', 'password' => 'wrong'])
        ->assertJsonValidationErrors('phone');
});

it('verifies the phone with the code sent by SMS', function () {
    $user = User::factory()->unverified()->create();
    $code = null;
    $this->sms->shouldReceive('send')->andReturnUsing(function ($phone, $message) use (&$code) {
        preg_match('/\d{6}/', $message, $m);
        $code = $m[0];
    });

    $this->actingAs($user)->postJson('/api/auth/phone/send-code')->assertAccepted();
    $this->actingAs($user)->postJson('/api/auth/phone/verify', ['code' => '000000'])->assertJsonValidationErrors('code');
    $this->actingAs($user)->postJson('/api/auth/phone/verify', ['code' => $code])->assertOk();

    expect($user->fresh()->hasVerifiedPhone())->toBeTrue();
});
