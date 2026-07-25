"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import api from "@/lib/axios";
import { gql } from "graphql-request";
import { useAuthStore } from "@/stores/auth";
import { useRouter } from "next/navigation";
import { useEffect, useState, useRef } from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { toast } from "sonner";
import { UploadCloud, FileText } from "lucide-react";
import { DashboardHeader } from "@/components/dashboard/dashboard-header";
import { UploadPanel } from "@/components/dashboard/upload-panel";
import { UploadFab } from "@/components/dashboard/upload-fab";
import { DocumentCard } from "@/components/dashboard/document-card";


const ME_QUERY = gql`
  query Me {
    me {
      id
      name
      email
    }
  }
`;

const DOCUMENTS_QUERY = gql`
  query GetDocuments {
    documents(first: 50) {
      data {
        id
        title
        original_name
        status
        created_at
      }
    }
  }
`;

const LOGOUT_MUTATION = gql`
  mutation Logout {
    logout {
      message
    }
  }
`;

interface Document {
  id: string;
  title: string | null;
  original_name: string;
  status: string;
  created_at: string;
}

interface DocumentsData {
  documents: {
    data: Document[];
  };
}

interface DocumentProgressEvent {
  document_id: string;
  status: string;
}

interface CreateConversationResponse {
  createConversation: {
    id: string;
    agent_type: string;
  };
}


