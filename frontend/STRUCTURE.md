# Frontend Structure Documentation

## Stack

- **Next.js**: `16.2.10` with App Router (confirmed via `src/app/` directory structure)
- **React**: `19.2.4`
- **TypeScript**: `5.x` with strict mode enabled (`tsconfig.json` line 7)
  - Path alias: `@/*` → `./src/*` (line 22)
  - Target: ES2017, JSX transform: react-jsx
- **Tailwind CSS**: `4.x` with PostCSS (`@tailwindcss/postcss` v4)
  - No `tailwind.config.js/ts/mjs` found — likely using Tailwind v4's built-in defaults or CSS-based config
- **UI Components (shadcn/ui)**: Present components confirmed via `src/components/ui/`:
  - `button.tsx`, `card.tsx`, `form.tsx`, `input.tsx`, `label.tsx`
  - `progress.tsx`, `scroll-area.tsx`, `sheet.tsx`, `sonner.tsx`
  - Uses `class-variance-authority` for variants, `lucide-react` for icons
- **State Management**: Zustand `5.0.14`
  - Store: `src/stores/auth.ts` (manages `User` object: `{id, name, email}` + `isAuthenticated` boolean)
- **Data Fetching**: @tanstack/react-query `5.101.2`
  - Provider: `src/app/providers.tsx` wraps app with `QueryClientProvider`
  - Default config: 60s staleTime, retry once, no refetch on window focus (lines 11-15)
- **Form Handling**: react-hook-form `7.81.0` + @hookform/resolvers `5.4.0` + Zod `4.4.3`
  - Example: `src/app/login/page.tsx` lines 26-29 (Zod schema) + line 47 (`zodResolver`)
- **Real-time Client**: Laravel Echo `2.4.0` + Pusher.js `8.5.0` (Reverb broadcaster)
  - Setup: `src/lib/echo.ts`
  - Broadcaster: "reverb", key: `NEXT_PUBLIC_REVERB_APP_KEY` (default: "docubrain-key")
  - WS config: `localhost:8080`, no TLS
  - Custom authorizer posts to `/broadcasting/auth` via Axios (lines 20-35)
- **HTTP Clients**:
  - **Axios**: `src/lib/axios.ts` for REST (file uploads, CSRF cookie fetch)
  - **GraphQL**: `graphql-request` `7.4.0` via `src/lib/graphql.ts` for queries/mutations
- **Other**: framer-motion `12.42.2` (animations), next-themes `0.4.6` (not actively used, hardcoded dark theme in `layout.tsx` line 31)

## Route Protection

**Middleware**: `src/middleware.ts` (lines 1-40)

### What it checks:
1. **Session cookie presence**: Looks for ANY cookie ending in `_session` or `-session` (lines 18-19)
   - Does NOT validate cookie contents or expiry — only checks existence
2. **Special param handling**: `?clearSession=true` deletes all session cookies and redirects to clean URL (lines 7-16)

### Matchers:
Protected routes (line 39): `/dashboard/:path*`, `/chat/:path*`, `/login`, `/register`

### Logic:
- **Auth routes** (`/login`, `/register`): If session exists → redirect to `/dashboard` (lines 24-28)
- **Protected routes** (`/dashboard/*`, `/chat/*`): If NO session → redirect to `/login` (lines 31-33)
- **Unmatched routes**: No middleware runs (public by default)

### Session read pattern in components:
**Client Components only** (all pages are `"use client"`). No Server Component session reads exist.

1. **Initial auth check**: On protected pages (e.g. `src/app/dashboard/page.tsx`):
   - Fetch user via GraphQL `me` query using React Query (lines 61-65)
   - Store result in Zustand: `useAuthStore((state) => state.setUser)` (line 53, 69)
   - On error: clear user, redirect to `/login?clearSession=true` (lines 71-75)

2. **Accessing logged-in user**:
   ```typescript
   import { useAuthStore } from "@/stores/auth";
   const user = useAuthStore((state) => state.user);
   ```
   - Used in: `src/app/dashboard/page.tsx` line 54, `src/app/chat/[id]/page.tsx` line 54
   - Returns `User | null` where `User = { id: string; name: string; email: string; }`

3. **GraphQL client**: `src/lib/graphql.ts` (GraphQLClient from `graphql-request`)
   - Base URL: `process.env.NEXT_PUBLIC_GRAPHQL_URL` or `http://localhost:8000/graphql` (line 3)
   - Custom fetch wrapper adds `X-XSRF-TOKEN` from cookie + `credentials: 'include'` for Sanctum (lines 4-23)

