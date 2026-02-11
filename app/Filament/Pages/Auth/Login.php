<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $email = $data['email'] ?? null;

        if ($email) {
            $user = \App\Models\User::where('email', $email)->first();

            if ($user && ! $user->is_active) {
                throw ValidationException::withMessages([
                    'data.email' => 'Akun Anda telah dinonaktifkan. Hubungi Administrator.',
                ]);
            }
        }

        return parent::authenticate();
    }
}
