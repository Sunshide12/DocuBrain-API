---
name: clean-architecture-backend
description: Clean architecture rules, modularity, SOLID principles, and scalability guidance for the Laravel backend.
---

# Scalable Architecture & Best Practices Guide (Backend)

When acting as a senior software engineer on the Backend (DocuBrain-API), always apply these architectural principles. The goal is to keep the code highly maintainable, scalable, and testable.

## 1. Layers & Separation of Concerns
- **Ultra-thin Controllers / Resolvers (GraphQL)**: Lighthouse mutations and queries must **never** contain business logic. Their only job is: receive arguments, call a Service or Action, and return the result.
- **Logic lives in Services (`app/Services/`)**: All business rules, external API calls, and complex processing must be encapsulated in Service classes.
- **Single Responsibility Principle (SRP)**: A service should do one thing. If `DocumentProcessorService` extracts text, chunks it, and generates embeddings, split it into 3 smaller services orchestrated by a main service.

## 2. Dependency Injection & Contracts (SOLID)
- **Depend on abstractions, not implementations**: Before building a service that talks to an LLM, create an interface `AnswerGeneratorInterface` in `app/Services/Contracts/`. Inject the interface into constructors, never the concrete class (`OpenRouterAnswerGenerator`).
- Use Laravel's `ServiceProvider` to bind interfaces to implementations.

## 3. Complex Data Handling (DTOs)
- **No excessive associative arrays**: When passing a structured data set between a Controller and a Service, create a **Data Transfer Object (DTO)** in `app/DTOs/` using PHP 8.2+ `readonly` classes.
- This guarantees autocompletion and avoids "undefined array key" errors.

## 4. Strict Typing
- Use `declare(strict_types=1);` wherever possible.
- **Always** define return types (e.g. `public function process(): void`) and argument types.
- Use modern PHP 8+ features (constructor property promotion, match expressions, nullsafe operator `?->`).

## 5. Models (Eloquent) & Database
- **Skinny Models**: Models should contain only relationships (`hasMany`, `belongsTo`), `casts`, `fillable`/`guarded`, and search `scopes`. Do not put validation or heavy processing logic inside models.
- Avoid N+1 queries. If calling relationships inside a loop, use `with()` or `load()`. In GraphQL, rely on Lighthouse's Dataloader/batching.

## 6. Testing & Reliability
- Write code designed to be easily mockable — this is why we use dependency injection.
- Catch exceptions and transform them into clean, user-friendly GraphQL errors. Never expose database stack traces in production.

---
Checkpoint: are resolvers empty of business logic, and does every method have an explicit return type?
