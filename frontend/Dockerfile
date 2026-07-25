# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────────────────────
# Next.js 16 (App Router) — multi-stage build using `output: "standalone"`.
# Contexto de build: este directorio (ver docker-compose.yml al lado).
# ─────────────────────────────────────────────────────────────────────────────
FROM node:22-alpine AS base
# Next's precompiled binaries (SWC, sharp) expect a glibc-compatible loader.
RUN apk add --no-cache libc6-compat
ENV NEXT_TELEMETRY_DISABLED=1
WORKDIR /app


# ── deps ────────────────────────────────────────────────────────────────────
# Cached independently of source changes: only re-runs when the lockfile moves.
FROM base AS deps
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci


# ── builder ─────────────────────────────────────────────────────────────────
FROM base AS builder

# NEXT_PUBLIC_* values are inlined into the client bundle at build time, so the
# browser-reachable URLs must be known here — not at container start.
ARG NEXT_PUBLIC_API_URL=http://localhost:8000
ARG NEXT_PUBLIC_GRAPHQL_URL=http://localhost:8000/graphql
ARG NEXT_PUBLIC_REVERB_APP_KEY=docubrain-key
ARG NEXT_PUBLIC_REVERB_HOST=localhost
ARG NEXT_PUBLIC_REVERB_PORT=8080
ARG NEXT_PUBLIC_REVERB_WSS_PORT=8080
ARG NEXT_PUBLIC_REVERB_SCHEME=http
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL \
    NEXT_PUBLIC_GRAPHQL_URL=$NEXT_PUBLIC_GRAPHQL_URL \
    NEXT_PUBLIC_REVERB_APP_KEY=$NEXT_PUBLIC_REVERB_APP_KEY \
    NEXT_PUBLIC_REVERB_HOST=$NEXT_PUBLIC_REVERB_HOST \
    NEXT_PUBLIC_REVERB_PORT=$NEXT_PUBLIC_REVERB_PORT \
    NEXT_PUBLIC_REVERB_WSS_PORT=$NEXT_PUBLIC_REVERB_WSS_PORT \
    NEXT_PUBLIC_REVERB_SCHEME=$NEXT_PUBLIC_REVERB_SCHEME \
    NODE_ENV=production

COPY --from=deps /app/node_modules ./node_modules
COPY . .
RUN npm run build


# ── dev ─────────────────────────────────────────────────────────────────────
# Hot-reload target: `docker compose -f docker-compose.yml -f docker-compose.dev.yml up`
FROM base AS dev
ENV NODE_ENV=development
COPY --from=deps /app/node_modules ./node_modules
COPY . .
EXPOSE 3000
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0", "--port", "3000"]


# ── runner (default / production) ───────────────────────────────────────────
FROM base AS runner
ENV NODE_ENV=production \
    PORT=3000 \
    HOSTNAME=0.0.0.0

RUN addgroup -g 1001 -S nodejs && adduser -u 1001 -S nextjs -G nodejs

# `standalone` ships only the traced runtime files; public/ and .next/static
# are not copied by the tracer and must be placed alongside server.js.
COPY --from=builder --chown=nextjs:nodejs /app/.next/standalone ./
COPY --from=builder --chown=nextjs:nodejs /app/.next/static ./.next/static
COPY --from=builder --chown=nextjs:nodejs /app/public ./public

USER nextjs
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD node -e "fetch('http://127.0.0.1:'+process.env.PORT).then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

CMD ["node", "server.js"]
