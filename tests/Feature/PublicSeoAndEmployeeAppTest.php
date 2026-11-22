<?php

declare(strict_types=1);

test('the landing page carries a description, canonical link and structured data once', function () {
    $this->withoutVite();
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'name="description"'))->toBe(1)
        ->and($html)->toContain('rel="canonical"')
        ->and($html)->toContain('application/ld+json')
        ->and($html)->not->toContain('"offers"');
});

test('login and other private pages are kept out of search, registration and the landing page are not', function () {
    $this->withoutVite();
    expect($this->get('/login')->getContent())->toContain('noindex')
        ->and($this->get('/platform/login')->getContent())->toContain('noindex')
        ->and($this->get('/register')->getContent())->not->toContain('noindex')
        ->and($this->get('/')->getContent())->not->toContain('noindex');
});

test('the sitemap lists only public pages and the robots file hides private areas', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/register')->and($xml)->not->toContain('/login')->and($xml)->not->toContain('platform')->and($xml)->not->toContain('employee');

    $robots = file_get_contents(public_path('robots.txt'));
    expect($robots)->toContain('Disallow: /platform')->and($robots)->toContain('Disallow: /employee');
});

test('the employee web app files ship and use only the existing token API', function () {
    $manifest = json_decode((string) file_get_contents(public_path('employee/manifest.webmanifest')), true);
    expect($manifest['display'])->toBe('standalone')->and($manifest['start_url'])->toBe('/employee/index.html');

    $js = (string) file_get_contents(public_path('employee/app.js'));
    expect($js)->toContain("'/api/v1'")->and($js)->toContain("'check-in'")->and($js)->not->toContain('localStorage.setItem(\'pass');

    expect(file_get_contents(public_path('employee/index.html')))->toContain('noindex');
});
