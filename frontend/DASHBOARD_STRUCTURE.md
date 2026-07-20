# Dashboard Structure

## Current Implementation

The dashboard has been restructured into a multi-route layout with a shared sidebar.

### Routes

- **`/dashboard`** (Overview page)
  - Shows KPI cards with user statistics
  - Displays: Documents Uploaded, Documents Ready, Conversations
  - Data source: `userStats` GraphQL query (server-side user scoping enforced)

- **`/dashboard/uploads`** (Upload & Documents page)
  - Original dashboard functionality moved here
  - Document upload (desktop inline card + mobile FAB)
  - Documents grid with real-time status updates via Echo
  - Chat creation for ready documents

### Layout Components

- **`src/app/dashboard/layout.tsx`**
  - Shared layout for all `/dashboard/*` routes
  - Desktop: Left sidebar (240px wide) with navigation + branding
  - Mobile: Sidebar hidden, navigation accessible via MobileDrawer
  
- **`src/components/dashboard/dashboard-nav.tsx`**
  - Navigation component (client component for active state)
  - Current routes: Overview, Uploads
  - Placeholder: Settings (disabled, ready for future implementation)

- **`src/components/dashboard/kpi-card.tsx`**
  - Reusable KPI metric card component
  - Props: title, value, icon, description, isLoading

### Shared Components (Already Existed)

- `DashboardHeader` - Header with user info and logout
- `UploadPanel` - Desktop upload UI
- `UploadFab` - Mobile floating action button for upload
- `DocumentCard` - Document grid item with status badge
- `StatusBadge` - Document processing status indicator
- `MobileDrawer` - Mobile hamburger menu (now includes navigation)

## Adding a New Dashboard Section

To add a new section (e.g., `/dashboard/settings`):

### 1. Create the route page
```bash
mkdir -p src/app/dashboard/settings
touch src/app/dashboard/settings/page.tsx
```

### 2. Add navigation item
Edit `src/components/dashboard/dashboard-nav.tsx`:
```typescript
const navItems = [
  // ... existing items
  {
    label: "Settings",
    href: "/dashboard/settings",
    icon: Settings,
    disabled: false, // Remove the disabled flag
  },
];
```

### 3. Implement the page
Follow the pattern in `src/app/dashboard/page.tsx`:
- Use `"use client"` directive if you need React Query or Zustand
- Include `<DashboardHeader>` for consistency
- Fetch user session via `me` query (already in Zustand)
- Keep max-width container: `mx-auto max-w-6xl`

### 4. Route protection
Already handled! The middleware at `src/middleware.ts` protects all `/dashboard/:path*` routes automatically.

### 5. Update userStats if needed
If the new section requires additional KPIs:
1. Update `backend/graphql/schema.graphql` (`UserStats` type)
2. Update `backend/app/GraphQL/Queries/UserStats.php` resolver
3. Update frontend query in `src/app/dashboard/page.tsx`

## Notes

- The layout is Server Component by default (no state, no hooks)
- Page components are Client Components (`"use client"`) for React Query/Zustand
- Mobile navigation uses the existing `MobileDrawer` component (hamburger menu)
- All user data filtering is enforced server-side via Eloquent global scopes
- TypeScript types should be defined per-page for GraphQL responses
