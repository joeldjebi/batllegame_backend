<?php

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('renders the email login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('name="email"', false)->assertDontSee('name="phone"', false);
});
