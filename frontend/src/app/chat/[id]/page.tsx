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

const CONVERSATION_QUERY = gql`
  query GetConversation($id: ID!) {
    conversation(id: $id) {
      id
      title
      document {
        id
        title
        file_path
      }
      messages {
        id
        role
        content
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

  const { data, isLoading } = useQuery({
    queryKey: ["conversation", conversationId],
    queryFn: () => graphqlClient.request(CONVERSATION_QUERY, { id: conversationId }),
    enabled: !!user && !!conversationId,
    refetchInterval: 3000, // Poor man's subscription for messages until Echo is fully implemented for chat
  });

  const sendMessageMutation = useMutation({
    mutationFn: (msg: string) => graphqlClient.request(SEND_MESSAGE_MUTATION, {
      conversation_id: conversationId,
      content: msg,
    }),
    onMutate: async (newMsg) => {
      await queryClient.cancelQueries({ queryKey: ["conversation", conversationId] });
      const previousData: any = queryClient.getQueryData(["conversation", conversationId]);

      // Optimistic update
      queryClient.setQueryData(["conversation", conversationId], (old: any) => {
        if (!old) return old;
        return {
          ...old,
          conversation: {
            ...old.conversation,
            messages: [
              ...old.conversation.messages,
              { id: Date.now().toString(), role: "user", content: newMsg, created_at: new Date().toISOString() },
            ],
          },
        };
      });
      return { previousData };
    },
    onError: (err, newMsg, context) => {
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

  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: "smooth", block: "end" });
    }
  }, [data?.conversation?.messages, sendMessageMutation.isPending]);

  if (!user) return <div className="flex h-screen items-center justify-center">Loading session...</div>;
  if (isLoading) return <div className="flex h-screen items-center justify-center">Loading conversation...</div>;

  const conversation = data?.conversation;
  if (!conversation) return <div className="flex h-screen items-center justify-center">Conversation not found.</div>;

  const documentId = conversation.document?.id;

  return (
    <div className="flex h-dvh flex-col overflow-hidden bg-background md:flex-row">
      {/* Mobile-only bar: back to previous page + chat/document switcher. Always visible so
          whichever panel is hidden below can still be reached. */}
      <div className="flex items-center gap-3 border-b border-border/50 bg-background px-3 py-2.5 md:hidden">
        <Button
          variant="ghost"
          size="icon"
          className="shrink-0"
          onClick={() => router.back()}
          aria-label="Back to previous page"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>
        <div className="flex-1">
          <ChatMobileTabs active={activeMobileView} onChange={setActiveMobileView} />
        </div>
      </div>

      {/* Left panel: PDF Viewer (45% on desktop, full width on mobile when selected) */}
      <div
        className={cn(
          "w-full min-h-0 flex-1 flex-col border-r border-border/50 bg-muted/10 md:flex md:w-[45%] md:flex-initial",
          activeMobileView === "document" ? "flex" : "hidden"
        )}
      >
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
              src={`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/documents/${documentId}/download`}
              className="absolute inset-0 w-full h-full border-0"
              title="PDF Viewer"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-muted-foreground">
              No document attached (Global Chat).
            </div>
          )}
        </div>
      </div>

      {/* Right panel: Chat (55% on desktop, full width on mobile when selected) */}
      <div
        className={cn(
          "relative w-full min-h-0 flex-1 flex-col bg-background md:z-10 md:flex md:w-[55%] md:flex-initial md:shadow-2xl",
          activeMobileView === "chat" ? "flex" : "hidden"
        )}
      >
        <div className="no-scrollbar flex-1 overflow-y-auto p-4 md:p-6">
          <div className="space-y-4">
            <AnimatePresence>
              {conversation.messages.map((msg: any) => (
                <MessageBubble key={msg.id} role={msg.role} content={msg.content} />
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
              placeholder="Ask a question about the document..."
              className="flex-1 bg-card border-border/60 shadow-sm focus-visible:ring-primary/50 rounded-full px-5 py-6 text-base"
            />
            <Button type="submit" size="icon" className="rounded-full h-12 w-12 shadow-sm" disabled={!content.trim() || sendMessageMutation.isPending}>
              <Send className="h-4 w-4" />
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
