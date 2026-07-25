"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { useAuthStore } from "@/stores/auth";
import { useRouter, useParams } from "next/navigation";
import { useState, useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

import { toast } from "sonner";
import { ArrowLeft, Send } from "lucide-react";
import { AnimatePresence } from "framer-motion";
import { ChatMobileTabs, type ChatMobileView } from "@/components/chat/chat-mobile-tabs";
import { MessageBubble } from "@/components/chat/message-bubble";
import { TypingIndicator } from "@/components/chat/typing-indicator";
import { QuizPanel } from "@/components/quiz/QuizPanel";
import { AgentSwitcher } from "@/components/agents/AgentSwitcher";

const CONVERSATION_QUERY = gql`
  query GetConversation($id: ID!) {
    conversation(id: $id) {
      id
      title
      agent_type
      document {
        id
        title
        file_path
      }
      messages {
        id
        role
        content
        response_type
        metadata
        created_at
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

export default function ChatPage() {
  const params = useParams();
  const conversationId = params.id as string;
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const queryClient = useQueryClient();
  const [content, setContent] = useState("");
  const [activeMobileView, setActiveMobileView] = useState<ChatMobileView>("chat");
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const quizToastShownRef = useRef<Set<string>>(new Set());

  const { data, isLoading } = useQuery({
    queryKey: ["conversation", conversationId],
    queryFn: () => graphqlClient.request(CONVERSATION_QUERY, { id: conversationId }),
    enabled: !!user && !!conversationId,
    refetchInterval: 3000,
  });

  const sendMessageMutation = useMutation({
    mutationFn: (msg: string) =>
      graphqlClient.request(SEND_MESSAGE_MUTATION, {
        conversation_id: conversationId,
        content: msg,
      }),
    onMutate: async (newMsg) => {
      await queryClient.cancelQueries({ queryKey: ["conversation", conversationId] });
      const previousData: any = queryClient.getQueryData(["conversation", conversationId]);

      queryClient.setQueryData(["conversation", conversationId], (old: any) => {
        if (!old) return old;
        return {
          ...old,
          conversation: {
            ...old.conversation,
            messages: [
              ...old.conversation.messages,
              {
                id: Date.now().toString(),
                role: "user",
                content: newMsg,
                response_type: null,
                metadata: null,
                created_at: new Date().toISOString(),
              },
            ],
          },
        };
      });
      return { previousData };
    },
    onError: (_err, _newMsg, context) => {
      queryClient.setQueryData(["conversation", conversationId], context?.previousData);
      toast.error("Failed to send message");
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ["conversation", conversationId] });
    },
  });

  const handleSend = (e: React.FormEvent) => {
    e.preventDefault();
    if (!content.trim()) return;
    sendMessageMutation.mutate(content);
    setContent("");
  };

  const handleAgentChanged = (newAgentType: string) => {
    // Update local cache immediately so the UI reacts instantly
    queryClient.setQueryData(["conversation", conversationId], (old: any) => {
      if (!old) return old;
      return {
        ...old,
        conversation: { ...old.conversation, agent_type: newAgentType },
      };
    });
    // Auto-switch view based on new agent
    if (newAgentType === "quiz_generator") {
      setActiveMobileView("quizzes");
    } else {
      setActiveMobileView("chat");
    }
  };

  // Listen for QuizGenerationCompleted broadcast event
  useEffect(() => {
    if (!user) return;
    let channel: any = null;

    const subscribe = async () => {
      try {
        if (typeof window === "undefined") return;
        const { echo } = await import("@/lib/echo");
        if (!echo) return;

        channel = echo.private(`App.Models.User.${user.id}`);
        channel.listen(".QuizGenerationCompleted", (e: { document_id: string; status: string }) => {
          const key = `${e?.document_id}-${e?.status}`;
          if (e?.status === "ready" && !quizToastShownRef.current.has(key)) {
            quizToastShownRef.current.add(key);
            toast.success("¡El quiz está listo!", { duration: 4000 });
            queryClient.invalidateQueries({ queryKey: ["documentQuizzes"] });
          }
        });
      } catch (err) {
        console.error("Echo subscription failed", err);
      }
    };

    subscribe();
    return () => {
      if (channel) {
        import("@/lib/echo").then(({ echo }) => echo?.leave(channel.name));
      }
    };
  }, [user, queryClient]);

  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: "smooth", block: "end" });
    }
  }, [data?.conversation?.messages, sendMessageMutation.isPending]);

  // When conversation loads, set the correct initial mobile view
  useEffect(() => {
    const agentType = data?.conversation?.agent_type;
    if (!agentType) return;
    if (agentType === "quiz_generator") {
      setActiveMobileView("quizzes");
    } else {
      setActiveMobileView("chat");
    }
  }, [data?.conversation?.agent_type]);

  if (!user) return <div className="flex h-screen items-center justify-center">Loading session...</div>;
  if (isLoading) return <div className="flex h-screen items-center justify-center">Loading conversation...</div>;

  const conversation = data?.conversation;
  if (!conversation) return <div className="flex h-screen items-center justify-center">Conversation not found.</div>;

  const documentId = conversation.document?.id;
  const agentType: string = conversation.agent_type ?? "document_qa";
  const isQuizMode = agentType === "quiz_generator";

  return (
    <div className="flex h-dvh flex-col overflow-hidden bg-background md:flex-row">
      {/* ── Mobile top bar: back + tabs ── */}
      <div className="flex items-center gap-2 border-b border-border/50 bg-background px-3 py-2 md:hidden">
        <Button
          variant="ghost"
          size="icon"
          className="shrink-0"
          onClick={() => router.back()}
          aria-label="Back"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <div className="flex-1 min-w-0">
          <ChatMobileTabs
            active={activeMobileView}
            agentType={agentType}
            onChange={setActiveMobileView}
          />
        </div>
        {/* Agent switcher on mobile too */}
        <AgentSwitcher
          conversationId={conversationId}
          currentAgentType={agentType}
          onAgentChanged={handleAgentChanged}
        />
      </div>

      {/* ── Left panel: PDF Viewer ── */}
      <div
        className={cn(
          "w-full min-h-0 flex-1 flex-col border-r border-border/50 bg-muted/10 md:flex md:w-[45%] md:flex-initial",
          activeMobileView === "document" ? "flex" : "hidden"
        )}
      >
        {/* Desktop back button */}
        <div className="hidden h-14 items-center border-b border-border/50 bg-background px-4 md:flex">
          <Button variant="ghost" size="sm" onClick={() => router.back()}>
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back
          </Button>
          <span className="ml-4 font-semibold truncate">{conversation.title}</span>
        </div>
        <div className="flex-1 relative">
          {documentId ? (
            <iframe
              src={`${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}/api/documents/${documentId}/download`}
              className="absolute inset-0 w-full h-full border-0"
              title="PDF Viewer"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-muted-foreground">
              No document attached.
            </div>
          )}
        </div>
      </div>

      {/* ── Right panel: Chat or Quizzes ── */}
      <div
        className={cn(
          "relative w-full min-h-0 flex-1 flex-col bg-background md:z-10 md:flex md:w-[55%] md:flex-initial md:shadow-2xl",
          activeMobileView === "chat" || activeMobileView === "quizzes" ? "flex" : "hidden"
        )}
      >
        {/* Desktop header */}
        <div className="hidden h-14 shrink-0 items-center justify-between border-b border-border/50 bg-background px-4 md:flex">
          <div className="flex items-center gap-2 min-w-0">
            <span className="font-semibold truncate">{conversation.title}</span>
          </div>
          <div className="flex items-center gap-3 shrink-0">
            <AgentSwitcher
              conversationId={conversationId}
              currentAgentType={agentType}
              onAgentChanged={handleAgentChanged}
            />
          </div>
        </div>

        {/* ── QUIZ MODE: show QuizPanel only ── */}
        {isQuizMode && (
          <QuizPanel documentId={documentId ?? ""} conversationId={conversationId} />
        )}

        {/* ── CHAT MODE: show chat interface only ── */}
        {!isQuizMode && (
          <>
            <div className="no-scrollbar flex-1 overflow-y-auto p-4 md:p-6">
              <div className="space-y-4">
                <AnimatePresence>
                  {conversation.messages.map((msg: any) => (
                    <MessageBubble
                      key={msg.id}
                      role={msg.role}
                      content={msg.content}
                      responseType={msg.response_type}
                      metadata={msg.metadata}
                    />
                  ))}
                </AnimatePresence>
                {sendMessageMutation.isPending && <TypingIndicator />}
                <div ref={messagesEndRef} className="h-2" />
              </div>
            </div>
            <div className="p-4 md:p-6 bg-background border-t border-border/40">
              <form onSubmit={handleSend} className="flex gap-3 items-center max-w-4xl mx-auto">
                <Input
                  value={content}
                  onChange={(e) => setContent(e.target.value)}
                  placeholder="Ask a question about the document…"
                  className="flex-1 bg-card border-border/60 shadow-sm focus-visible:ring-primary/50 rounded-full px-5 py-6 text-base"
                />
                <Button
                  type="submit"
                  size="icon"
                  className="rounded-full h-12 w-12 shadow-sm"
                  disabled={!content.trim() || sendMessageMutation.isPending}
                >
                  <Send className="h-4 w-4" />
                </Button>
              </form>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
