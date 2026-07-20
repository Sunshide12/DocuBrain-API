"use client";

import { motion } from "framer-motion";

interface MessageBubbleProps {
  role: string;
  content: string;
}

export function MessageBubble({ role, content }: MessageBubbleProps) {
  const isUser = role === "user";

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className={`flex ${isUser ? "justify-end" : "justify-start"}`}
    >
      <div
        className={`max-w-[85%] rounded-3xl px-5 py-3 text-sm leading-relaxed shadow-sm ${
          isUser
            ? "rounded-br-lg bg-primary text-primary-foreground"
            : "rounded-bl-lg border border-border/60 bg-card text-card-foreground"
        }`}
      >
        <p className="whitespace-pre-wrap break-words">{content}</p>
      </div>
    </motion.div>
  );
}
