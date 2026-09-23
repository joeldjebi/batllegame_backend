<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryResource;
use App\Models\Country;
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
}
