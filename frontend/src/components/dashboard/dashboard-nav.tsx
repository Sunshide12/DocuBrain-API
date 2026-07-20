"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import { LayoutDashboard, Upload, Settings } from "lucide-react";

const navItems = [
  {
    label: "Overview",
    href: "/dashboard",
    icon: LayoutDashboard,
  },
  {
    label: "Uploads",
    href: "/dashboard/uploads",
    icon: Upload,
  },
  {
    label: "Settings",
    href: "/dashboard/settings",
    icon: Settings,
    disabled: true, // Placeholder for future implementation
  },
];

export function DashboardNav() {
  const pathname = usePathname();

  return (
    <nav className="space-y-1">
      {navItems.map((item) => {
        const isActive = pathname === item.href;
        const Icon = item.icon;

        return (
          <Link
            key={item.href}
            href={item.disabled ? "#" : item.href}
            className={cn(
              "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              isActive
                ? "bg-primary text-primary-foreground"
                : item.disabled
                  ? "cursor-not-allowed text-muted-foreground/40 hover:bg-transparent"
                  : "text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            )}
            aria-disabled={item.disabled}
            onClick={(e) => item.disabled && e.preventDefault()}
          >
            <Icon className="h-5 w-5" />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}
