<?php

namespace Tests\Feature\GraphQL;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Tests\TestCase;

class IntegrationFlowTest extends TestCase
{
    use RefreshDatabase;
    use MakesGraphQLRequests;

    /**
     * Test de integración: login -> upload -> documento aparece en lista con status ready.
     * Dado que QUEUE_CONNECTION=sync en testing, el trabajo se ejecuta sincrónicamente.
     */
    public function test_user_can_register_upload_and_document_becomes_ready(): void
    {
        Storage::fake('local');
        
        // Forzamos la cola a sync porque docker-compose inyecta QUEUE_CONNECTION=redis
        // y phpunit.xml a veces no logra sobreescribirlo a nivel de sistema.
        config(['queue.default' => 'sync']);

        // 1. Registrar un usuario
        $registerMutation = '
            mutation Register($input: RegisterInput!) {
                register(input: $input) {
                    token
                    user {
                        id
                        email
                    }
                }
            }
        ';

        $registerResponse = $this->graphQL($registerMutation, [
            'input' => [
                'name' => 'Test User',
                'email' => 'integration@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]
        ]);

        $token = $registerResponse->json('data.register.token');
        $this->assertNotEmpty($token);

        // Configurar el header de autorización para las siguientes peticiones
        $this->withHeaders([
            'Authorization' => "Bearer $token",
        ]);

        // 2. Subir un PDF
        $file = UploadedFile::fake()->createWithContent('integration.pdf', '%PDF-1.4 Fake Content');

        $uploadMutation = '
            mutation UploadDocument($file: Upload!) {
                uploadDocument(file: $file, title: "Integration Document") {
                    id
                    status
                }
            }
        ';

        $operations = [
            'query'     => $uploadMutation,
            'variables' => [
                'file' => null,
            ],
        ];

        $map = [
            '0' => ['variables.file'],
        ];

        $files = [
            '0' => $file,
        ];

        $uploadResponse = $this->multipartGraphQL($operations, $map, $files);

        $uploadResponse->assertJsonStructure([
            'data' => [
                'uploadDocument' => [
                    'id',
                    'status',
                ],
            ],
        ]);

        $documentId = $uploadResponse->json('data.uploadDocument.id');
        $this->assertNotNull($documentId);

        // 3. Verificar que el documento existe y, dado que la cola es síncrona en testing, ya está en estado 'ready'.
        $document = Document::find($documentId);
        
        $this->assertNotNull($document);
        $this->assertEquals('ready', $document->status);

        // 4. Verificar que aparece en la consulta de documentos
        $queryDocuments = '
            query {
                documents {
                    data {
                        id
                        title
                        status
                    }
                    paginatorInfo {
                        total
                    }
                }
            }
        ';

        $documentsResponse = $this->graphQL($queryDocuments);
        $documentsResponse->assertJsonPath('data.documents.paginatorInfo.total', 1);
        $documentsResponse->assertJsonPath('data.documents.data.0.id', (string) $documentId);
        $documentsResponse->assertJsonPath('data.documents.data.0.status', 'ready');
    }
}
