"use client";

import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import api from "@/lib/axios";
import { gql } from "graphql-request";
import { useAuthStore } from "@/stores/auth";
import { useRouter } from "next/navigation";
import { useEffect, useState, useRef } from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { toast } from "sonner";
import { LogOut, UploadCloud, MessageSquare, AlertCircle, FileText } from "lucide-react";

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

export default function DashboardPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const setUser = useAuthStore((state) => state.setUser);
  const user = useAuthStore((state) => state.user);

  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [isUploading, setIsUploading] = useState(false);

  // Fetch current user if not in store
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
      router.push("/login");
    }
  }, [meData, meError, setUser, router]);

  // Fetch documents
  const { data: docsData, isLoading: docsLoading } = useQuery({
    queryKey: ["documents"],
    queryFn: () => graphqlClient.request(DOCUMENTS_QUERY),
    enabled: !!user,
  });

  useEffect(() => {
    if (!user) return;

    let currentChannel: any = null;

    const subscribeToDocuments = async () => {
      try {
        const query = `
          subscription {
            documentUpdated {
              id
              status
            }
          }
        `;
        const response: any = await graphqlClient.request(query);
        const channelName = response?.extensions?.lighthouse_subscriptions?.channel;

        if (channelName && typeof window !== "undefined") {
          import("@/lib/echo").then(({ echo }) => {
            if (echo) {
              currentChannel = echo.private(channelName);
              currentChannel.listen(".lighthouse.subscription", (e: any) => {
                const updatedDoc = e.data?.documentUpdated;
                if (updatedDoc) {
                  queryClient.invalidateQueries({ queryKey: ["documents"] });
                  if (updatedDoc.status === 'ready') {
                     toast.success("A document has finished processing and is ready!");
                  } else if (updatedDoc.status === 'failed') {
                     toast.error("A document failed to process.");
                  }
                }
              });
            }
          });
        }
      } catch (e) {
        console.error("Subscription failed", e);
      }
    };

    subscribeToDocuments();

    return () => {
      if (currentChannel && typeof window !== "undefined") {
        import("@/lib/echo").then(({ echo }) => {
          echo?.leave(currentChannel.name);
        });
      }
    };
  }, [user, queryClient]);

  const logoutMutation = useMutation({
    mutationFn: () => graphqlClient.request(LOGOUT_MUTATION),
    onSuccess: () => {
      setUser(null);
      router.push("/login");
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
      toast.success("Document uploaded successfully. Processing started...");
      queryClient.invalidateQueries({ queryKey: ["documents"] });
    } catch (error: any) {
      toast.error(error.response?.data?.message || error.response?.data?.errors?.file?.[0] || "Upload failed");
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
      if (fileInputRef.current) fileInputRef.current.value = "";
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case "ready":
      case "completed":
        return "text-green-500";
      case "processing":
      case "pending":
        return "text-yellow-500";
      case "failed":
      case "error":
        return "text-red-500";
      default:
        return "text-muted-foreground";
    }
  };

  const createConversationMutation = useMutation({
    mutationFn: (documentId: string) => graphqlClient.request(gql`
      mutation CreateConversation($document_id: ID!) {
        createConversation(document_id: $document_id) {
          id
        }
      }
    `, { document_id: documentId }),
    onSuccess: (data: any) => {
      router.push(`/chat/${data.createConversation.id}`);
    },
    onError: () => toast.error("Failed to start conversation"),
  });

  const handleChatClick = (docId: string, status: string) => {
    if (status !== 'ready') {
      toast.error("Document is still processing or failed.");
      return;
    }
    createConversationMutation.mutate(docId);
  };

  if (!user) return <div className="flex h-screen items-center justify-center">Loading session...</div>;

  const documents = docsData?.documents?.data || [];

  return (
    <div className="min-h-screen p-8 max-w-7xl mx-auto space-y-8">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Dashboard</h1>
          <p className="text-muted-foreground">Welcome back, {user.name}</p>
        </div>
        <Button variant="outline" onClick={() => logoutMutation.mutate()}>
          <LogOut className="mr-2 h-4 w-4" />
          Logout
        </Button>
      </header>

      <div className="grid gap-8 md:grid-cols-3">
        <Card className="col-span-1 border-dashed border-2 border-primary/20 bg-primary/5 transition-colors hover:bg-primary/10">
          <CardHeader>
            <CardTitle className="text-xl flex items-center">
              <UploadCloud className="mr-2 h-5 w-5" />
              Upload Document
            </CardTitle>
            <CardDescription>Upload a PDF to start a new conversation.</CardDescription>
          </CardHeader>
          <CardContent className="flex flex-col items-center justify-center py-8">
            <input
              type="file"
              accept="application/pdf"
              className="hidden"
              ref={fileInputRef}
              onChange={handleUpload}
              disabled={isUploading}
            />
            <Button size="lg" onClick={() => fileInputRef.current?.click()} disabled={isUploading}>
              {isUploading ? "Uploading..." : "Select PDF File"}
            </Button>
            {isUploading && (
              <div className="w-full mt-6 space-y-2">
                <Progress value={uploadProgress} className="w-full" />
                <p className="text-sm text-center text-muted-foreground">{uploadProgress}%</p>
              </div>
            )}
          </CardContent>
        </Card>

        <Card className="col-span-1 md:col-span-2">
          <CardHeader>
            <CardTitle className="text-xl flex items-center">
              <FileText className="mr-2 h-5 w-5" />
              Your Documents
            </CardTitle>
            <CardDescription>Select a document to chat with it.</CardDescription>
          </CardHeader>
          <CardContent>
            {docsLoading ? (
              <div className="text-muted-foreground">Loading documents...</div>
            ) : documents.length === 0 ? (
              <div className="text-muted-foreground text-center py-8">No documents yet. Upload one to begin!</div>
            ) : (
              <div className="grid gap-4 md:grid-cols-2">
                {documents.map((doc: any) => (
                  <Card key={doc.id} className="cursor-pointer hover:bg-accent transition-colors" onClick={() => handleChatClick(doc.id, doc.status)}>
                    <CardHeader className="pb-2">
                      <CardTitle className="text-base line-clamp-1">{doc.title || doc.original_name}</CardTitle>
                    </CardHeader>
                    <CardContent className="flex justify-between items-center text-sm">
                      <span className={`capitalize font-medium ${statusColor(doc.status)} flex items-center gap-1`}>
                        {doc.status === 'failed' && <AlertCircle className="h-4 w-4" />}
                        {doc.status}
                      </span>
                      <Button 
                        variant="ghost" 
                        size="sm" 
                        className="h-8"
                        disabled={doc.status !== 'ready' || createConversationMutation.isPending}
                      >
                        <MessageSquare className="h-4 w-4 mr-2" />
                        Chat
                      </Button>
                    </CardContent>
                  </Card>
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
