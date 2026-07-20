import { LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";
import { MobileDrawer } from "./mobile-drawer";

interface DashboardHeaderProps {
  userName: string;
  userEmail: string;
  onLogout: () => void;
  isLoggingOut: boolean;
}

export function DashboardHeader({
  userName,
  userEmail,
  onLogout,
  isLoggingOut,
}: DashboardHeaderProps) {
  return (
    <header className="sticky top-0 z-30 flex items-center justify-between border-b border-border/50 bg-background/80 px-4 py-3 backdrop-blur-sm md:px-8 md:py-4">
      <div>
        <h1 className="text-lg font-semibold tracking-tight md:text-2xl">Dashboard</h1>
        <p className="hidden text-sm text-muted-foreground md:block">Welcome back, {userName}</p>
      </div>

      <div className="hidden items-center gap-3 md:flex">
        <span className="text-sm text-muted-foreground">{userName}</span>
        <Button variant="outline" className="rounded-lg" onClick={onLogout} disabled={isLoggingOut}>
          <LogOut className="mr-2 h-4 w-4" />
          Logout
        </Button>
      </div>

      <MobileDrawer
        userName={userName}
        userEmail={userEmail}
        onLogout={onLogout}
        isLoggingOut={isLoggingOut}
      />
    </header>
  );
}
