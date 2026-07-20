import type { ChangeEvent, RefObject } from "react";
import { UploadCloud } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";

interface UploadPanelProps {
  fileInputRef: RefObject<HTMLInputElement | null>;
  isUploading: boolean;
  uploadProgress: number;
  onFileChange: (e: ChangeEvent<HTMLInputElement>) => void;
}

export function UploadPanel({
  fileInputRef,
  isUploading,
  uploadProgress,
  onFileChange,
}: UploadPanelProps) {
  return (
    <div className="flex flex-col items-center justify-center gap-4 py-4 text-center">
      <input
        type="file"
        accept="application/pdf"
        className="hidden"
        ref={fileInputRef}
        onChange={onFileChange}
        disabled={isUploading}
      />
      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
        <UploadCloud className="h-6 w-6" />
      </div>
      <div className="space-y-1">
        <p className="text-sm font-medium">Upload a PDF</p>
        <p className="text-xs text-muted-foreground">
          Start a new conversation with your document
        </p>
      </div>
      <Button
        size="lg"
        className="rounded-lg"
        onClick={() => fileInputRef.current?.click()}
        disabled={isUploading}
      >
        {isUploading ? "Uploading..." : "Select PDF File"}
      </Button>
      {isUploading && (
        <div className="w-full space-y-2">
          <Progress value={uploadProgress} className="w-full" />
          <p className="text-center text-xs text-muted-foreground">{uploadProgress}%</p>
        </div>
      )}
    </div>
  );
}
