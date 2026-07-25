"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { CheckCircle, XCircle, Eye } from "lucide-react";
import { OpenEndedCard } from "./OpenEndedCard";

interface QuizCardProps {
  questionId?: string;
  question: string;
  type: string;
  options?: string[] | null;
  correctAnswer: string;
  explanation?: string | null;
  onAnswer?: (isCorrect: boolean) => void;
}

export function QuizCard({
  questionId,
  question,
  type,
  options,
  correctAnswer,
  explanation,
  onAnswer,
}: QuizCardProps) {
  const [selected, setSelected] = useState<string | null>(null);
  const [revealed, setRevealed] = useState(false);

  const isAnswered = selected !== null || revealed;

  const handleOptionClick = (option: string) => {
    if (isAnswered) return;
    setSelected(option);
    // For multiple_choice and true_false: the option contains the letter (e.g., "A) ...")
    const optionLetter = option.charAt(0).toUpperCase();
    const correct = correctAnswer.trim().toUpperCase();
    const isCorrect = optionLetter === correct || option.trim() === correctAnswer.trim();
    onAnswer?.(isCorrect);
  };

  const handleReveal = () => {
    if (revealed) return;
    setRevealed(true);
    onAnswer?.(true); // Flashcards always count as answered correctly (self-assessment)
  };

  if (type === "flashcard") {
    return (
      <div className="rounded-xl border border-border bg-card p-4 space-y-3">
        <div>
          <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Flashcard</span>
          <p className="mt-1 text-sm font-medium">{question}</p>
        </div>

        {!revealed ? (
          <Button variant="outline" size="sm" onClick={handleReveal} className="gap-1.5">
            <Eye className="h-3.5 w-3.5" />
            Reveal Answer
          </Button>
        ) : (
          <div className="rounded-lg bg-green-500/10 border border-green-500/20 px-3 py-2 space-y-1">
            <p className="text-sm font-medium text-green-700 dark:text-green-400">{correctAnswer}</p>
            {explanation && (
              <p className="text-xs text-muted-foreground">{explanation}</p>
            )}
          </div>
        )}
      </div>
    );
  }

  if (type === "open_ended") {
    return (
      <OpenEndedCard
        questionId={questionId}
        question={question}
        correctAnswer={correctAnswer}
        onAnswer={onAnswer}
      />
    );
  }

  // multiple_choice and true_false
  const choices = (type === "true_false" && (!options || options.length === 0))
    ? ["True", "False"]
    : (options ?? []);

  return (
    <div className="rounded-xl border border-border bg-card p-4 space-y-3">
      <div>
        <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
          {type === "multiple_choice" ? "Multiple Choice" : type === "true_false" ? "True / False" : "Question"}
        </span>
        <p className="mt-1 text-sm font-medium">{question}</p>
      </div>

      <div className="space-y-2">
        {choices.map((option) => {
          const optionLetter = option.charAt(0).toUpperCase();
          const correct = correctAnswer.trim().toUpperCase();
          const isThisCorrect = optionLetter === correct || option.trim() === correctAnswer.trim();
          const isSelected = selected === option;

          let variantClass = "border-border bg-card hover:bg-muted/50";
          if (isAnswered && isThisCorrect) {
            variantClass = "border-green-500 bg-green-500/10 text-green-700 dark:text-green-400";
          } else if (isAnswered && isSelected && !isThisCorrect) {
            variantClass = "border-red-500 bg-red-500/10 text-red-700 dark:text-red-400";
          }

          return (
            <button
              key={option}
              type="button"
              disabled={isAnswered}
              onClick={() => handleOptionClick(option)}
              className={cn(
                "w-full rounded-lg border px-3 py-2 text-left text-sm transition-colors",
                variantClass,
                !isAnswered && "cursor-pointer"
              )}
            >
              <span className="flex items-center gap-2">
                {isAnswered && isThisCorrect && <CheckCircle className="h-3.5 w-3.5 shrink-0 text-green-600 dark:text-green-400" />}
                {isAnswered && isSelected && !isThisCorrect && <XCircle className="h-3.5 w-3.5 shrink-0 text-red-600 dark:text-red-400" />}
                {option}
              </span>
            </button>
          );
        })}
      </div>

      {isAnswered && explanation && (
        <p className="text-xs text-muted-foreground border-t border-border pt-2">{explanation}</p>
      )}
    </div>
  );
}
