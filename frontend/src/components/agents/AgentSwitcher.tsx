"use client";

import { useQuery, useMutation } from "@tanstack/react-query";
import { graphqlClient } from "@/lib/graphql";
import { gql } from "graphql-request";
import { useState, useRef, useEffect } from "react";
import { Search, Calculator, GraduationCap, ChevronDown, Check } from "lucide-react";
import { cn } from "@/lib/utils";

const AVAILABLE_AGENTS_QUERY = gql`
  query AvailableAgents {
    availableAgents {
      key
      name
      description
    }
  }
`;

const SWITCH_AGENT_MUTATION = gql`
  mutation SwitchAgentType($conversation_id: ID!, $agent_type: String!) {
    switchAgentType(conversation_id: $conversation_id, agent_type: $agent_type) {
      id
      agent_type
    }
  }
`;

interface Agent {
  key: string;
  name: string;
  description: string;
}

interface AgentSwitcherProps {
  conversationId: string;
  currentAgentType: string;
  onAgentChanged: (newAgentType: string) => void;
}

const AGENT_ICONS: Record<string, React.ReactNode> = {
  document_qa: <Search className="h-4 w-4" />,
  math_solver: <Calculator className="h-4 w-4" />,
  quiz_generator: <GraduationCap className="h-4 w-4" />,
};

const AGENT_COLORS: Record<string, string> = {
  document_qa: "bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20",
  math_solver: "bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20",
  quiz_generator: "bg-green-500/10 text-green-600 dark:text-green-400 border-green-500/20",
};

const AGENT_ICON_COLORS: Record<string, string> = {
  document_qa: "text-blue-600 dark:text-blue-400",
  math_solver: "text-orange-600 dark:text-orange-400",
  quiz_generator: "text-green-600 dark:text-green-400",
};

export function AgentSwitcher({
  conversationId,
  currentAgentType,
  onAgentChanged,
}: AgentSwitcherProps) {
  const [open, setOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const { data: agentsData, isLoading } = useQuery({
    queryKey: ["availableAgents"],
    queryFn: () => graphqlClient.request<{ availableAgents: Agent[] }>(AVAILABLE_AGENTS_QUERY),
  });

  const switchMutation = useMutation({
    mutationFn: (agentType: string) =>
      graphqlClient.request(SWITCH_AGENT_MUTATION, {
        conversation_id: conversationId,
        agent_type: agentType,
      }),
    onSuccess: (_data, agentType) => {
      onAgentChanged(agentType);
      setOpen(false);
    },
  });

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    if (open) document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [open]);

  const agents = agentsData?.availableAgents ?? [];
  const currentAgent = agents.find((a) => a.key === currentAgentType);
  const currentColor = AGENT_COLORS[currentAgentType] ?? "bg-muted text-foreground";
  const currentIcon = AGENT_ICONS[currentAgentType] ?? <Search className="h-4 w-4" />;

  return (
    <div ref={dropdownRef} className="relative">
      {/* Trigger button */}
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        disabled={isLoading || switchMutation.isPending}
        className={cn(
          "flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-all",
          "active:scale-95 select-none",
          currentColor,
          open && "ring-1 ring-current ring-offset-1 ring-offset-background"
        )}
      >
        <span className={cn(AGENT_ICON_COLORS[currentAgentType])}>{currentIcon}</span>
        <span className="max-w-[100px] truncate sm:max-w-none">
          {currentAgent?.name ?? currentAgentType}
        </span>
        <ChevronDown
          className={cn(
            "h-3 w-3 shrink-0 transition-transform duration-200",
            open && "rotate-180"
          )}
        />
      </button>

      {/* Dropdown */}
      {open && (
        <div
          className={cn(
            "absolute right-0 z-50 mt-2 origin-top-right",
            // Mobile: near-full width; desktop: fixed wider width to fit descriptions
            "w-[calc(100vw-2rem)] max-w-xs sm:w-80",
            "rounded-2xl border border-border bg-background shadow-xl",
            "animate-in fade-in-0 zoom-in-95 duration-150"
          )}
        >
          <div className="p-2">
            <p className="mb-1.5 px-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              Switch Agent
            </p>
            {agents.map((agent) => {
              const isActive = agent.key === currentAgentType;
              const isSwitching = switchMutation.isPending && switchMutation.variables === agent.key;
              return (
                <button
                  key={agent.key}
                  type="button"
                  disabled={isActive || switchMutation.isPending}
                  onClick={() => {
                    if (!isActive) switchMutation.mutate(agent.key);
                  }}
                  className={cn(
                    "flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors",
                    isActive
                      ? "bg-primary/8 cursor-default"
                      : "hover:bg-muted/70 active:bg-muted cursor-pointer"
                  )}
                >
                  {/* Agent icon */}
                  <div
                    className={cn(
                      "flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border",
                      AGENT_COLORS[agent.key] ?? "bg-muted"
                    )}
                  >
                    {AGENT_ICONS[agent.key] ?? <Search className="h-4 w-4" />}
                  </div>

                  {/* Agent info */}
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium leading-tight">{agent.name}</p>
                    <p className="text-xs text-muted-foreground line-clamp-1 sm:line-clamp-none mt-0.5 sm:mt-1 sm:leading-relaxed">
                      {agent.description}
                    </p>
                  </div>

                  {/* Active / loading indicator */}
                  {isActive && !isSwitching && (
                    <Check className="h-4 w-4 shrink-0 text-primary" />
                  )}
                  {isSwitching && (
                    <div className="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                  )}
                </button>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
