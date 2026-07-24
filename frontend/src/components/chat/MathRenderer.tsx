"use client";

import ReactMarkdown from "react-markdown";
import remarkMath from "remark-math";
import rehypeKatex from "rehype-katex";
import "katex/dist/katex.min.css";

interface MathRendererProps {
  content: string;
}

export function MathRenderer({ content }: MathRendererProps) {
  return (
    <div className="math-content prose prose-sm dark:prose-invert max-w-none break-words text-sm leading-relaxed">
      <ReactMarkdown
        remarkPlugins={[remarkMath]}
        rehypePlugins={[rehypeKatex]}
        components={{
          p: ({ children }) => <p className="mb-2 last:mb-0">{children}</p>,
          strong: ({ children }) => <strong className="font-semibold">{children}</strong>,
          em: ({ children }) => <em className="italic">{children}</em>,
          h1: ({ children }) => <h1 className="mb-2 text-base font-bold">{children}</h1>,
          h2: ({ children }) => <h2 className="mb-1.5 text-sm font-bold">{children}</h2>,
          h3: ({ children }) => <h3 className="mb-1 text-sm font-semibold">{children}</h3>,
          ul: ({ children }) => <ul className="mb-2 list-disc pl-4">{children}</ul>,
          ol: ({ children }) => <ol className="mb-2 list-decimal pl-4">{children}</ol>,
          li: ({ children }) => <li className="mb-0.5">{children}</li>,
          code: ({ children, className }) => {
            const isBlock = className?.includes("language-");
            return isBlock ? (
              <code className="block rounded bg-muted px-3 py-2 font-mono text-xs">{children}</code>
            ) : (
              <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">{children}</code>
            );
          },
          pre: ({ children }) => <pre className="mb-2 overflow-x-auto">{children}</pre>,
        }}
      >
        {content}
      </ReactMarkdown>
    </div>
  );
}
