<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Commune;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Super-admin: the reference places (country > city > commune) picked in the forms
 * of organizers, competitions and users instead of being typed.
 * A place in use is disabled (hidden from the lists, kept on its records), never deleted.
 */
class LocationController extends Controller
{
    public function index(Request $request): View
    {
        $countries = Country::query()->withCount('cities')->orderByDesc('is_active')->orderBy('position')->orderBy('name')->get();
        $country = $countries->firstWhere('id', $request->integer('country')) ?? $countries->firstWhere('is_active', true) ?? $countries->first();

        $cities = $country
            ? $country->cities()->withCount('communes')->orderBy('position')->orderBy('name')
                ->when($request->query('q'), fn ($q, $search) => $q->whereLike('name', "%{$search}%"))
                ->get()
            : collect();
        $city = $cities->firstWhere('id', $request->integer('city'));

        return view('admin.locations.index', [
            'countries' => $countries,
            'country' => $country,
            'cities' => $cities,
            'city' => $city,
            'communes' => $city ? $city->communes()->orderBy('position')->orderBy('name')->get() : collect(),
            'usage' => $this->usage($cities, $city),
        ]);
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        $country = Country::create($this->countryData($request));

        return redirect()->route('admin.locations.index', ['country' => $country->id])->with('status', "Pays « {$country->name} » ajouté.");
    }

    public function updateCountry(Request $request, Country $country): RedirectResponse
    {
        $data = $this->countryData($request, $country);

        // Phone numbers need at least one country.
        if (! $data['is_active'] && $country->is_active && Country::query()->where('is_active', true)->count() === 1) {
            throw ValidationException::withMessages(['is_active' => 'Au moins un pays doit rester actif (champs téléphone).']);
        }

        $country->update($data);

        return back()->with('status', "Pays « {$country->name} » mis à jour.");
    }

    public function destroyCountry(Country $country): RedirectResponse
    {
        if ($country->users()->exists() || $country->cities()->exists()) {
            return back()->withErrors(['location' => "« {$country->name} » a des villes ou des comptes : désactivez-le plutôt."]);
        }

        $country->delete();

        return redirect()->route('admin.locations.index')->with('status', "Pays « {$country->name} » supprimé.");
    }

    /**
     * One or several cities (one per line).
     */
    public function storeCities(Request $request, Country $country): RedirectResponse
    {
        $names = $this->names($request, 'cities', $country->cities()->pluck('name'));
        $position = (int) $country->cities()->max('position');

        foreach ($names as $name) {
            $country->cities()->create(['name' => $name, 'position' => ++$position]);
        }

        return redirect()->route('admin.locations.index', ['country' => $country->id])
            ->with('status', count($names) === 1 ? "Ville « {$names[0]} » ajoutée." : count($names).' villes ajoutées.');
    }

    public function updateCity(Request $request, City $city): RedirectResponse
    {
        $city->update($this->placeData($request, 'cities', ['country_id' => $city->country_id], $city->id));

        return back()->with('status', "Ville « {$city->name} » mise à jour.");
    }

    public function destroyCity(City $city): RedirectResponse
    {
        $used = $city->usageCount() + $city->communes->sum(fn (Commune $commune) => $commune->usageCount());

        if ($used > 0) {
            return back()->withErrors(['location' => "« {$city->name} » est utilisée ({$used}) : désactivez-la plutôt."]);
        }

        $city->delete();

        return redirect()->route('admin.locations.index', ['country' => $city->country_id])->with('status', "Ville « {$city->name} » supprimée.");
    }

    public function storeCommunes(Request $request, City $city): RedirectResponse
    {
        $names = $this->names($request, 'communes', $city->communes()->pluck('name'));
        $position = (int) $city->communes()->max('position');

        foreach ($names as $name) {
            $city->communes()->create(['name' => $name, 'position' => ++$position]);
        }

        return redirect()->route('admin.locations.index', ['country' => $city->country_id, 'city' => $city->id])
            ->with('status', count($names) === 1 ? "Commune « {$names[0]} » ajoutée." : count($names).' communes ajoutées.');
    }

    public function updateCommune(Request $request, Commune $commune): RedirectResponse
    {
        $commune->update($this->placeData($request, 'communes', ['city_id' => $commune->city_id], $commune->id));

        return back()->with('status', "Commune « {$commune->name} » mise à jour.");
    }