## Current Dashboard Implementation

**Main page**: `src/app/dashboard/page.tsx` (278 lines, all client-side)

### Parts breakdown:

1. **User session fetch** (lines 61-76):
   - GraphQL `Me` query (lines 18-26), stores user in Zustand
   - Redirects to login if session expired

2. **Documents list fetch** (lines 79-83):
   - GraphQL `GetDocuments` query (lines 28-40)
   - Returns array of `{ id, title, original_name, status, created_at }`

3. **Real-time document status updates** (lines 85-144):
   - Subscribes to `App.Models.User.${user.id}` private channel via Echo
   - Listens for `.DocumentProgressUpdated` event
   - Optimistically updates React Query cache when status changes (lines 102-119)
   - Shows toast on `ready` or `failed` status (lines 121-125)

4. **Upload UI** (lines 154-185):
   - **Desktop**: Inline `<Card>` with dashed border at top of page (lines 224-240)
     - Component: `<UploadPanel>` from `src/components/dashboard/upload-panel.tsx`
   - **Mobile**: Floating action button (FAB) bottom-right (lines 269-274)
     - Component: `<UploadFab>` from `src/components/dashboard/upload-fab.tsx`
     - Opens bottom sheet with same `<UploadPanel>` inside
   - Upload handler: Axios POST to `/api/documents/upload` with `multipart/form-data` (lines 165-175)
   - Progress tracking: `onUploadProgress` callback updates `uploadProgress` state (lines 169-173)
   - Uses `useRef<HTMLInputElement>` for file input (line 56)

5. **Documents grid display** (lines 242-267):
   - Empty state: dashed border card with "No documents yet" (lines 251-253)
   - Grid: `sm:grid-cols-2 lg:grid-cols-3` (line 255)
   - Component: `<DocumentCard>` from `src/components/dashboard/document-card.tsx` (lines 257-262)
     - Receives `doc` object, `onSelect` callback, `isChatPending` flag
     - Shows `<StatusBadge>` (from `src/components/dashboard/status-badge.tsx`)
     - "Chat" button disabled unless `status === 'ready'`

6. **Chat navigation** (lines 187-207):
   - Click handler validates `status === 'ready'` (lines 201-206)
   - Creates conversation via GraphQL `createConversation` mutation (lines 187-199)
   - Navigates to `/chat/${conversationId}` (line 196)

7. **Header** (lines 215-220):
   - Component: `<DashboardHeader>` from `src/components/dashboard/dashboard-header.tsx`
   - Shows username, email, logout button
   - Mobile: renders `<MobileDrawer>` (from `src/components/dashboard/mobile-drawer.tsx`)

8. **Logout** (lines 146-152):
   - GraphQL `logout` mutation (lines 42-48)
   - Clears Zustand user state, redirects to `/login?clearSession=true`

### All files involved:
- **Page**: `src/app/dashboard/page.tsx`
- **Components**:
  - `src/components/dashboard/dashboard-header.tsx`
  - `src/components/dashboard/upload-panel.tsx`
  - `src/components/dashboard/upload-fab.tsx`
  - `src/components/dashboard/document-card.tsx`
  - `src/components/dashboard/status-badge.tsx`
  - `src/components/dashboard/mobile-drawer.tsx`
- **UI primitives**: `src/components/ui/{card,button,progress,sheet}.tsx`
- **Lib**: `src/lib/{graphql,axios,echo}.ts`, `src/stores/auth.ts`

**Current state**: Upload and documents list are already **well-separated into reusable components**. Not inline in page file.

## API Client Usage

### REST API (Axios)
**Config**: `src/lib/axios.ts`
- Base URL: `process.env.NEXT_PUBLIC_API_URL` or `http://localhost:8000` (line 4)
- Headers: `X-Requested-With: XMLHttpRequest`, `Accept: application/json` (lines 6-7)
- Credentials: `withCredentials: true` (Sanctum HttpOnly cookies, line 9)
- Request interceptor: Extracts `XSRF-TOKEN` cookie, adds as `X-XSRF-TOKEN` header (lines 12-20)

**Usage**:
```typescript
import api from "@/lib/axios";

// CSRF cookie fetch (before login)
await api.get("/sanctum/csrf-cookie");

// File upload
await api.post("/api/documents/upload", formData, {
  headers: { "Content-Type": "multipart/form-data" },
  onUploadProgress: (e) => { /* ... */ }
});
```

