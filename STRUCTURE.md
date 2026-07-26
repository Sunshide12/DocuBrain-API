# Backend Architecture Report: DocuBrain-API Workspace

## CORE FRAMEWORK & VERSION
- **Framework:** Laravel `13.8` (Running on PHP `^8.3`)
- **API Paradigm:** Hybrid architecture utilizing both GraphQL and REST.
- **Real-Time Server:** Laravel Reverb (`^1.10`) for native WebSocket broadcasting.

## ROUTING & API STRUCTURE
- **GraphQL Layer:** Managed by Nuwave Lighthouse (`^6.68`).
  - The schema is located at `graphql/schema.graphql`.
  - Used for structured data fetching, pagination (`@paginate`), and real-time operations.
- **REST Layer:** Standard Laravel API routes (`routes/api.php`).
  - Used for specific binary operations, such as file uploads (`DocumentUploadController`) and file streaming/downloads (`DocumentDownloadController`), where REST often outperforms GraphQL.

## USER VALIDATION & ROUTE PROTECTION
- **Authentication Engine:** Laravel Sanctum (`^4.0`) is used for token-based authentication.
- **REST Protection:** Routes in `api.php` are wrapped in the `auth:sanctum` middleware block to ensure only authenticated users can upload or download documents.
- **GraphQL Protection:** Lighthouse utilizes the `@guard(with: ["sanctum"])` directive on Queries and Mutations to securely lock down data fetching and modifications.
- **Rate Limiting:** Identified in GraphQL schemas via the `@throttle(name: "api")` directive, protecting intensive queries (like fetching documents and chunks) from abuse.

## DATABASE, AI & PROCESSING
- **Vector Database:** Uses `pgvector/pgvector` (`^0.2.2`), indicating a PostgreSQL database with Vector embeddings integration. This powers the Retrieval-Augmented Generation (RAG) system for searching documents intelligently.
- **File Processing:** Integrates `spatie/pdf-to-text` (`^1.55`) for server-side text extraction from uploaded PDFs, feeding the vector database pipeline.

## TESTING & DEV ENVIRONMENT
- **Testing:** PHPUnit (`^12.5.12`) combined with Mockery.
- **Code Styling & Tools:** Laravel Pint for code styling, and Laravel Pail/Tinker for debugging and log inspection.
- **GraphQL Interface:** `mll-lab/laravel-graphiql` is installed for in-browser GraphQL playground testing during development.
