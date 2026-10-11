<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The landing page contact form. `website` is a honeypot: people never see it, so a filled one means a bot. */
class ContactRequest extends FormRequest
{
    /** Errors show next to the form, not at the top of the page. */
    protected $redirect = '/#contact';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]*$/'],
            'company' => ['nullable', 'string', 'max:150'],
            'employees' => ['nullable', 'string', 'in:1-50,51-200,201-500,500+'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Please enter a phone number with digits only.',
            'message.min' => 'Please tell us a little more (at least 10 characters).',
        ];
    }
}
