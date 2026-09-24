<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
use App\Support\Locations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CountryController extends Controller
{
    /**
     * Active countries, used by every phone number input.
     */
    public function index(): AnonymousResourceCollection
    {
        return CountryResource::collection(Country::query()->active()->get());
    }

    /**
     * Places to pick (country > cities > communes), managed by the super-admin.
     */
    public function locations(): JsonResponse
    {
        return response()->json(['data' => Locations::tree()]);
    }
}
