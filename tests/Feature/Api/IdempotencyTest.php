<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Preselection/helpers.php';
require_once __DIR__.'/../Stages/helpers.php';

beforeEach(function () {
    Storage::fake('public');
    config(['media.disk' => 'public']);
    fakeMediaDuration(60);
});

it('executes a queued write once and replays the stored response', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(2);
    [$first, $second] = $artists->map(fn ($artist) => preselectionEntry($artist))->all();
    $fan = User::factory()->create(['phone_verified_at' => now()]);
    $like = fn ($entry, string $key) => $this->actingAs($fan, 'sanctum')
        ->postJson("/api/competitions/{$competition->slug}/preselection/entries/{$entry->id}/like", [], ['Idempotency-Key' => $key]);

    $original = $like($first, 'offline-like-0001')->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
    $replay = $like($first, 'offline-like-0001')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->json())->toBe($original->json())
        ->and($first->fresh()->likes_count)->toBe(1);

    // Same key for another request: refused, nothing happens.
    $like($second, 'offline-like-0001')->assertStatus(422);
    expect($second->fresh()->likes_count)->toBe(0);

    // Keys are per user.
    $other = User::factory()->create(['phone_verified_at' => now()]);
    $this->actingAs($other, 'sanctum')->postJson("/api/competitions/{$competition->slug}/preselection/entries/{$second->id}/like", [], ['Idempotency-Key' => 'offline-like-0001'])
        ->assertCreated()->assertHeaderMissing('Idempotent-Replayed');
});

it('replays a business refusal the same way and ignores reads and requests without a key', function () {
    ['competition' => $competition, 'artists' => $artists] = competitionWithPreselection(1);
    $entry = preselectionEntry($artists[0]);
    $unverified = User::factory()->create(['phone_verified_at' => null]);
    $like = fn () => $this->actingAs($unverified, 'sanctum')
        ->postJson("/api/competitions/{$competition->slug}/preselection/entries/{$entry->id}/like", [], ['Idempotency-Key' => 'refused-like-0001']);

    $status = $like()->status();
    expect($status)->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    $like()->assertStatus($status)->assertHeader('Idempotent-Replayed', 'true');

    $this->getJson('/api/feed', ['Idempotency-Key' => 'read-0000001'])->assertOk()->assertHeaderMissing('Idempotent-Replayed');
    $this->postJson('/api/auth/login', [], ['Idempotency-Key' => 'bad key!'])->assertStatus(400);
});
