<?php

namespace App\Http\Requests\BackOffice;

use App\Http\Requests\Concerns\HasLocationInput;
use App\Support\Locations;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizerRequest extends FormRequest
{
    use HasLocationInput;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            ...$this->locationRules(required: Locations::tree() !== []),
            'logo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
