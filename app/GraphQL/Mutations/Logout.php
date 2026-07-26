<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\TransientToken;

final class Logout
{
    /**
     * Handle the logout mutation.
     *
     * @param  array<string, mixed>  $args
     * @return array{message: string}
     */
    public function __invoke(null $_, array $args, $context): array
    {
        /** @var Request $request */
        $request = $context->request();

        // Revoke the token that was used to authenticate this request if it's a real token
        $user = $request->user();
        if ($user && $user->currentAccessToken() && ! ($user->currentAccessToken() instanceof TransientToken)) {
            $user->currentAccessToken()->delete();
        }

        // Also ensure web session is logged out if SPA authentication was used
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return [
            'message' => 'Logged out successfully.',
        ];
    }
}
