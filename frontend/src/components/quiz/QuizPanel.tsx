"use client";

import { useQuery } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { useState } from "react";
import { QuizCard } from "./QuizCard";
import { ChevronDown, ChevronRight, BookOpen, Loader2 } from "lucide-react";
import { cn } from "@/lib/utils";

const DOCUMENT_QUIZZES_QUERY = gql`
  query DocumentQuizzes($document_id: ID!) {
    documentQuizzes(document_id: $document_id) {
      id
      title
      status
      created_at
      questions {
        id
        question
        type
        options
        correct_answer
        explanation
        sort_order
      }
    }
  }
`;

interface QuizQuestion {
  id: string;
  question: string;
  type: string;
  options: string[] | null;
  correct_answer: string;
  explanation: string | null;
  sort_order: number;
}

interface Quiz {
  id: string;
  title: string;
  status: string;
  created_at: string;
  questions: QuizQuestion[];
}

interface QuizPanelProps {
  documentId: string;
}

export function QuizPanel({ documentId }: QuizPanelProps) {
  const { data, isLoading, error } = useQuery({
    queryKey: ["documentQuizzes", documentId],
    queryFn: () =>
      graphqlClient.request<{ documentQuizzes: Quiz[] }>(DOCUMENT_QUIZZES_QUERY, {
        document_id: documentId,
      }),
    enabled: !!documentId,
    refetchInterval: (query) => {
      // Poll while any quiz is still generating
      const quizzes = query.state.data?.documentQuizzes ?? [];
      return quizzes.some((q) => q.status === "generating") ? 3000 : false;
    },
  });

  const quizzes = data?.documentQuizzes ?? [];

  if (isLoading) {
    return (
      <div className="flex flex-1 items-center justify-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin" />
        Loading quizzes…
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
        Failed to load quizzes.
      </div>
    );
  }

  if (quizzes.length === 0) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-3 text-center px-6">
        <BookOpen className="h-8 w-8 text-muted-foreground/50" />
        <p className="text-sm text-muted-foreground">
          No quizzes yet. Quizzes are auto-generated when a document finishes processing,
          or you can start a conversation with the <strong>Study &amp; Quiz Generator</strong> agent.
        </p>
      </div>
    );
  }

  return (
    <div className="flex-1 overflow-y-auto space-y-4 p-4 md:p-6">
      {quizzes.map((quiz) => (
        <QuizAccordion key={quiz.id} quiz={quiz} />
      ))}
    </div>
  );
}

function QuizAccordion({ quiz }: { quiz: Quiz }) {
  const [open, setOpen] = useState(true);
  const [scores, setScores] = useState<Record<string, boolean>>({});

  const answered = Object.keys(scores).length;
  const correct = Object.values(scores).filter(Boolean).length;

  const handleAnswer = (questionId: string, isCorrect: boolean) => {
    setScores((prev) => ({ ...prev, [questionId]: isCorrect }));
  };

  return (
    <div className="rounded-xl border border-border overflow-hidden">
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center justify-between bg-muted/30 px-4 py-3 text-left hover:bg-muted/50 transition-colors"
      >
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium truncate">{quiz.title}</p>
          <div className="flex items-center gap-2 mt-0.5">
            {quiz.status === "generating" ? (
              <span className="flex items-center gap-1 text-xs text-muted-foreground">
                <Loader2 className="h-3 w-3 animate-spin" /> Generating…
              </span>
            ) : quiz.status === "failed" ? (
              <span className="text-xs text-red-500">Generation failed</span>
            ) : (
              <span className="text-xs text-muted-foreground">
                {quiz.questions.length} question{quiz.questions.length !== 1 ? "s" : ""}
                {answered > 0 && ` · ${correct}/${answered} correct`}
              </span>
            )}
          </div>
        </div>
        {open ? (
          <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
        ) : (
          <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" />
        )}
      </button>

      <div className={cn("space-y-3 p-4", !open && "hidden")}>
        {quiz.status === "ready" && quiz.questions.length === 0 && (
          <p className="text-xs text-muted-foreground">No questions were generated for this quiz.</p>
        )}
        {quiz.questions.map((q) => (
          <QuizCard
            key={q.id}
            question={q.question}
            type={q.type}
            options={q.options}
            correctAnswer={q.correct_answer}
            explanation={q.explanation}
            onAnswer={(isCorrect) => handleAnswer(q.id, isCorrect)}
          />
        ))}

        {answered === quiz.questions.length && quiz.questions.length > 0 && (
          <div className="rounded-lg bg-primary/5 border border-primary/20 px-4 py-3 text-center">
            <p className="text-sm font-medium">
              Score: {correct}/{quiz.questions.length}
              {correct === quiz.questions.length ? " 🎉 Perfect!" : " — Keep studying!"}
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
