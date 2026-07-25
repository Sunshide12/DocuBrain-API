<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class Register
{
    /**
     * Handle the register mutation.
     *
     * @param  null  $_
     * @param  array{name: string, email: string, password: string}  $args
     * @return array{token: string, user: User}
     */
    public function __invoke(null $_, array $args): array
    {
        $user = User::create([
            'name'     => $args['name'],
            'email'    => $args['email'],
            'password' => Hash::make($args['password']),
        ]);

        \Illuminate\Support\Facades\Auth::guard('web')->login($user);
        request()->session()->regenerate();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user'  => $user,
        ];
    }
}
