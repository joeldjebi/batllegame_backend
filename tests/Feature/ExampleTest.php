<?php

use App\Models\Country;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('renders the login page with the active dial codes', function () {
    Country::factory()->ivoryCoast();

    $this->get(route('login'))->assertOk()->assertSee('+225');
});
