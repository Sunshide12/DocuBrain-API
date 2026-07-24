"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { useState, useRef, useEffect } from "react";
import { QuizCard } from "./QuizCard";
import { ChevronDown, ChevronRight, BookOpen, Loader2, Send } from "lucide-react";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

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

const SEND_MESSAGE_MUTATION = gql`
  mutation SendMessage($conversation_id: ID!, $content: String!) {
    sendMessage(conversation_id: $conversation_id, content: $content) {
      id
      role
      content
      response_type
      metadata
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

interface ChatMessage {
  id: string;
  role: "user" | "assistant";
  content: string;
}

interface QuizPanelProps {
  documentId: string;
  conversationId?: string;
  isGenerating?: boolean;
}

export function QuizPanel({ documentId, conversationId, isGenerating }: QuizPanelProps) {
  const queryClient = useQueryClient();
  const [chatInput, setChatInput] = useState("");
  const [localMessages, setLocalMessages] = useState<ChatMessage[]>([]);
  const chatEndRef = useRef<HTMLDivElement>(null);

  const { data, isLoading, error } = useQuery({
    queryKey: ["documentQuizzes", documentId],
    queryFn: () =>
      graphqlClient.request<{ documentQuizzes: Quiz[] }>(DOCUMENT_QUIZZES_QUERY, {
        document_id: documentId,
      }),
    enabled: !!documentId,
    refetchInterval: (query) => {
      const quizzes = query.state.data?.documentQuizzes ?? [];
      return quizzes.some((q) => q.status === "generating") ? 3000 : false;
    },
  });

  const sendMutation = useMutation({
    mutationFn: (content: string) =>
      graphqlClient.request(SEND_MESSAGE_MUTATION, {
        conversation_id: conversationId,
        content,
      }),
    onMutate: (content) => {
      setLocalMessages((prev) => [
        ...prev,
        { id: Date.now().toString(), role: "user", content },
      ]);
    },
    onSuccess: (data: any) => {
      const msg = data?.sendMessage;
      if (msg) {
        setLocalMessages((prev) => [
          ...prev,
          { id: msg.id, role: "assistant", content: msg.content },
        ]);
        // If a new quiz was generated, refresh
        if (msg.response_type === "quiz") {
          queryClient.invalidateQueries({ queryKey: ["documentQuizzes", documentId] });
        }
      }
    },
  });

  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [localMessages]);

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = chatInput.trim();
    if (!trimmed || !conversationId || sendMutation.isPending) return;
    sendMutation.mutate(trimmed);
    setChatInput("");
  };

  const quizzes = data?.documentQuizzes ?? [];

  return (
    <div className="flex flex-1 flex-col min-h-0">
      {/* Scrollable quizzes + chat area */}
      <div className="flex-1 overflow-y-auto">
        {/* Quizzes section */}
        <div className="p-4 md:p-6 space-y-4">
          {isLoading && (
            <div className="flex items-center justify-center gap-2 py-8 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" />
              Loading quizzes…
            </div>
          )}

          {error && (
            <div className="flex items-center justify-center py-8 text-sm text-muted-foreground">
              Failed to load quizzes.
            </div>
          )}

          {!isLoading && !error && quizzes.length === 0 && localMessages.length === 0 && !isGenerating && (
            <div className="flex flex-col items-center justify-center gap-3 py-16 text-center px-6">
              <BookOpen className="h-8 w-8 text-muted-foreground/50" />
              <p className="text-sm text-muted-foreground max-w-xs">
                No quizzes yet. Ask me to generate one using the chat below!
              </p>
              <p className="text-xs text-muted-foreground/70">
                Try: <em>"Give me a 5-question quiz about this document"</em>
              </p>
            </div>
          )}

          {isGenerating && (
            <div className="rounded-xl border border-primary/20 bg-primary/5 p-4 flex flex-col items-center justify-center gap-3 text-center animate-pulse">
              <Loader2 className="h-6 w-6 animate-spin text-primary" />
              <div>
                <p className="text-sm font-medium text-primary">Generating your study quiz...</p>
                <p className="text-xs text-muted-foreground mt-1">This might take a few moments as I analyze the document.</p>
              </div>
            </div>
          )}

          {quizzes.map((quiz) => (
            <QuizAccordion key={quiz.id} quiz={quiz} />
          ))}
        </div>

        {/* Mini-chat messages (shown below quizzes) */}
        {localMessages.length > 0 && (
          <div className="border-t border-border/50 px-4 md:px-6 py-4 space-y-3">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              Quiz Chat
            </p>
            {localMessages.map((msg) => (
              <div
                key={msg.id}
                className={cn(
                  "flex",
                  msg.role === "user" ? "justify-end" : "justify-start"
                )}
              >
                <div
                  className={cn(
                    "max-w-[85%] rounded-2xl px-4 py-2.5 text-sm",
                    msg.role === "user"
                      ? "bg-primary text-primary-foreground rounded-br-sm"
                      : "bg-muted text-foreground rounded-bl-sm"
                  )}
                >
                  {msg.content}
                </div>
              </div>
            ))}
            {sendMutation.isPending && (
              <div className="flex justify-start">
                <div className="bg-muted rounded-2xl rounded-bl-sm px-4 py-2.5">
                  <div className="flex gap-1.5 items-center">
                    <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:0ms]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:150ms]" />
                    <span className="h-1.5 w-1.5 rounded-full bg-muted-foreground/60 animate-bounce [animation-delay:300ms]" />
                  </div>
                </div>
              </div>
            )}
            <div ref={chatEndRef} />
          </div>
        )}
        {localMessages.length === 0 && <div ref={chatEndRef} />}
      </div>

      {/* Mini-chat input — always visible at the bottom */}
      {conversationId && (
        <div className="shrink-0 border-t border-border/40 bg-background p-3 md:p-4">
          <form onSubmit={handleSend} className="flex gap-2 items-center">
            <Input
              value={chatInput}
              onChange={(e) => setChatInput(e.target.value)}
              placeholder="Ask for more quizzes or about the content…"
              className="flex-1 rounded-full bg-muted/50 border-border/60 text-sm px-4 py-2"
              disabled={sendMutation.isPending}
            />
            <Button
              type="submit"
              size="icon"
              className="rounded-full h-9 w-9 shrink-0"
              disabled={!chatInput.trim() || sendMutation.isPending}
            >
              <Send className="h-3.5 w-3.5" />
            </Button>
          </form>
        </div>
      )}
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