**Real examples**:
- `src/app/login/page.tsx` line 58: CSRF fetch
- `src/app/dashboard/page.tsx` line 165: Document upload
- `src/lib/echo.ts` line 23: Broadcasting auth via `api.post('/broadcasting/auth', ...)`

### GraphQL API (graphql-request)
**Config**: `src/lib/graphql.ts`
- Base URL: `process.env.NEXT_PUBLIC_GRAPHQL_URL` or `http://localhost:8000/graphql` (line 3)
- Custom fetch wrapper (lines 4-23):
  - Adds `X-XSRF-TOKEN` from cookie (lines 9-12)
  - Includes credentials (line 19)

**Usage with React Query**:
```typescript
import { useQuery, useMutation } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";

// Query
const { data } = useQuery({
  queryKey: ["documents"],
  queryFn: () => graphqlClient.request(DOCUMENTS_QUERY),
});

// Mutation
const loginMutation = useMutation({
  mutationFn: (values) => graphqlClient.request(LOGIN_MUTATION, { input: values }),
  onSuccess: (data) => { /* ... */ }
});
```

**Real examples**:
- `src/app/login/page.tsx` lines 55-74: Login mutation
- `src/app/dashboard/page.tsx` lines 61-83: `me` + `documents` queries
- `src/app/chat/[id]/page.tsx` lines 60-65: Conversation query with 3s polling (`refetchInterval: 3000` line 65)

### Environment variables needed:
From `.env.local` and code:
- `NEXT_PUBLIC_API_URL` (default: `http://localhost:8000`)
- `NEXT_PUBLIC_GRAPHQL_URL` (default: `http://localhost:8000/graphql`)
- `NEXT_PUBLIC_REVERB_APP_KEY` (default: `docubrain-key`)

**Note**: No `NEXT_PUBLIC_REVERB_HOST` variable currently used — hardcoded `localhost:8080` in `src/lib/echo.ts` lines 15-17.

## Folder Conventions

```
src/
├── app/                    # Next.js App Router pages
│   ├── chat/[id]/          # Dynamic route for conversation view
│   │   └── page.tsx        # Chat page (client component)
│   ├── dashboard/          # Dashboard route
│   │   └── page.tsx        # Dashboard page (client component)
│   ├── landing/            # Landing page (unused, has img/ subfolder)
│   ├── login/              # Login route
│   │   └── page.tsx
│   ├── register/           # Register route
│   │   └── page.tsx
│   ├── layout.tsx          # Root layout (wraps with Providers + Toaster)
│   ├── page.tsx            # Root page (/)
│   ├── providers.tsx       # React Query provider setup
│   └── globals.css         # Global styles
│
├── components/
│   ├── auth/               # Auth-related components
│   │   └── auth-layout.tsx # Login/register page wrapper
│   ├── chat/               # Chat-specific components
│   │   ├── chat-mobile-tabs.tsx
│   │   ├── message-bubble.tsx
│   │   └── typing-indicator.tsx
│   ├── dashboard/          # Dashboard-specific components
│   │   ├── dashboard-header.tsx
│   │   ├── document-card.tsx
│   │   ├── mobile-drawer.tsx
│   │   ├── status-badge.tsx
│   │   ├── upload-fab.tsx
│   │   └── upload-panel.tsx
│   └── ui/                 # shadcn/ui primitives (generic, reusable)
│       ├── button.tsx
│       ├── card.tsx
│       ├── form.tsx
│       ├── input.tsx
│       ├── label.tsx
│       ├── progress.tsx
│       ├── scroll-area.tsx
│       ├── sheet.tsx
│       └── sonner.tsx
│
├── lib/                    # Utility functions & client setup
│   ├── axios.ts            # Axios instance with Sanctum config
│   ├── constants.ts        # App constants (e.g. AUTH_VISUAL_SRC)
│   ├── echo.ts             # Laravel Echo + Pusher setup
│   ├── graphql.ts          # GraphQL client config
│   └── utils.ts            # cn() helper (clsx + tailwind-merge)
│
├── stores/                 # Zustand stores
│   └── auth.ts             # Auth state (user, isAuthenticated, setUser)
│
└── middleware.ts           # Next.js middleware (route protection)
```

