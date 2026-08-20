import { useState } from "react";
import { useParams } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError } from "../../lib/api";
import {
  Button,
  Card,
  CardHeader,
  Chip,
  ErrorState,
  Field,
  LoadingState,
  StatusBadge,
  Textarea,
} from "../../components/ui";
import type { Envelope, Submission, Transition } from "../../types/api";

/** Which transitions need a reason before they can be sent. */
const ENDPOINT: Record<string, { path: string; needsRemarks: boolean; verb: string }> = {
  SUBMITTED: { path: "submit", needsRemarks: false, verb: "Submit for validation" },
  WITHDRAWN: { path: "withdraw", needsRemarks: false, verb: "Withdraw" },
  UNDER_REVIEW: { path: "claim", needsRemarks: false, verb: "Start review" },
  APPROVED: { path: "approve", needsRemarks: false, verb: "Approve" },
  REJECTED: { path: "reject", needsRemarks: true, verb: "Reject" },
  REVISION_REQUESTED: { path: "request-revision", needsRemarks: true, verb: "Request revision" },
};

export function SubmissionDetailPage() {
  const { id } = useParams<{ id: string }>();
  const queryClient = useQueryClient();

  const [pending, setPending] = useState<Transition | null>(null);
  const [remarks, setRemarks] = useState("");

  const submission = useQuery({
    queryKey: ["submission", id],
    queryFn: () => api.get<Envelope<Submission>>(`/submissions/${id}`),
    enabled: Boolean(id),
  });

  const transitions = useQuery({
    queryKey: ["submission", id, "transitions"],
    queryFn: () => api.get<Envelope<Transition[]>>(`/submissions/${id}/transitions`),
    enabled: Boolean(id),
  });

  const act = useMutation({
    mutationFn: ({ path, body }: { path: string; body?: unknown }) =>
      api.post(`/submissions/${id}/${path}`, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["submission", id] });
      queryClient.invalidateQueries({ queryKey: ["submissions"] });
      queryClient.invalidateQueries({ queryKey: ["research-records"] });
      setPending(null);
      setRemarks("");
    },
  });

  if (submission.isLoading) return <LoadingState rows={6} label="Loading submission" />;
  if (submission.isError) {
    return <ErrorState error={submission.error} onRetry={() => submission.refetch()} />;
  }

  const data = submission.data!.data;
  const record = data.record;
  const actions = transitions.data?.data ?? [];
  const error = act.error instanceof ApiError ? act.error : null;

  function runAction(transition: Transition) {
    const config = ENDPOINT[transition.to_status];
    if (!config) return;

    // Anything irreversible or requiring a reason gets a confirm step.
    if (config.needsRemarks || transition.to_status === "APPROVED" || transition.to_status === "WITHDRAWN") {
      setPending(transition);
      return;
    }

    act.mutate({ path: config.path });
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={data.status} />
            <Chip>Revision {data.current_revision}</Chip>
            {data.origin === "MIGRATED_V1" && <Chip tone="warn">Migrated from ARAMS 1.0</Chip>}
          </div>
          <h1 className="mt-2 font-serif text-xl font-semibold tracking-tight">
            {record?.display_title ?? `Submission #${data.id}`}
          </h1>
          <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
            {record?.owner?.full_name} · {record?.type?.replace("_", " ").toLowerCase()}
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          {actions.map((transition) => (
            <Button
              key={transition.to_status}
              variant={
                transition.to_status === "APPROVED"
                  ? "primary"
                  : transition.to_status === "REJECTED"
                    ? "danger"
                    : "secondary"
              }
              disabled={act.isPending}
              onClick={() => runAction(transition)}
            >
              {transition.label}
            </Button>
          ))}
        </div>
      </div>

      {/* Workflow refusals arrive as 422 with a readable reason — show it. */}
      {error && (
        <Card className="border-l-2 border-l-[--color-crit]">
          <p role="alert" className="px-4 py-3 text-[13px] text-[--color-crit]">
            {error.detail}
          </p>
        </Card>
      )}

      {record?.needs_date_backfill && (
        <Card className="border-l-2 border-l-[--color-warn]">
          <p className="px-4 py-3 text-[13px] text-[--color-ink-2]">
            This record has no effective date, so it cannot be credited to a KPI period. It still
            counts in totals.
          </p>
        </Card>
      )}

      <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
        <Card>
          <CardHeader title="Validation history" />
          {(!data.reviews || data.reviews.length === 0) && (
            <p className="px-4 py-6 text-center text-[13px] text-[--color-ink-3]">
              No decisions recorded yet.
            </p>
          )}
          {data.reviews && data.reviews.length > 0 && (
            <ol className="divide-y divide-[--color-rule]">
              {data.reviews.map((review) => (
                <li key={review.id} className="px-4 py-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <Chip
                      tone={
                        review.decision === "APPROVED"
                          ? "good"
                          : review.decision === "REJECTED"
                            ? "crit"
                            : "warn"
                      }
                    >
                      {review.decision.replace("_", " ")}
                    </Chip>
                    <span className="font-mono text-[10px] uppercase tracking-wider text-[--color-ink-3]">
                      Revision {review.revision_no}
                    </span>
                    <span className="tabular text-[11px] text-[--color-ink-3]">
                      {review.decided_at?.slice(0, 10)}
                    </span>
                  </div>

                  <p className="mt-1 text-[12px] text-[--color-ink-2]">
                    {review.reviewer_unknown ? (
                      /* 108 migrated ARAMS 1.0 approvals recorded no approver.
                         The loss is permanent, so it is stated rather than
                         rendered as an empty field. */
                      <em className="text-[--color-warn]">
                        Reviewer not recorded — migrated from ARAMS 1.0
                      </em>
                    ) : (
                      <>
                        {review.reviewer?.name}{" "}
                        <span className="text-[--color-ink-3]">({review.reviewer?.role})</span>
                      </>
                    )}
                  </p>

                  {review.remarks && (
                    <p className="mt-1.5 border-l-2 border-[--color-rule] pl-2.5 text-[13px]">
                      {review.remarks}
                    </p>
                  )}
                </li>
              ))}
            </ol>
          )}
        </Card>

        <Card>
          <CardHeader title="Details" />
          <dl className="divide-y divide-[--color-rule] text-[13px]">
            <Row label="Effective date" value={record?.effective_date ?? "Not set"} />
            <Row label="Date precision" value={record?.effective_date_precision ?? "—"} />
            <Row label="First submitted" value={data.first_submitted_at?.slice(0, 10) ?? "—"} />
            <Row label="Last submitted" value={data.submitted_at?.slice(0, 10) ?? "—"} />
            <Row label="Decided" value={data.decided_at?.slice(0, 10) ?? "—"} />
            <Row
              label="Attributed faculty"
              value={record?.attributed_faculty_id ? `#${record.attributed_faculty_id}` : "Set on approval"}
            />
          </dl>
        </Card>
      </div>

      {pending && (
        <ConfirmDialog
          transition={pending}
          remarks={remarks}
          setRemarks={setRemarks}
          pending={act.isPending}
          error={error}
          onCancel={() => {
            setPending(null);
            setRemarks("");
          }}
          onConfirm={() => {
            const config = ENDPOINT[pending.to_status];
            act.mutate({
              path: config.path,
              body: config.needsRemarks || remarks ? { remarks } : undefined,
            });
          }}
        />
      )}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-3 px-4 py-2">
      <dt className="text-[--color-ink-3]">{label}</dt>
      <dd className="tabular text-right font-medium">{value}</dd>
    </div>
  );
}

