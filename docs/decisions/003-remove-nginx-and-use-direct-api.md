# 003: Remove Nginx and Use Direct API Connection

## Date
2026-07-17

## Status
Accepted

## Context
Initially, the project used Nginx as a reverse proxy within a Docker environment to route traffic from port 80 to the Laravel application (port 8000), acting as a load balancer and handling potential static files.

However, the planned deployment strategy involves:
1. **Frontend:** Vercel (which handles routing, SSL, edge caching, and static files natively).
2. **Backend:** Likely serverless functions (like `vercel-php`) or a separate PaaS/VPS.

In this architecture, Vercel acts as the proxy for the frontend, making Nginx redundant and adding unnecessary complexity to the local development environment. Locally, Next.js runs its own development server (`npm run dev` on port 3000), and Laravel provides `php artisan serve` (on port 8000). 

## Decision
We decided to completely remove Nginx from the project stack.

### Changes Made:
- Removed the `nginx` service and its Dockerfile/config from `docker-compose.yml`.
- Exposed port `8000:8000` directly on the Laravel `app` service container.
- Updated the frontend HTTP clients (`axios.ts` and `graphql.ts`) to use environment variables (`NEXT_PUBLIC_API_URL`, `NEXT_PUBLIC_GRAPHQL_URL`) with a fallback to `http://localhost:8000`. This ensures seamless local development while supporting Vercel production environments via configured environment variables.
- Kept the Laravel CORS configuration (`config/cors.php`) to allow requests from `http://localhost:3000` to prevent cross-origin issues during local development.

## Consequences
**Positive:**
- Simpler local environment setup.
- Fewer running containers (less resource usage locally).
- Production-ready frontend configuration using dynamic environment variables rather than hardcoded URLs.

**Negative:**
- We lose the ability to simulate a unified domain (like `api.docubrain.local`) on port 80 locally unless configured through `/etc/hosts` and alternative proxying, meaning we rely on distinct ports (`3000` and `8000`) locally.
- Requires explicit CORS configuration in Laravel (which is already correctly configured).
