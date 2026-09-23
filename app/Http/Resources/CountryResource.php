<?php

namespace App\Http\Resources;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iso2' => $this->iso2,
            'dial_code' => $this->dial_code,
            'flag' => $this->flag,
            'phone_min_length' => $this->phone_min_length,
            'phone_max_length' => $this->phone_max_length,
            'phone_example' => $this->phone_example,
        ];
    }
}
