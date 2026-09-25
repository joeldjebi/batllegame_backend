<?php

require_once __DIR__.'/../Preselection/helpers.php';

it('revalidates API reads with an ETag and answers 304 when nothing changed', function () {
    ['competition' => $competition] = competitionWithPreselection(1);

    $first = $this->getJson("/api/competitions/{$competition->slug}")->assertOk();
    $etag = $first->headers->get('ETag');

    expect($etag)->toStartWith('W/"')
        ->and($first->headers->get('Cache-Control'))->toContain('private')->toContain('no-cache')
        ->and($first->headers->get('Vary'))->toContain('Authorization');

    $cached = $this->getJson("/api/competitions/{$competition->slug}", ['If-None-Match' => $etag])->assertStatus(304);
    expect($cached->getContent())->toBe('');

    $competition->update(['name' => 'Nouveau nom']);
    $this->getJson("/api/competitions/{$competition->slug}", ['If-None-Match' => $etag])->assertOk()->assertJsonPath('data.name', 'Nouveau nom');
});

it('never caches writes nor errors', function () {
    $this->postJson('/api/auth/login', [])->assertStatus(422)->assertHeaderMissing('ETag');
    $this->getJson('/api/competitions/inconnue')->assertNotFound()->assertHeaderMissing('ETag');
});