export default function UploadsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const setUser = useAuthStore((state) => state.setUser);
  const user = useAuthStore((state) => state.user);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isUploading, setIsUploading] = useState(false);
  const notifiedDocsRef = useRef<Set<string>>(new Set());

  const { data: meData, error: meError } = useQuery({
    queryKey: ["me"],
    queryFn: () => graphqlClient.request(ME_QUERY),
    retry: false,
  });

  useEffect(() => {
    if (meData?.me) {
      setUser(meData.me);
    }
    if (meError) {
      toast.error("Session expired.");
      setUser(null);
      window.location.href = "/login?clearSession=true";
    }
  }, [meData, meError, setUser]);

  const { data: docsData, isLoading: docsLoading } = useQuery({
    queryKey: ["documents"],
    queryFn: () => graphqlClient.request(DOCUMENTS_QUERY),
    enabled: !!user,
  });

  useEffect(() => {
    if (!user) return;

    let currentChannel: ReturnType<NonNullable<typeof import("@/lib/echo").echo>["private"]> | null = null;

    const subscribeToDocuments = async () => {
      try {
        if (typeof window !== "undefined") {
          const { echo } = await import("@/lib/echo");
          if (echo) {
            currentChannel = echo.private(`App.Models.User.${user.id}`);

            currentChannel.listen(".DocumentProgressUpdated", (e: DocumentProgressEvent) => {
              if (e && e.document_id) {
                queryClient.setQueryData<DocumentsData>(["documents"], (oldData) => {
                  if (!oldData || !oldData.documents || !oldData.documents.data) return oldData;

                  const updatedDocs = oldData.documents.data.map((doc) => {
                    if (doc.id === e.document_id.toString()) {
                      return { ...doc, status: e.status };
                    }
                    return doc;
                  });

                  return {
                    ...oldData,
                    documents: {
                      ...oldData.documents,
                      data: updatedDocs,
                    },
                  };
                });

                // Only show toast once per document per status to avoid duplicates
                const notificationKey = `${e.document_id}-${e.status}`;
                if (!notifiedDocsRef.current.has(notificationKey)) {
                  notifiedDocsRef.current.add(notificationKey);

                  if (e.status === 'ready') {
                    toast.success("A document has finished processing and is ready!", {
                      closeButton: true,
                    });
                  } else if (e.status === 'failed') {
                    toast.error("A document failed to process.", {
                      closeButton: true,
                    });
                  }
                }
              }
            });
          }
        }
      } catch (e) {
        console.error("Subscription failed", e);
      }
    };

    subscribeToDocuments();

    return () => {
      if (currentChannel && typeof window !== "undefined") {
        import("@/lib/echo").then(({ echo }) => {
          if (currentChannel) {
            echo?.leave(currentChannel.name);
          }
        });
      }
    };
  }, [user, queryClient]);

  const logoutMutation = useMutation({
    mutationFn: () => graphqlClient.request(LOGOUT_MUTATION),
    onSuccess: () => {
      setUser(null);
      window.location.href = "/login?clearSession=true";
    },
  });

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    setUploadProgress(0);

    const formData = new FormData();
    formData.append("file", file);

    try {
      await api.post("/api/documents/upload", formData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
            setUploadProgress(percentCompleted);
          }
        },
      });
      toast.warning("Document uploaded successfully. Processing started...");
      queryClient.invalidateQueries({ queryKey: ["documents"] });
      queryClient.invalidateQueries({ queryKey: ["userStats"] });
    } catch (error) {
      const err = error as { response?: { data?: { message?: string; errors?: { file?: string[] } } } };
      toast.error(err.response?.data?.message || err.response?.data?.errors?.file?.[0] || "Upload failed");
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  const createConversationMutation = useMutation({
    mutationFn: ({ documentId, agentType }: { documentId: string; agentType: string }) =>
      graphqlClient.request<CreateConversationResponse>(gql`
        mutation CreateConversation($document_id: ID!, $agent_type: String) {
          createConversation(document_id: $document_id, agent_type: $agent_type) {
            id
            agent_type
          }
        }
      `, { document_id: documentId, agent_type: agentType }),
    onSuccess: (data) => {
      router.push(`/chat/${data.createConversation.id}`);
    },
    onError: () => toast.error("Failed to start conversation"),
  });

  const handleChatClick = (docId: string, status: string) => {
    if (status !== 'ready') {
      toast.error("Document is still processing Twin.");
      return;
    }
    createConversationMutation.mutate({ documentId: docId, agentType: 'document_qa' });
  };

  if (!user) return <div className="flex h-dvh items-center justify-center">Loading session...</div>;

  const documents = docsData?.documents?.data || [];

  return (
    <div className="min-h-dvh pb-24 md:pb-8">
      <DashboardHeader
        userName={user.name}
        userEmail={user.email}
        onLogout={() => logoutMutation.mutate()}
        isLoggingOut={logoutMutation.isPending}
      />

      <main className="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-8 md:py-8">
        {/* Desktop-only inline upload card; mobile uses the floating action button below. */}
        <Card className="hidden rounded-2xl border-2 border-dashed border-primary/20 bg-primary/5 transition-colors hover:bg-primary/10 md:block">
          <CardHeader>
            <CardTitle className="flex items-center text-xl">
              <UploadCloud className="mr-2 h-5 w-5" />
              Upload Document
            </CardTitle>
            <CardDescription>Upload a PDF to start a new conversation.</CardDescription>
          </CardHeader>
          <CardContent>
            <UploadPanel
              fileInputRef={fileInputRef}
              isUploading={isUploading}
              uploadProgress={uploadProgress}
              onFileChange={handleUpload}
            />
          </CardContent>
        </Card>

        <section className="space-y-4">
          <div className="flex items-center gap-2">
            <FileText className="h-5 w-5 text-muted-foreground" />
            <h2 className="text-lg font-semibold tracking-tight">Your Documents</h2>
          </div>

          {docsLoading ? (
            <div className="text-muted-foreground">Loading documents...</div>
          ) : documents.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-border py-12 text-center text-muted-foreground">
              No documents yet. Upload one to begin!
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {documents.map((doc: Document) => (
                <DocumentCard
                  key={doc.id}
                  doc={doc}
                  onSelect={(id, status) => handleChatClick(id, status)}
                  isChatPending={createConversationMutation.isPending}
                />
              ))}
            </div>
          )}
        </section>
      </main>

      <UploadFab
        fileInputRef={fileInputRef}
        isUploading={isUploading}
        uploadProgress={uploadProgress}
        onFileChange={handleUpload}
      />

    </div>
  );
}
