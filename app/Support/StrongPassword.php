<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class StrongPassword
{
    public static function rule(): Password
    {
        $rule = Password::min(8)
            ->letters()
            ->numbers()
            ->rules(['regex:/[A-Z]/']);

        return app()->environment('testing') ? $rule : $rule->uncompromised();
    }
}
