<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class Register
{
    /**
     * Handle the register mutation.
     *
     * @param  array{name: string, email: string, password: string}  $args
     * @return array{token: string, user: User}
     */
    public function __invoke(null $_, array $args): array
    {
        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => Hash::make($args['password']),
        ]);

        Auth::guard('web')->login($user);
        request()->session()->regenerate();

        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }
}