    public function destroyCommune(Commune $commune): RedirectResponse
    {
        if (($used = $commune->usageCount()) > 0) {
            return back()->withErrors(['location' => "« {$commune->name} » est utilisée ({$used}) : désactivez-la plutôt."]);
        }

        $commune->delete();

        return back()->with('status', "Commune « {$commune->name} » supprimée.");
    }

    /**
     * @return array<string, mixed>
     */
    private function countryData(Request $request, ?Country $country = null): array
    {
        // Toggle from the list: only the status.
        if ($country && $request->has('is_active') && ! $request->has('name')) {
            return $request->validate(['is_active' => ['required', 'boolean']]);
        }

        $unique = fn (string $column) => Rule::unique('countries', $column)->ignore($country?->id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $unique('name')],
            'iso2' => ['required', 'string', 'size:2', 'alpha', $unique('iso2')],
            'iso3' => ['required', 'string', 'size:3', 'alpha', $unique('iso3')],
            'dial_code' => ['required', 'string', 'regex:/^\+\d{1,4}$/'],
            'phone_min_length' => ['required', 'integer', 'min:4', 'max:15'],
            'phone_max_length' => ['required', 'integer', 'gte:phone_min_length', 'max:15'],
            'phone_example' => ['nullable', 'string', 'max:20', 'regex:/^\d+$/'],
            'flag' => ['nullable', 'string', 'max:8'],
            'currency_code' => ['nullable', 'string', 'size:3', 'alpha'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ], [
            'dial_code.regex' => 'Indicatif au format +225.',
            'phone_example.regex' => 'Chiffres uniquement, sans indicatif.',
        ], ['iso2' => 'code ISO 2', 'iso3' => 'code ISO 3', 'dial_code' => 'indicatif', 'phone_min_length' => 'longueur minimale', 'phone_max_length' => 'longueur maximale']);

        return [
            ...$data,
            'iso2' => strtoupper($data['iso2']),
            'iso3' => strtoupper($data['iso3']),
            'currency_code' => isset($data['currency_code']) ? strtoupper($data['currency_code']) : null,
            'position' => (int) ($data['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Name, status and position of a city or a commune (unique within its parent).
     *
     * @param  array<string, int>  $parent
     * @return array<string, mixed>
     */
    private function placeData(Request $request, string $table, array $parent, int $id): array
    {
        if ($request->has('is_active') && ! $request->has('name')) {
            return $request->validate(['is_active' => ['required', 'boolean']]);
        }

        $unique = Rule::unique($table, 'name')->ignore($id);
        foreach ($parent as $column => $value) {
            $unique->where($column, $value);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $unique],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], ['name.unique' => 'Ce nom existe déjà.']);

        return ['name' => trim($data['name']), 'position' => (int) ($data['position'] ?? 0), 'is_active' => $request->boolean('is_active')];
    }

    /**
     * Names typed one per line, without duplicates or existing ones.
     *
     * @param  Collection<int, string>  $existing
     * @return list<string>
     */
    private function names(Request $request, string $field, Collection $existing): array
    {
        $request->validate([$field => ['required', 'string', 'max:5000']], [], [$field => $field === 'cities' ? 'villes' : 'communes']);

        $taken = $existing->map(fn (string $name) => mb_strtolower($name));
        $names = collect(preg_split('/\r\n|\r|\n/', (string) $request->input($field)))
            ->map(fn (string $name) => trim(preg_replace('/\s+/', ' ', $name)))
            ->filter(fn (string $name) => $name !== '' && mb_strlen($name) <= 100)
            ->unique(fn (string $name) => mb_strtolower($name))
            ->reject(fn (string $name) => $taken->contains(mb_strtolower($name)))
            ->values()
            ->all();

        if ($names === []) {
            throw ValidationException::withMessages([$field => 'Rien à ajouter : ces noms existent déjà.']);
        }

        return $names;
    }

    /**
     * How many records use each city / commune shown (a used place cannot be deleted).
     *
     * @param  Collection<int, City>  $cities
     * @return array{cities: array<int, int>, communes: array<int, int>}
     */
    private function usage(Collection $cities, ?City $city): array
    {
        $count = fn (string $column, array $ids) => collect(['organizers', 'competitions', 'users'])
            ->flatMap(fn (string $table) => DB::table($table)->whereIn($column, $ids)->groupBy($column)->selectRaw("{$column} as id, count(*) as total")->get())
            ->groupBy('id')
            ->map(fn (Collection $rows) => (int) $rows->sum('total'))
            ->all();

        return [
            'cities' => $count('city_id', $cities->modelKeys()),
            'communes' => $city ? $count('commune_id', $city->communes()->pluck('id')->all()) : [],
        ];
    }
}
