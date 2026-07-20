"use client";

import { cn } from "@/lib/utils";

export type ChatMobileView = "chat" | "document";

interface ChatMobileTabsProps {
  active: ChatMobileView;
  onChange: (view: ChatMobileView) => void;
}

const VIEWS: { value: ChatMobileView; label: string }[] = [
  { value: "chat", label: "Chat" },
  { value: "document", label: "Document" },
];

export function ChatMobileTabs({ active, onChange }: ChatMobileTabsProps) {
  return (
    <div className="flex items-center gap-1 rounded-full bg-muted p-1">
      {VIEWS.map(({ value, label }) => (
        <button
          key={value}
          type="button"
          onClick={() => onChange(value)}
          className={cn(
            "flex-1 rounded-full px-4 py-1.5 text-sm font-medium transition-colors",
            active === value
              ? "bg-background text-foreground shadow-sm"
              : "text-muted-foreground"
          )}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
