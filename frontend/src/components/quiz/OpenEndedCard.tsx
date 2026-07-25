"use client";

import { useState } from "react";
import { useMutation } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { Loader2, Eye } from "lucide-react";

const REVIEW_OPEN_ENDED_ANSWER_MUTATION = gql`
  mutation ReviewOpenEndedAnswer($question_id: ID!, $user_answer: String!) {
    reviewOpenEndedAnswer(question_id: $question_id, user_answer: $user_answer) {
      score
      feedback
      isCorrect
    }
  }
`;

interface OpenEndedReview {
  score: string;
  feedback: string;
  isCorrect: boolean;
}

interface OpenEndedCardProps {
  questionId?: string;
  question: string;
  correctAnswer: string;
  onAnswer?: (isCorrect: boolean) => void;
}

const SCORE_BADGES: Record<string, { label: string; emoji: string; className: string }> = {
  excellent: { label: "Excellent", emoji: "🟢", className: "bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20" },
  good: { label: "Good", emoji: "🟡", className: "bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border-yellow-500/20" },
  partial: { label: "Partial", emoji: "🟠", className: "bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-500/20" },
  incorrect: { label: "Incorrect", emoji: "🔴", className: "bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20" },
};

export function OpenEndedCard({ questionId, question, correctAnswer, onAnswer }: OpenEndedCardProps) {
  const [answer, setAnswer] = useState("");
  const [review, setReview] = useState<OpenEndedReview | null>(null);
  const [revealedOnly, setRevealedOnly] = useState(false);

  const reviewMutation = useMutation({
    mutationFn: () =>
      graphqlClient.request<{ reviewOpenEndedAnswer: OpenEndedReview }>(REVIEW_OPEN_ENDED_ANSWER_MUTATION, {
        question_id: questionId,
        user_answer: answer,
      }),
    onSuccess: (data) => {
      setReview(data.reviewOpenEndedAnswer);
      onAnswer?.(data.reviewOpenEndedAnswer.isCorrect);
    },
  });

  const badge = review ? SCORE_BADGES[review.score] ?? SCORE_BADGES.partial : null;

  return (
    <div className="rounded-xl border border-border bg-card p-4 space-y-3">
      <div>
        <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Open Ended</span>
        <p className="mt-1 text-sm font-medium">{question}</p>
      </div>

      {!review && !revealedOnly && (
        <div className="space-y-2">
          <textarea
            value={answer}
            onChange={(e) => setAnswer(e.target.value)}
            disabled={reviewMutation.isPending}
            placeholder="Write your answer…"
            rows={4}
            className="w-full rounded-lg border border-input bg-transparent px-3 py-2 text-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50 resize-none"
          />
          <div className="flex items-center gap-2">
            {questionId ? (
              <Button
                size="sm"
                onClick={() => reviewMutation.mutate()}
                disabled={!answer.trim() || reviewMutation.isPending}
                className="gap-1.5"
              >
                {reviewMutation.isPending && <Loader2 className="h-3.5 w-3.5 animate-spin" />}
                Submit &amp; Review
              </Button>
            ) : (
              // No persisted question id (e.g. inline chat response) — grading isn't
              // available yet, so fall back to a plain self-assessed reveal.
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setRevealedOnly(true);
                  onAnswer?.(true);
                }}
                className="gap-1.5"
              >
                <Eye className="h-3.5 w-3.5" />
                Reveal Model Answer
              </Button>
            )}
          </div>
          {reviewMutation.isError && (
            <p className="text-xs text-red-500">Could not review your answer. Please try again.</p>
          )}
        </div>
      )}

      {revealedOnly && (
        <div className="rounded-lg bg-green-500/10 border border-green-500/20 px-3 py-2 space-y-1">
          <p className="text-sm font-medium text-green-700 dark:text-green-400">{correctAnswer}</p>
        </div>
      )}

      {review && (
        <div className="space-y-2">
          <span className={cn("inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium", badge?.className)}>
            {badge?.emoji} {badge?.label}
          </span>
          <p className="text-sm text-muted-foreground">{review.feedback}</p>
          <div className="rounded-lg bg-muted/50 border border-border px-3 py-2 space-y-1">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Model Answer</p>
            <p className="text-sm">{correctAnswer}</p>
          </div>
        </div>
      )}
    </div>
  );
}
