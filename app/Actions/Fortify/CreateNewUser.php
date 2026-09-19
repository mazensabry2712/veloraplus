<?php

namespace App\Actions\Fortify;

use App\Models\PlatformAccount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): PlatformAccount
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(PlatformAccount::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return PlatformAccount::create([
            'name' => $input['name'],
            'email' => Str::lower($input['email']),
            'password' => $input['password'],
            'status' => 'active',
        ]);
    }
}
