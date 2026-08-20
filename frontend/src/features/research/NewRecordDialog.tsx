import { useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useNavigate } from "react-router-dom";
import { api, ApiError } from "../../lib/api";
import { Button, Field, Input, Select } from "../../components/ui";
import type { Envelope, ResearchRecord } from "../../types/api";

const CURRENT_YEAR = new Date().getFullYear();

/**
 * Creates a DRAFT. Nothing is auto-approved — ARAMS 1.0 had an admin path that
 * inserted records pre-stamped 'Approved', bypassing validation entirely.
 */
export function NewRecordDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const [type, setType] = useState("PUBLICATION");
  const [form, setForm] = useState<Record<string, string>>({
    title: "",
    journal_name: "",
    pub_year: String(CURRENT_YEAR),
    quartile: "N/A",
    doi: "",
  });

  const mutation = useMutation({
    mutationFn: (payload: Record<string, unknown>) =>
      api.post<Envelope<ResearchRecord>>("/research-records", payload),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["research-records"] });
      onClose();
      if (result.data.submission) navigate(`/submissions/${result.data.submission.id}`);
    },
  });

  const error = mutation.error instanceof ApiError ? mutation.error : null;

  if (!open) return null;

  function set(key: string, value: string) {
    setForm((prev) => ({ ...prev, [key]: value }));
  }

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();

    const payload: Record<string, unknown> = { type, title: form.title };

    if (type === "PUBLICATION") {
      Object.assign(payload, {
        journal_name: form.journal_name || null,
        pub_year: Number(form.pub_year),
        quartile: form.quartile,
        doi: form.doi || null,
      });
    }

    if (type === "AWARD") payload.award_year = Number(form.pub_year);

    mutation.mutate(payload);
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
      role="dialog"
      aria-modal="true"
      aria-label="Add research record"
      onClick={(e) => e.target === e.currentTarget && onClose()}
    >
      <form
        onSubmit={handleSubmit}
        className="flex w-full max-w-md flex-col gap-3 border border-[--color-rule] bg-[--color-surface] p-5"
      >
        <h2 className="font-serif text-lg font-semibold tracking-tight">Add research record</h2>

        <Field label="Record type" required>
          <Select value={type} onChange={(e) => setType(e.target.value)}>
            <option value="PUBLICATION">Publication</option>
            <option value="IP_RECORD">Intellectual property</option>
            <option value="AWARD">Award</option>
          </Select>
        </Field>

        <Field label="Title" required error={error?.fieldError("title")}>
          <Input
            required
            value={form.title}
            onChange={(e) => set("title", e.target.value)}
            placeholder="Full title of the work"
          />
        </Field>

        {type === "PUBLICATION" && (
          <>
            <Field label="Journal or conference" error={error?.fieldError("journal_name")}>
              <Input value={form.journal_name} onChange={(e) => set("journal_name", e.target.value)} />
            </Field>

            <div className="grid grid-cols-2 gap-3">
              <Field label="Publication year" required error={error?.fieldError("pub_year")}>
                <Input
                  type="number"
                  min={1950}
                  max={CURRENT_YEAR + 1}
                  required
                  value={form.pub_year}
                  onChange={(e) => set("pub_year", e.target.value)}
                />
              </Field>
              <Field label="Quartile">
                <Select value={form.quartile} onChange={(e) => set("quartile", e.target.value)}>
                  {["Q1", "Q2", "Q3", "Q4", "N/A"].map((q) => (
                    <option key={q}>{q}</option>
                  ))}
                </Select>
              </Field>
            </div>

            <Field label="DOI" error={error?.fieldError("doi")} hint="Must be unique across ARAMS.">
              <Input value={form.doi} onChange={(e) => set("doi", e.target.value)} placeholder="10.1000/…" />
            </Field>
          </>
        )}

        {type === "AWARD" && (
          <Field label="Award year" required error={error?.fieldError("award_year")}>
            <Input
              type="number"
              min={1950}
              max={CURRENT_YEAR + 1}
              required
              value={form.pub_year}
              onChange={(e) => set("pub_year", e.target.value)}
            />
          </Field>
        )}

        {type === "IP_RECORD" && (
          <p className="rounded-sm bg-[--color-warn-soft] px-2.5 py-2 text-[12px] text-[--color-warn]">
            Without a filing date this record cannot be placed in a KPI period. You can add one
            before submitting.
          </p>
        )}

        {error && !error.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">
            {error.detail}
          </p>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="primary" disabled={mutation.isPending}>
            {mutation.isPending ? "Saving…" : "Save as draft"}
          </Button>
        </div>
      </form>
    </div>
  );
}
