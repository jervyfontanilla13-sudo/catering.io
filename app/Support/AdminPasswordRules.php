<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class AdminPasswordRules
{
    private const MINIMUM_LENGTH = 12;

    public static function rules(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(self::MINIMUM_LENGTH)->letters()->mixedCase()->numbers()->symbols(),
        ];
    }

    public static function minimumLength(): int
    {
        return self::MINIMUM_LENGTH;
    }

    public static function helperText(): string
    {
        return 'At least '.self::MINIMUM_LENGTH.' characters. Include uppercase and lowercase letters, a number, and a symbol.';
    }

    public static function messages(): array
    {
        return [
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
