"use client";

import { motion } from "framer-motion";
import { MathRenderer } from "./MathRenderer";
import { QuizCard } from "@/components/quiz/QuizCard";

interface QuizQuestion {
  question: string;
  type: string;
  options?: string[] | null;
  correct_answer: string;
  explanation?: string | null;
}

interface MessageBubbleProps {
  role: string;
  content: string;
  responseType?: string | null;
  metadata?: string | null;
}

export function MessageBubble({ role, content, responseType, metadata }: MessageBubbleProps) {
  const isUser = role === "user";

  const renderAssistantContent = () => {
    if (responseType === "steps") {
      return <MathRenderer content={content} />;
    }

    if (responseType === "quiz") {
      let questions: QuizQuestion[] = [];
      if (metadata) {
        try {
          const parsed = JSON.parse(metadata);
          questions = parsed?.questions ?? [];
        } catch {
          // fall through to plain text
        }
      }

      if (questions.length > 0) {
        return (
          <div className="space-y-3">
            {content && (
              <p className="whitespace-pre-wrap break-words text-sm">{content}</p>
            )}
            {questions.map((q, i) => (
              <QuizCard
                key={i}
                question={q.question}
                type={q.type}
                options={q.options}
                correctAnswer={q.correct_answer}
                explanation={q.explanation}
              />
            ))}
          </div>
        );
      }
    }

    return <p className="whitespace-pre-wrap break-words">{content}</p>;
  };

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
        {isUser ? (
          <p className="whitespace-pre-wrap break-words">{content}</p>
        ) : (
          renderAssistantContent()
        )}
      </div>
    </motion.div>
  );
}
