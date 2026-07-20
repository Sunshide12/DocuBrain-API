import { MessageSquare } from "lucide-react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { StatusBadge } from "./status-badge";

interface DocumentSummary {
  id: string;
  title?: string | null;
  original_name: string;
  status: string;
}

interface DocumentCardProps {
  doc: DocumentSummary;
  onSelect: (docId: string, status: string) => void;
  isChatPending: boolean;
}

export function DocumentCard({ doc, onSelect, isChatPending }: DocumentCardProps) {
  return (
    <Card
      className="cursor-pointer gap-3 rounded-2xl transition-colors hover:bg-accent"
      onClick={() => onSelect(doc.id, doc.status)}
    >
      <CardHeader className="pb-0">
        <CardTitle className="line-clamp-1 text-base">
          {doc.title || doc.original_name}
        </CardTitle>
      </CardHeader>
      <CardContent className="flex items-center justify-between">
        <StatusBadge status={doc.status} />
        <Button
          variant="ghost"
          size="sm"
          className="h-8"
          disabled={doc.status !== "ready" || isChatPending}
        >
          <MessageSquare className="mr-2 h-4 w-4" />
          Chat
        </Button>
      </CardContent>
    </Card>
  );
}
