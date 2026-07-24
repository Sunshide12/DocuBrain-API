"use client";

import { useQuery } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { useState } from "react";
import { Search, Calculator, GraduationCap, X, Sparkles } from "lucide-react";

const AVAILABLE_AGENTS_QUERY = gql`
  query AvailableAgents {
    availableAgents {
      key
      name
      description
    }
  }
`;

const SUGGEST_AGENT_QUERY = gql`
  query SuggestAgent($document_id: ID!) {
    suggestAgent(document_id: $document_id) {
      agent_type
      confidence
    }
  }
`;

interface Agent {
  key: string;
  name: string;
  description: string;
}

const AGENT_ICONS: Record<string, React.ReactNode> = {
  document_qa: <Search className="h-5 w-5" />,
  math_solver: <Calculator className="h-5 w-5" />,
  quiz_generator: <GraduationCap className="h-5 w-5" />,
};

const AGENT_COLORS: Record<string, string> = {
  document_qa: "bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20",
  math_solver: "bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20",
  quiz_generator: "bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20",
};

interface AgentPickerModalProps {
  documentId: string;
  documentTitle: string;
  onConfirm: (agentType: string) => void;
  onCancel: () => void;
  isConfirming?: boolean;
}

export function AgentPickerModal({
  documentId,
  documentTitle,
  onConfirm,
  onCancel,
  isConfirming,
}: AgentPickerModalProps) {
  const [selected, setSelected] = useState<string | null>(null);

  const { data: agentsData, isLoading: agentsLoading } = useQuery({
    queryKey: ["availableAgents"],
    queryFn: () => graphqlClient.request<{ availableAgents: Agent[] }>(AVAILABLE_AGENTS_QUERY),
  });

  const { data: suggestionData } = useQuery({
    queryKey: ["suggestAgent", documentId],
    queryFn: () =>
      graphqlClient.request<{ suggestAgent: { agent_type: string; confidence: number } }>(
        SUGGEST_AGENT_QUERY,
        { document_id: documentId }
      ),
    enabled: !!documentId,
  });

  const agents = agentsData?.availableAgents ?? [];
  const suggestedKey = suggestionData?.suggestAgent?.agent_type ?? null;

  const handleConfirm = () => {
    const agentType = selected ?? suggestedKey ?? "document_qa";
    onConfirm(agentType);
  };

  return (
    // Backdrop
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
      onClick={(e) => { if (e.target === e.currentTarget) onCancel(); }}
    >
      {/* Modal */}
      <div className="relative w-full max-w-lg rounded-2xl border border-border bg-background shadow-2xl">
        {/* Header */}
        <div className="flex items-start justify-between p-6 pb-4">
          <div>
            <h2 className="text-lg font-semibold">Choose an AI Agent</h2>
            <p className="mt-0.5 text-sm text-muted-foreground line-clamp-1">
              {documentTitle}
            </p>
          </div>
          <Button variant="ghost" size="icon" className="-mt-1 -mr-1 shrink-0" onClick={onCancel}>
            <X className="h-4 w-4" />
          </Button>
        </div>

        {/* Agent cards */}
        <div className="space-y-2 px-6 pb-2">
          {agentsLoading ? (
            <div className="py-8 text-center text-sm text-muted-foreground">Loading agents…</div>
          ) : (
            agents.map((agent) => {
              const isSelected = selected === agent.key || (!selected && suggestedKey === agent.key);

              return (
                <button
                  key={agent.key}
                  type="button"
                  onClick={() => setSelected(agent.key)}
                  className={cn(
                    "w-full rounded-xl border p-4 text-left transition-all",
                    isSelected
                      ? "border-primary bg-primary/5 ring-1 ring-primary"
                      : "border-border hover:border-border/80 hover:bg-muted/50"
                  )}
                >
                  <div className="flex items-center gap-3">
                    <div
                      className={cn(
                        "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border",
                        AGENT_COLORS[agent.key] ?? "bg-muted"
                      )}
                    >
                      {AGENT_ICONS[agent.key] ?? <Search className="h-5 w-5" />}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">{agent.name}</span>
                      </div>
                      <p className="mt-0.5 text-xs text-muted-foreground line-clamp-2">
                        {agent.description}
                      </p>
                    </div>
                  </div>
                </button>
              );
            })
          )}
        </div>

        {/* Footer */}
        <div className="flex justify-end gap-2 p-6 pt-4">
          <Button variant="ghost" onClick={onCancel} disabled={isConfirming}>
            Cancel
          </Button>
          <Button onClick={handleConfirm} disabled={agentsLoading || isConfirming}>
            {isConfirming ? "Starting…" : "Start Chat"}
          </Button>
        </div>
      </div>
    </div>
  );
}
