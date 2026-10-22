<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public static function rules(): array
    {
        return [
            'required', 'string', 'max:64', 'confirmed', Password::min(12)->letters()->numbers(),
            function (string $attribute, mixed $value, Closure $fail) {
                if (is_string($value) && (str_contains($value, "\0") ||
                    (config('hashing.driver') === 'bcrypt' && strlen($value) > 72))) {
                    $fail('The password is too long or contains unsupported characters. Please use a different password.');
                }
            },
        ];
    }
}
