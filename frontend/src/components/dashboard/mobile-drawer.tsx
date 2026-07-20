"use client";

import { LogOut, Menu } from "lucide-react";
import {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { DashboardNav } from "./dashboard-nav";

interface MobileDrawerProps {
  userName: string;
  userEmail: string;
  onLogout: () => void;
  isLoggingOut: boolean;
}

export function MobileDrawer({ userName, userEmail, onLogout, isLoggingOut }: MobileDrawerProps) {
  return (
    <Sheet>
      <SheetTrigger
        className="flex h-9 w-9 items-center justify-center rounded-lg text-foreground transition-colors hover:bg-accent md:hidden"
        aria-label="Open menu"
      >
        <Menu className="h-5 w-5" />
      </SheetTrigger>
      <SheetContent side="left" className="flex flex-col">
        <SheetHeader>
          <SheetTitle>{userName}</SheetTitle>
          <SheetDescription>{userEmail}</SheetDescription>
        </SheetHeader>

        {/* Navigation */}
        <nav className="flex-1 py-6">
          <DashboardNav />
        </nav>

        {/* Logout button at bottom */}
        <div className="border-t border-border pt-4">
          <Button
            variant="outline"
            className="w-full rounded-lg"
            onClick={onLogout}
            disabled={isLoggingOut}
          >
            <LogOut className="mr-2 h-4 w-4" />
            Logout
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  );
}
