import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import type { ReactNode } from "react";
import type { SubmissionStatus } from "../../types/api";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/* ─────────────────────────── Button ─────────────────────────── */

type ButtonProps = React.ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: "primary" | "secondary" | "ghost" | "danger";
  size?: "sm" | "md";
};

export function Button({ variant = "secondary", size = "md", className, ...props }: ButtonProps) {
  return (
    <button
      className={cn(
        "inline-flex items-center justify-center gap-1.5 rounded-sm border font-medium",
        "transition-colors disabled:cursor-not-allowed disabled:opacity-50",
        size === "sm" ? "px-2.5 py-1 text-xs" : "px-3.5 py-1.5 text-sm",
        variant === "primary" &&
          "border-[--color-accent] bg-[--color-accent] text-white hover:bg-[--color-accent-hover]",
        variant === "secondary" &&
          "border-[--color-rule-strong] bg-[--color-surface] text-[--color-ink] hover:bg-[--color-surface-2]",
        variant === "ghost" &&
          "border-transparent bg-transparent text-[--color-ink-2] hover:bg-[--color-surface-2]",
        variant === "danger" &&
          "border-[--color-crit] bg-[--color-crit] text-white hover:opacity-90",
        className,
      )}
      {...props}
    />
  );
}

/* ─────────────────────────── Card ─────────────────────────── */

export function Card({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <div className={cn("border border-[--color-rule] bg-[--color-surface]", className)}>{children}</div>
  );
}

export function CardHeader({ title, action }: { title: string; action?: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-3 border-b border-[--color-rule] px-4 py-2.5">
      <h2 className="text-[13px] font-semibold tracking-tight">{title}</h2>
      {action}
    </div>
  );
}

/* ───────────────────── Status indicator ───────────────────── */

/**
 * Status is legible without colour: each state carries its own label and a
 * distinct dot, so it survives greyscale printing and colour-blind viewers.
 */
const STATUS_STYLES: Record<SubmissionStatus, { label: string; className: string }> = {
  DRAFT: { label: "Draft", className: "bg-[--color-surface-2] text-[--color-ink-3]" },
  SUBMITTED: { label: "Submitted", className: "bg-[--color-info-soft] text-[--color-info]" },
  UNDER_REVIEW: { label: "Under review", className: "bg-[--color-info-soft] text-[--color-info]" },
  APPROVED: { label: "Approved", className: "bg-[--color-good-soft] text-[--color-good]" },
  REJECTED: { label: "Rejected", className: "bg-[--color-crit-soft] text-[--color-crit]" },
  REVISION_REQUESTED: { label: "Revision requested", className: "bg-[--color-warn-soft] text-[--color-warn]" },
  WITHDRAWN: { label: "Withdrawn", className: "bg-[--color-surface-2] text-[--color-ink-3]" },
  SUPERSEDED: { label: "Superseded", className: "bg-[--color-surface-2] text-[--color-ink-3]" },
};

export function StatusBadge({ status }: { status: SubmissionStatus }) {
  const style = STATUS_STYLES[status] ?? STATUS_STYLES.DRAFT;

  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 whitespace-nowrap rounded-sm px-2 py-0.5",
        "font-mono text-[10px] font-medium uppercase tracking-wider",
        style.className,
      )}
    >
      <span aria-hidden className="size-1.5 rounded-full bg-current" />
      {style.label}
    </span>
  );
}

export function Chip({
  children,
  tone = "neutral",
}: {
  children: ReactNode;
  tone?: "neutral" | "warn" | "crit" | "good";
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-sm px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wider",
        tone === "neutral" && "bg-[--color-surface-2] text-[--color-ink-3]",
        tone === "warn" && "bg-[--color-warn-soft] text-[--color-warn]",
        tone === "crit" && "bg-[--color-crit-soft] text-[--color-crit]",
        tone === "good" && "bg-[--color-good-soft] text-[--color-good]",
      )}
    >
      {children}
    </span>
  );
}

/* ──────────────────── The four async states ──────────────────── */
/* ARAMS 1.0 had none of these: a failed fetch left the page silent.  */

