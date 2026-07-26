<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class Login
{
    /**
     * Handle the login mutation.
     *
     * @param  array{email: string, password: string}  $args
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function __invoke(null $_, array $args): array
    {
        if (! Auth::guard('web')->attempt(['email' => $args['email'], 'password' => $args['password']])) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Regenerate session to prevent fixation
        request()->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // Also create a token just in case mobile apps need it, but SPA will use the cookie.
        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }
}
