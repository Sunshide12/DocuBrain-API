import { AlertCircle } from "lucide-react";

const STATUS_STYLES: Record<string, string> = {
  ready: "bg-green-500/10 text-green-500",
  completed: "bg-green-500/10 text-green-500",
  processing: "bg-yellow-500/10 text-yellow-500",
  pending: "bg-yellow-500/10 text-yellow-500",
  failed: "bg-red-500/10 text-red-500",
  error: "bg-red-500/10 text-red-500",
};

interface StatusBadgeProps {
  status: string;
}

export function StatusBadge({ status }: StatusBadgeProps) {
  const isFailed = status === "failed" || status === "error";
  const style = STATUS_STYLES[status] ?? "bg-muted text-muted-foreground";

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium capitalize ${style}`}
    >
      {isFailed && <AlertCircle className="h-3 w-3" />}
      {status}
    </span>
  );
}
