import { DashboardNav } from "@/components/dashboard/dashboard-nav";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-dvh">
      {/* Sidebar - Hidden on mobile, visible on md+ */}
      <aside className="hidden w-64 border-r border-border bg-card md:block">
        <div className="sticky top-0 flex h-dvh flex-col">
          {/* Logo/Brand area */}
          <div className="border-b border-border px-6 py-4">
            <h1 className="text-xl font-bold">DocuBrain</h1>
          </div>

          {/* Navigation */}
          <div className="flex-1 overflow-y-auto px-3 py-4">
            <DashboardNav />
          </div>

          {/* Footer info */}
          <div className="border-t border-border px-6 py-4">
            <p className="text-xs text-muted-foreground">
              AI-powered document chat
            </p>
          </div>
        </div>
      </aside>

      {/* Main content area */}
      <div className="flex-1">
        {children}
      </div>
    </div>
  );
}
