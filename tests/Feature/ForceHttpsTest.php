<?php

declare(strict_types=1);



it('does not redirect when force https is off', function () {
    config(['app.force_https' => false]);

    $this->get('http://localhost/up')->assertStatus(200);
});

it('redirects plain http web requests to https and keeps path and query', function () {
    config(['app.force_https' => true]);

    $this->get('http://localhost/platform/login?x=1')
        ->assertStatus(301)
        ->assertRedirect('https://localhost/platform/login?x=1');
});

it('redirects non-get requests with 308', function () {
    config(['app.force_https' => true]);

    $this->post('http://localhost/login', [])
        ->assertStatus(308)
        ->assertRedirect('https://localhost/login');
});

it('does not redirect requests that already use https', function () {
    config(['app.force_https' => true]);

    $this->get('https://localhost/platform/login')->assertStatus(200);
});
