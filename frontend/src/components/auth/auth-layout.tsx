import type { ReactNode } from "react";
import Image from "next/image";
import { AUTH_VISUAL_SRC } from "@/lib/constants";

interface AuthLayoutProps {
  title: string;
  description: string;
  children: ReactNode;
  footer: ReactNode;
}

export function AuthLayout({ title, description, children, footer }: AuthLayoutProps) {
  return (
    <div className="flex min-h-dvh w-full">
      {/* Image panel — desktop only, mobile stays scroll-free and form-only. */}
      <div className="relative hidden overflow-hidden bg-muted md:block md:w-2/5 lg:w-1/2">
        <Image
          src={AUTH_VISUAL_SRC}
          alt=""
          fill
          priority
          sizes="(min-width: 768px) 40vw, 0vw"
          className="object-cover"
        />
        {/* Dim overlay */}
        <div className="absolute inset-0 bg-black/80" />

        {/* Text overlay */}
        <div className="absolute inset-0 flex flex-col justify-center p-10 lg:p-16 pb-32 lg:pb-48 text-white">
          <div className="space-y-5 max-w-lg">
            <h2 className="text-3xl lg:text-4xl font-bold tracking-tight leading-tight">
              Tu Conocimiento, Centralizado.
            </h2>
            <p className="text-lg text-gray-300">
              DocuBrain procesa, organiza y conecta tus documentos para que encuentres lo que necesitas al instante.
            </p>
          </div>
        </div>
      </div>

      <div className="flex w-full flex-col items-center justify-center px-6 py-10 sm:px-10 md:w-3/5 lg:w-1/2">
        <div className="w-full max-w-sm space-y-6">
          <div className="space-y-1.5 text-center">
            <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
            <p className="text-sm text-muted-foreground">{description}</p>
          </div>

          {children}

          <div className="text-center text-sm text-muted-foreground">{footer}</div>
        </div>
      </div>
    </div>
  );
}
