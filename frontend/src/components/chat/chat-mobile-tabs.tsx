"use client";

import { cn } from "@/lib/utils";

export type ChatMobileView = "chat" | "document" | "quizzes";

interface ChatMobileTabsProps {
  active: ChatMobileView;
  agentType: string;
  onChange: (view: ChatMobileView) => void;
}

export function ChatMobileTabs({ active, agentType, onChange }: ChatMobileTabsProps) {
  const isQuizMode = agentType === "quiz_generator";

  const views: { value: ChatMobileView; label: string }[] = [
    { value: "document", label: "Document" },
    ...(isQuizMode
      ? [{ value: "quizzes" as ChatMobileView, label: "Quizzes" }]
      : [{ value: "chat" as ChatMobileView, label: "Chat" }]),
  ];

  return (
    <div className="flex items-center gap-1 rounded-full bg-muted p-1">
      {views.map(({ value, label }) => (
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
