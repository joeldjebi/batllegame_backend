<?php

namespace App\Http\Requests\BackOffice;

use Illuminate\Foundation\Http\FormRequest;

class CriterionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'max_points' => ['required', 'integer', 'min:1', 'max:100'],
            'weight' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
