import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link } from "react-router-dom";
import { api } from "../../lib/api";
import {
  Button,
  Card,
  CardHeader,
  Chip,
  EmptyState,
  ErrorState,
  LoadingState,
} from "../../components/ui";
import type { Envelope, Notification } from "../../types/api";

/**
 * Messages are composed here from type + data, rather than read out of the
 * database as finished sentences. That is what makes them translatable into
 * Malay and filterable — ARAMS 1.0 stored a CONCAT-built English string.
 */
function render(notification: Notification): { title: string; body?: string } {
  const data = notification.data as Record<string, string | number | boolean | null>;

  switch (notification.type) {
    case "submission.received":
      return {
        title: `New submission from ${data.author ?? "a lecturer"}`,
        body: String(data.title ?? ""),
      };
    case "submission.approved":
      return { title: "Your submission was approved", body: String(data.title ?? "") };
    case "submission.rejected":
      return {
        title: "Your submission was rejected",
        body: data.remarks ? String(data.remarks) : String(data.title ?? ""),
      };
    case "submission.revision_requested":
      return {
        title: "A revision was requested",
        body: data.remarks ? String(data.remarks) : String(data.title ?? ""),
      };
    case "kpi.assigned":
      return {
        title: `New KPI target: ${data.measure ?? ""} (${data.target_value ?? ""})`,
        body: data.deadline ? `Due ${data.deadline}` : undefined,
      };
    case "faculty.no_validator":
      return {
        title: `${data.faculty_code} has no serving TDPP`,
        body: "Lecturers there cannot submit research for validation.",
      };
    default:
      return { title: notification.type };
  }
}

export function NotificationsPage() {
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ["notifications", "all"],
    queryFn: () => api.get<Envelope<Notification[]>>("/notifications", { limit: 50 }),
  });

  const markAll = useMutation({
    mutationFn: () => api.post("/notifications/read-all"),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const markOne = useMutation({
    mutationFn: (id: string) => api.post(`/notifications/${id}/read`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["notifications"] }),
  });

  const items = query.data?.data ?? [];
  const unread = items.filter((item) => !item.read_at).length;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-end justify-between gap-3">
        <div>
          <h1 className="font-serif text-xl font-semibold tracking-tight">Notifications</h1>
          <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
            {unread > 0 ? `${unread} unread` : "All caught up."}
          </p>
        </div>
        {unread > 0 && (
          <Button size="sm" onClick={() => markAll.mutate()} disabled={markAll.isPending}>
            Mark all as read
          </Button>
        )}
      </div>

      <Card>
        <CardHeader title="Recent" />
        {query.isLoading && <LoadingState rows={5} label="Loading notifications" />}
        {query.isError && <ErrorState error={query.error} onRetry={() => query.refetch()} />}
        {query.data && items.length === 0 && (
          <EmptyState
            title="No notifications"
            description="You will be told when something needs you."
          />
        )}
        {items.length > 0 && (
          <ul className="divide-y divide-[--color-rule]">
            {items.map((notification) => {
              const { title, body } = render(notification);

              return (
                <li
                  key={notification.id}
                  className={
                    notification.read_at ? "px-4 py-3" : "bg-[--color-accent-soft]/40 px-4 py-3"
                  }
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="text-[13px] font-medium">{title}</p>
                      {body && <p className="mt-0.5 text-[12px] text-[--color-ink-2]">{body}</p>}
                      <div className="mt-1 flex items-center gap-2">
                        <span className="tabular font-mono text-[10px] text-[--color-ink-3]">
                          {notification.created_at?.slice(0, 16).replace("T", " ")}
                        </span>
                        {!notification.read_at && <Chip tone="warn">Unread</Chip>}
                      </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                      {notification.action_url && (
                        <Link
                          to={notification.action_url}
                          className="text-[12px] text-[--color-accent] hover:underline"
                        >
                          Open
                        </Link>
                      )}
                      {!notification.read_at && (
                        <button
                          className="text-[12px] text-[--color-ink-3] hover:text-[--color-ink]"
                          onClick={() => markOne.mutate(notification.id)}
                        >
                          Mark read
                        </button>
                      )}
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </Card>
    </div>
  );
}
