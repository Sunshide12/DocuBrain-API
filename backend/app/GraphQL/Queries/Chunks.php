<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Resolver para la query `chunks(document_id, first, page)`.
 *
 * Devuelve los chunks de un documento específico, paginados y ordenados
 * por chunk_index para preservar el orden original del PDF.
 *
 * SEGURIDAD: Solo devuelve chunks de documentos que pertenecen al usuario
 * autenticado — evita que un usuario acceda a chunks de otro usuario
 * simplemente conociendo el document_id.
 */
final class Chunks
{
    /**
     * @param  null  $root
     * @param  array{document_id: string|int, first?: int, page?: int}  $args
     */
    public function __invoke(null $root, array $args, GraphQLContext $context): array
    {
        /** @var \App\Models\User $user */
        $user       = $context->user();
        $documentId = (int) $args['document_id'];
        $first      = max(1, (int) ($args['first'] ?? 20));
        $page       = max(1, (int) ($args['page'] ?? 1));

        // Verificamos que el documento exista y pertenezca al usuario.
        // abort(403) si no — la excepción la maneja Lighthouse automáticamente.
        $document = $user->documents()->findOrFail($documentId);

        $paginator = DocumentChunk::where('document_id', $document->id)
            ->orderBy('chunk_index')
            ->paginate($first, ['*'], 'page', $page);

        return [
            'data'          => $paginator->items(),
            'paginatorInfo' => [
                'total'        => $paginator->total(),
                'perPage'      => $paginator->perPage(),
                'currentPage'  => $paginator->currentPage(),
                'lastPage'     => $paginator->lastPage(),
                'hasMorePages' => $paginator->hasMorePages(),
            ],
        ];
    }
}
