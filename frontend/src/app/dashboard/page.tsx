"use client";

import { useQuery, useMutation } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { useAuthStore } from "@/stores/auth";
import { useEffect } from "react";
import { toast } from "sonner";
import { FileText, MessageSquare, CheckCircle } from "lucide-react";
import { DashboardHeader } from "@/components/dashboard/dashboard-header";
import { KpiCard } from "@/components/dashboard/kpi-card";

const ME_QUERY = gql`
  query Me {
    me {
      id
      name
      email
    }
  }
`;

const USER_STATS_QUERY = gql`
  query GetUserStats {
    userStats {
      totalDocuments
      documentsReady
      totalConversations
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

export default function DashboardOverviewPage() {
  const setUser = useAuthStore((state) => state.setUser);
  const user = useAuthStore((state) => state.user);

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

  const { data: statsData, isLoading: statsLoading } = useQuery({
    queryKey: ["userStats"],
    queryFn: () => graphqlClient.request(USER_STATS_QUERY),
    enabled: !!user,
  });

  const logoutMutation = useMutation({
    mutationFn: () => graphqlClient.request(LOGOUT_MUTATION),
    onSuccess: () => {
      setUser(null);
      window.location.href = "/login?clearSession=true";
    },
  });

  if (!user) {
    return (
      <div className="flex h-dvh items-center justify-center">
        Loading session...
      </div>
    );
  }

  const stats = statsData?.userStats;

  return (
    <div className="min-h-dvh">
      <DashboardHeader
        userName={user.name}
        userEmail={user.email}
        onLogout={() => logoutMutation.mutate()}
        isLoggingOut={logoutMutation.isPending}
      />

      <main className="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-8 md:py-8">
        {/* Welcome section */}
        <section>
          <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
            Welcome back, {user.name}
          </h1>
          <p className="mt-1 text-muted-foreground">
            Here&apos;s an overview of your document library and chat activity.
          </p>
        </section>

        {/* KPI Cards */}
        <section className="grid gap-4 md:grid-cols-3">
          <KpiCard
            title="Documents Uploaded"
            value={stats?.totalDocuments ?? 0}
            icon={FileText}
            description="Total PDFs in your library"
            isLoading={statsLoading}
          />
          <KpiCard
            title="Documents Ready"
            value={stats?.documentsReady ?? 0}
            icon={CheckCircle}
            description="Processed and ready to chat"
            isLoading={statsLoading}
          />
          <KpiCard
            title="Conversations"
            value={stats?.totalConversations ?? 0}
            icon={MessageSquare}
            description="Total chat sessions started"
            isLoading={statsLoading}
          />
        </section>

        {/* Quick actions or additional content can go here */}
      </main>
    </div>
  );
}
