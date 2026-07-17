"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { useAuthStore } from "@/stores/auth";
import { useRouter, useParams } from "next/navigation";
import { useState, useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ScrollArea } from "@/components/ui/scroll-area";
import { toast } from "sonner";
import { ArrowLeft, Send } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";

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
  const scrollRef = useRef<HTMLDivElement>(null);

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
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [data?.conversation?.messages]);

  if (!user) return <div className="flex h-screen items-center justify-center">Loading session...</div>;
  if (isLoading) return <div className="flex h-screen items-center justify-center">Loading conversation...</div>;

  const conversation = data?.conversation;
  if (!conversation) return <div className="flex h-screen items-center justify-center">Conversation not found.</div>;

  const documentId = conversation.document?.id;

  return (
    <div className="flex h-screen flex-col md:flex-row overflow-hidden bg-background">
      {/* Left panel: PDF Viewer */}
      <div className="flex-1 flex flex-col border-r border-border/50 bg-muted/20">
        <div className="h-14 border-b border-border/50 flex items-center px-4 bg-background">
          <Button variant="ghost" size="sm" onClick={() => router.push("/dashboard")}>
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

      {/* Right panel: Chat */}
      <div className="w-full md:w-[450px] lg:w-[500px] flex flex-col bg-card relative shadow-xl z-10">
        <ScrollArea className="flex-1 p-4" ref={scrollRef}>
          <div className="space-y-4">
            <AnimatePresence>
              {conversation.messages.map((msg: any) => (
                <motion.div
                  key={msg.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  <div
                    className={`max-w-[85%] rounded-2xl px-4 py-2 ${msg.role === 'user'
                      ? 'bg-primary text-primary-foreground rounded-br-none'
                      : 'bg-muted text-muted-foreground rounded-bl-none'
                      }`}
                  >
                    <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
                  </div>
                </motion.div>
              ))}
            </AnimatePresence>
            {sendMessageMutation.isPending && (
              <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex justify-start">
                <div className="bg-muted text-muted-foreground rounded-2xl rounded-bl-none px-4 py-2">
                  <span className="animate-pulse">Thinking...</span>
                </div>
              </motion.div>
            )}
          </div>
        </ScrollArea>
        <div className="p-4 bg-background border-t border-border/50">
          <form onSubmit={handleSend} className="flex gap-2">
            <Input
              value={content}
              onChange={(e) => setContent(e.target.value)}
              placeholder="Ask a question about the document..."
              className="flex-1 bg-muted/50 border-transparent focus-visible:ring-primary/50"
            />
            <Button type="submit" size="icon" disabled={!content.trim() || sendMessageMutation.isPending}>
              <Send className="h-4 w-4" />
            </Button>
          </form>
        </div>
      </div>
    </div>
  );
}
