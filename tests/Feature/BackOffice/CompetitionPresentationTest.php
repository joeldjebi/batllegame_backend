<?php

use App\Enums\OrganizerRole;
use App\Models\Competition;
use App\Models\Organizer;
use App\Models\User;
use App\Support\RichText;

beforeEach(function () {
    $this->organizer = Organizer::factory()->create();
    $this->owner = User::factory()->create();
    $this->organizer->users()->attach($this->owner, ['role' => OrganizerRole::Owner]);
    $this->competition = Competition::factory()->for($this->organizer)->draft()->create(['description' => null, 'prizes' => null]);
});

it('saves a sanitized rich description and the ordered rewards', function () {
    $this->actingAs($this->owner, 'web')
        ->put(route('organizers.competitions.update', [$this->organizer, $this->competition]), [
            'description' => '<div><strong>Grande</strong> finale <a href="https://ex.com" onclick="x()">ici</a><script>alert(1)</script></div><ul><li>Règle 1</li></ul>',
            'prizes' => [
                ['rank' => '', 'reward' => '500 000 XOF'],
                ['rank' => '', 'reward' => ''],
                ['rank' => 'Prix du public', 'reward' => 'Un clip vidéo'],
            ],
        ])
        ->assertSessionHasNoErrors();

    $competition = $this->competition->fresh();

    expect($competition->description)
        ->toContain('<strong>Grande</strong>')
        ->toContain('<li>Règle 1</li>')
        ->toContain('href="https://ex.com"')
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->and($competition->prizeList())->toBe([
            ['rank' => '1er prix', 'reward' => '500 000 XOF'],
            ['rank' => 'Prix du public', 'reward' => 'Un clip vidéo'],
        ]);
});

it('drops javascript links and empty rich text', function () {
    expect(RichText::sanitize('<a href="javascript:alert(1)">x</a>'))->not->toContain('javascript')
        ->and(RichText::sanitize('<div><br></div>'))->toBeNull()
        ->and(RichText::excerpt('<div>Hello <strong>world</strong></div><ul><li>a</li></ul>'))->toBe('Hello world a');
});

it('refuses to open registrations without description and rewards', function () {
    $this->actingAs($this->owner, 'web')
        ->patch(route('organizers.competitions.status', [$this->organizer, $this->competition]), ['status' => 'inscriptions'])
        ->assertSessionHasErrors('flow');

    expect($this->competition->fresh()->status->value)->toBe('brouillon');

    $this->competition->update(['description' => '<div>Battle</div>', 'prizes' => [['rank' => '1er prix', 'reward' => 'Trophée']]]);

    $this->actingAs($this->owner, 'web')
        ->patch(route('organizers.competitions.status', [$this->organizer, $this->competition]), ['status' => 'inscriptions']);

    expect($this->competition->fresh()->status->value)->toBe('inscriptions');
});

it('shows the presentation to the public and through the API', function () {
    $competition = Competition::factory()->for($this->organizer)->create();

    $this->get(route('fan.competitions.show', $competition))
        ->assertOk()
        ->assertSee('À gagner')
        ->assertSee('500 000 XOF et un enregistrement studio')
        ->assertSee('La plus grande scène de battle', false);

    $this->getJson("/api/competitions/{$competition->slug}")
        ->assertOk()
        ->assertJsonPath('data.prizes.0.rank', '1er prix');
});

it('renders the editor in the competition settings', function () {
    $this->actingAs($this->owner, 'web')
        ->get(route('organizers.competitions.show', [$this->organizer, $this->competition]))
        ->assertOk()
        ->assertSee('<trix-editor', false)
        ->assertSee('Ajouter une récompense')
        ->assertDontSee('@js(', false);
});

it('puts Accueil first in the mobile tab bar and highlights the current tab', function () {
    $artist = User::factory()->create();
    $tabs = fn (string $html) => [
        preg_match_all('/<nav[^>]*aria-label="Navigation".*?<\/nav>/s', $html, $m) ? $m[0][0] : '',
    ][0];
    $activeLabel = function (string $nav): ?string {
        preg_match('/aria-current="page".*?<\/span>\s*([^<]+?)\s*<\/a>/s', $nav, $m);

        return $m[1] ?? null;
    };

    $nav = $tabs($this->actingAs($artist, 'member')->get(route('artist.dashboard'))->getContent());
    expect(strpos($nav, 'Accueil'))->toBeLessThan(strpos($nav, 'Voter'))
        ->and($activeLabel($nav))->toBe('Mon espace');

    $competition = Competition::factory()->for($this->organizer)->create();
    expect($activeLabel($tabs($this->actingAs($artist, 'member')->get(route('fan.competitions.show', $competition))->getContent())))->toBe('Voter')
        ->and($activeLabel($tabs($this->actingAs($artist, 'member')->get(route('home'))->getContent())))->toBe('Accueil');
});
