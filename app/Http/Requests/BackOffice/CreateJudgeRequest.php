<?php

namespace App\Http\Requests\BackOffice;

use App\Http\Requests\Concerns\HasPhoneNumber;
use App\Models\Country;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Organizer creates a judge for a competition (account created if needed).
 */
class CreateJudgeRequest extends FormRequest
{
    use HasPhoneNumber;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            ...$this->phoneRules(),
        ];
    }

    public function country(): Country
    {
        return $this->phoneCountry();
    }
}