function ConfirmDialog({
  transition,
  remarks,
  setRemarks,
  pending,
  error,
  onCancel,
  onConfirm,
}: {
  transition: Transition;
  remarks: string;
  setRemarks: (value: string) => void;
  pending: boolean;
  error: ApiError | null;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  const config = ENDPOINT[transition.to_status];
  const blocked = config.needsRemarks && remarks.trim().length < 3;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
      role="dialog"
      aria-modal="true"
      aria-label={transition.label}
      onClick={(e) => e.target === e.currentTarget && onCancel()}
    >
      <div className="flex w-full max-w-md flex-col gap-3 border border-[--color-rule] bg-[--color-surface] p-5">
        <h2 className="font-serif text-lg font-semibold tracking-tight">{transition.label}</h2>

        <p className="text-[13px] text-[--color-ink-2]">
          {transition.to_status === "APPROVED" &&
            "This record will count toward official analytics, KPI and reporting."}
          {transition.to_status === "REJECTED" &&
            "Rejection is final. If the work belongs in the repository but needs correcting, request a revision instead."}
          {transition.to_status === "REVISION_REQUESTED" &&
            "The author will be able to edit and resubmit. Explain what needs changing."}
          {transition.to_status === "WITHDRAWN" && "This will remove the submission from the queue."}
        </p>

        {(config.needsRemarks || transition.to_status === "APPROVED") && (
          <Field
            label="Remarks"
            required={config.needsRemarks}
            error={error?.fieldError("remarks")}
            hint={config.needsRemarks ? "At least 3 characters." : "Optional."}
          >
            <Textarea
              value={remarks}
              autoFocus
              onChange={(e) => setRemarks(e.target.value)}
              placeholder={
                config.needsRemarks ? "What does the author need to change?" : "Any notes for the record"
              }
            />
          </Field>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button variant="ghost" onClick={onCancel}>
            Cancel
          </Button>
          <Button
            variant={transition.to_status === "REJECTED" ? "danger" : "primary"}
            disabled={pending || blocked}
            onClick={onConfirm}
          >
            {pending ? "Working…" : transition.label}
          </Button>
        </div>
      </div>
    </div>
  );
}
