<?php

namespace Tests\Feature\GraphQL;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class UserStatsTest extends TestCase
{
    use MakesGraphQLRequests, RefreshDatabase;

    public function test_user_can_query_own_stats()
    {
        $user = User::factory()->create();

        Document::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'ready']);
        Document::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        Conversation::factory()->count(3)->create(['user_id' => $user->id]);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $response = $this->graphQL('
            query {
                userStats {
                    totalDocuments
                    documentsReady
                    totalConversations
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'userStats' => [
                    'totalDocuments' => 3,
                    'documentsReady' => 2,
                    'totalConversations' => 3,
                ],
            ],
        ]);
    }

    public function test_user_stats_do_not_include_other_users_data()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Document::factory()->create(['user_id' => $user->id, 'status' => 'ready']);
        Document::factory()->count(5)->create(['user_id' => $otherUser->id, 'status' => 'ready']);
        Conversation::factory()->count(5)->create(['user_id' => $otherUser->id]);

        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeaders(['Authorization' => "Bearer $token"]);

        $response = $this->graphQL('
            query {
                userStats {
                    totalDocuments
                    documentsReady
                    totalConversations
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'userStats' => [
                    'totalDocuments' => 1,
                    'documentsReady' => 1,
                    'totalConversations' => 0,
                ],
            ],
        ]);
    }

    public function test_user_stats_requires_authentication()
    {
        $response = $this->graphQL('
            query {
                userStats {
                    totalDocuments
                }
            }
        ');

        $this->assertNull($response->json('data.userStats'));
        $this->assertNotEmpty($response->json('errors'));
    }
}