**Conventions observed**:
1. **All app pages are client components** (`"use client"` directive) — no Server Components with async data fetching
2. **Feature-based component organization**: `components/{feature}/` for page-specific components (auth, chat, dashboard)
3. **Generic UI in** `components/ui/`: Only shadcn/ui primitives, no business logic
4. **Lib for setup, stores for state**: Client configs in `lib/`, global state in `stores/`
5. **No hooks directory**: React Query hooks defined inline in pages (e.g. `useQuery` directly in `page.tsx` files)
6. **No services directory**: API calls via `graphqlClient.request()` or `api.post()` directly in components/pages
7. **Path aliases**: Always use `@/` imports (e.g. `@/components/ui/button`, `@/lib/axios`)

## Gotchas

### 1. Client-only architecture — no SSR data fetching
All pages use `"use client"` and fetch data in `useEffect`/React Query hooks. Middleware only checks cookie existence, not validity. This means:
- Initial page load shows "Loading session..." before user data arrives
- No server-side authentication check beyond cookie presence
- SEO/meta tags cannot be personalized per user server-side

### 2. Middleware does NOT validate session contents
`src/middleware.ts` lines 18-19 only check if a cookie **name** ends with `_session`/`-session`. It does NOT:
- Verify the cookie is valid, unexpired, or matches a real session
- Call the backend to confirm authentication
- A garbage cookie named `fake_session` would pass the check

### 3. Environment variables must be prefixed with `NEXT_PUBLIC_`
Since all pages are client components, env vars MUST be `NEXT_PUBLIC_*` to be accessible. Server-only vars (no prefix) won't work.

Currently used (with defaults):
- `NEXT_PUBLIC_API_URL` → `http://localhost:8000`
- `NEXT_PUBLIC_GRAPHQL_URL` → `http://localhost:8000/graphql`
- `NEXT_PUBLIC_REVERB_APP_KEY` → `docubrain-key`

Missing (hardcoded): `NEXT_PUBLIC_REVERB_HOST` — currently hardcoded to `localhost` in `echo.ts`.

### 4. Real-time updates: Echo setup is browser-only
`src/lib/echo.ts` lines 10-37 check `typeof window !== "undefined"` before initializing Echo. This means:
- Echo instance is `null` on server (Next.js RSC rendering)
- Components using Echo must handle `null` case (e.g. `src/app/dashboard/page.tsx` line 94 `if (echo)`)
- Dynamic import pattern used: `await import("@/lib/echo")` (lines 93-94, 139)

### 5. Chat uses polling, not Echo subscriptions
`src/app/chat/[id]/page.tsx` line 65: `refetchInterval: 3000` — messages refetch every 3 seconds instead of using Echo's real-time updates. Comment at line 65 says "Poor man's subscription for messages until Echo is fully implemented for chat".

Document status updates DO use Echo (dashboard only).

### 6. Tailwind v4 config location unclear
No `tailwind.config.{js,ts,mjs}` file exists. Using Tailwind v4's CSS-based config or defaults. If you need to customize theme (colors, spacing, etc.), check for `@theme` directive in `src/app/globals.css` or create a config file.

### 7. Dark mode is hardcoded
`src/app/layout.tsx` line 31: `className="... dark bg-background text-foreground"` — hardcoded dark mode class, despite `next-themes` being installed (not configured). Light mode won't work without implementing ThemeProvider.

### 8. Session clear pattern
When logging out or session expires, components redirect to `/login?clearSession=true` (e.g. `src/app/dashboard/page.tsx` line 74). Middleware intercepts this param and deletes all session cookies (lines 7-16). Do NOT use `router.push()` alone — must set `window.location.href` to trigger middleware (lines 74, 150).

### 9. GraphQL error shape varies by context
- Login errors: `error.response?.errors?.[0]?.message` (line 71 in `login/page.tsx`)
- Generic errors: Toast library used (`sonner`), but no global error boundary
- 401/403 from GraphQL: Caught in `me` query error, triggers logout (dashboard lines 71-75)

### 10. File upload size limits not visible
`src/app/dashboard/page.tsx` line 179 shows generic error from backend: `error.response?.data?.errors?.file?.[0]`. No client-side file size validation before upload starts. Backend may reject silently.

### 11. Component state vs Zustand — split brain risk
Some state is in Zustand (`user`, `isAuthenticated`), some in React Query cache (`documents`, `conversation`). Echo updates patch React Query directly (lines 102-119 in dashboard), bypassing Zustand. If you add user-related real-time updates, ensure Zustand stays in sync.

### 12. TypeScript strict mode enabled but `any` types used
`tsconfig.json` line 7: `"strict": true`, but pages use `any` for API responses (e.g. `src/app/dashboard/page.tsx` line 106: `doc: any`). GraphQL schema types not generated — no type safety on API boundaries.