export function LoadingState({ rows = 4, label = "Loading" }: { rows?: number; label?: string }) {
  return (
    <div className="space-y-2 p-4" role="status" aria-live="polite" aria-busy="true">
      <span className="sr-only">{label}</span>
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="h-8 animate-pulse rounded-sm bg-[--color-surface-2]" />
      ))}
    </div>
  );
}

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center gap-2 px-6 py-12 text-center">
      <p className="text-sm font-semibold">{title}</p>
      {description && <p className="max-w-sm text-[13px] text-[--color-ink-3]">{description}</p>}
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  const message = error instanceof Error ? error.message : "Something went wrong.";

  return (
    <div className="flex flex-col items-center gap-3 px-6 py-10 text-center" role="alert">
      <p className="text-sm font-semibold text-[--color-crit]">This didn’t load</p>
      <p className="max-w-md text-[13px] text-[--color-ink-2]">{message}</p>
      {onRetry && (
        <Button size="sm" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  );
}

/* ─────────────────────────── Table ─────────────────────────── */

export function Table({ children }: { children: ReactNode }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-[13px]">{children}</table>
    </div>
  );
}

export function Th({ children, className }: { children?: ReactNode; className?: string }) {
  return (
    <th
      className={cn(
        "border-b border-[--color-rule-strong] bg-[--color-surface-2] px-3 py-2 text-left",
        "font-mono text-[10px] font-medium uppercase tracking-wider text-[--color-ink-2]",
        className,
      )}
    >
      {children}
    </th>
  );
}

export function Td({ children, className }: { children?: ReactNode; className?: string }) {
  return (
    <td className={cn("border-b border-[--color-rule] px-3 py-2 align-top", className)}>{children}</td>
  );
}

/* ─────────────────────────── Inputs ─────────────────────────── */

type FieldProps = {
  label: string;
  error?: string;
  hint?: string;
  required?: boolean;
  children: ReactNode;
};

export function Field({ label, error, hint, required, children }: FieldProps) {
  return (
    <label className="flex flex-col gap-1">
      <span className="text-[12px] font-medium text-[--color-ink-2]">
        {label}
        {required && <span className="ml-0.5 text-[--color-crit]">*</span>}
      </span>
      {children}
      {hint && !error && <span className="text-[11px] text-[--color-ink-3]">{hint}</span>}
      {/* Field-level feedback — ARAMS 1.0 returned errors as a query string. */}
      {error && (
        <span role="alert" className="text-[11px] text-[--color-crit]">
          {error}
        </span>
      )}
    </label>
  );
}

export const inputClass =
  "w-full rounded-sm border border-[--color-rule-strong] bg-[--color-surface] px-2.5 py-1.5 " +
  "text-sm text-[--color-ink] placeholder:text-[--color-ink-3]";

export function Input(props: React.InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={cn(inputClass, props.className)} />;
}

export function Textarea(props: React.TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...props} className={cn(inputClass, "min-h-20 resize-y", props.className)} />;
}

export function Select(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={cn(inputClass, props.className)} />;
}

/* ───────────────────────── Stat tile ───────────────────────── */

export function Stat({
  label,
  value,
  hint,
  tone,
}: {
  label: string;
  value: string | number;
  hint?: string;
  tone?: "warn" | "crit";
}) {
  return (
    <div className="bg-[--color-surface] px-3.5 py-3">
      <div
        className={cn(
          "tabular font-mono text-[22px] font-medium leading-tight",
          tone === "warn" && "text-[--color-warn]",
          tone === "crit" && "text-[--color-crit]",
        )}
      >
        {value}
      </div>
      <div className="mt-0.5 text-[11px] leading-snug text-[--color-ink-3]">{label}</div>
      {hint && <div className="mt-0.5 text-[10px] text-[--color-ink-3]">{hint}</div>}
    </div>
  );
}

export function StatRow({ children }: { children: ReactNode }) {
  return (
    <div className="grid grid-cols-[repeat(auto-fit,minmax(130px,1fr))] gap-px border border-[--color-rule] bg-[--color-rule]">
      {children}
    </div>
  );
}
