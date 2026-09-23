<?php

it('shows the public landing page at the root', function () {
    $this->get('/')->assertOk()->assertSee('se joue en direct.');
});

it('redirects guests from the back-office to the organizer login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders the email login page', function () {
    $this->get(route('login'))->assertOk()->assertSee('name="email"', false)->assertDontSee('name="phone"', false);
});
