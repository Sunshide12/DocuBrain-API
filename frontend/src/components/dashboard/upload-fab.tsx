"use client";

import type { ChangeEvent, RefObject } from "react";
import { Plus } from "lucide-react";
import {
  Sheet,
  SheetTrigger,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from "@/components/ui/sheet";
import { UploadPanel } from "./upload-panel";

interface UploadFabProps {
  fileInputRef: RefObject<HTMLInputElement | null>;
  isUploading: boolean;
  uploadProgress: number;
  onFileChange: (e: ChangeEvent<HTMLInputElement>) => void;
}

export function UploadFab({
  fileInputRef,
  isUploading,
  uploadProgress,
  onFileChange,
}: UploadFabProps) {
  return (
    <Sheet>
      <SheetTrigger
        className="fixed right-5 bottom-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform active:scale-95 md:hidden"
        aria-label="Upload document"
      >
        <Plus className="h-6 w-6" />
      </SheetTrigger>
      <SheetContent side="bottom">
        <SheetHeader>
          <SheetTitle>Upload Document</SheetTitle>
          <SheetDescription>Upload a PDF to start a new conversation.</SheetDescription>
        </SheetHeader>
        <UploadPanel
          fileInputRef={fileInputRef}
          isUploading={isUploading}
          uploadProgress={uploadProgress}
          onFileChange={onFileChange}
        />
      </SheetContent>
    </Sheet>
  );
}
