<?php

namespace Tests\Feature\GraphQL;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    use MakesGraphQLRequests;

    /**
     * Test user registration.
     */
    public function test_user_can_register(): void
    {
        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            mutation {
                register(input: {
                    name: "Test User"
                    email: "test@example.com"
                    password: "password123"
                    password_confirmation: "password123"
                }) {
                    token
                    user {
                        name
                        email
                    }
                }
            }
        '
        );

        $response->assertJsonStructure([
            'data' => [
                'register' => [
                    'token',
                    'user' => [
                        'name',
                        'email',
                    ],
                ],
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    /**
     * Test user login.
     */
    public function test_user_can_login(): void
    {
        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            mutation {
                login(input: {
                    email: "existing@example.com"
                    password: "password123"
                }) {
                    token
                    user {
                        name
                        email
                    }
                }
            }
        '
        );

        $response->assertJsonStructure([
            'data' => [
                'login' => [
                    'token',
                    'user' => [
                        'name',
                        'email',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test user me query
     */

    public function test_user_can_me(): void
    {
        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            mutation {
                login(input: {
                    email: "existing@example.com"
                    password: "password123"
                }) {
                    token
                    user {
                        name
                        email
                    }
                }
            }
        '
        );

        $token = $response->json('data.login.token');
        $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            query {
                me {
                id
                    name
                    email
                    created_at
                }
            }
        '
        );
        $response->assertJsonStructure([
            'data' => [
                'me' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                ],
            ],
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            mutation {
                login(input: {
                    email: "existing@example.com"
                    password: "password123"
                }) {
                    token
                    user {
                        name
                        email
                    }
                }
            }
        '
        );

        $token = $response->json('data.login.token');
        $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);

        $response = $this->graphQL(
            /** @lang GraphQL */
            '
            mutation {
                logout {
                    message
                }
            }
        '
        );
        $response->assertJsonStructure([
            'data' => [
                'logout' => [
                    'message',
                ],
            ],
        ]);
        $response->assertJsonPath('data.logout.message', 'Logged out successfully.');
    }
}
