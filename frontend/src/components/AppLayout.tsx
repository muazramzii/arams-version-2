import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { useQuery } from "@tanstack/react-query";
import { useAuth } from "../features/auth/AuthContext";
import { api } from "../lib/api";
import { Button, cn } from "./ui";
import type { Envelope, Notification, Role } from "../types/api";

type NavItem = { to: string; label: string; roles: Role[]; requiresAppointment?: boolean };

/**
 * Navigation is a convenience, not a control. Hiding a link keeps the UI
 * honest about what a role can do; the API refuses the request regardless.
 * ARAMS 1.0 treated navigation *as* the control, which is why typing a URL
 * was enough to reach the admin dashboard.
 */
const NAV: NavItem[] = [
  { to: "/", label: "Dashboard", roles: ["Lecturer", "TDPP", "Admin"] },
  { to: "/research", label: "My Research", roles: ["Lecturer", "TDPP", "Admin"] },
  { to: "/validation", label: "Validation Queue", roles: ["TDPP"], requiresAppointment: true },
  { to: "/kpi", label: "KPI", roles: ["Lecturer", "TDPP"] },
  { to: "/analytics", label: "Analytics", roles: ["Lecturer", "TDPP", "Admin"] },
  { to: "/reports", label: "Reports", roles: ["Lecturer", "TDPP", "Admin"] },
  { to: "/audit", label: "Activity Log", roles: ["Lecturer", "TDPP", "Admin"] },
  { to: "/admin", label: "Administration", roles: ["Admin"] },
];

export function AppLayout() {
  const { user, logout, can } = useAuth();
  const navigate = useNavigate();

  const { data: notifications } = useQuery({
    queryKey: ["notifications", "unread"],
    queryFn: () =>
      api.get<Envelope<Notification[]> & { meta?: { unread: number } }>("/notifications", {
        unread_only: true,
        limit: 5,
      }),
    refetchInterval: 60_000,
  });

  const unread = (notifications?.meta?.unread as number) ?? 0;

  const items = NAV.filter((item) => {
    if (!user || !item.roles.includes(user.role)) return false;
    if (item.requiresAppointment && !can.validate) return false;
    return true;
  });

  return (
    <div className="grid min-h-screen grid-cols-[220px_1fr] max-md:grid-cols-1">
      <aside className="flex flex-col border-r border-[--color-rule] bg-[--color-surface] max-md:border-b max-md:border-r-0">
        <div className="border-b border-[--color-rule] px-4 py-3.5">
          <div className="font-serif text-lg font-semibold leading-none tracking-tight">ARAMS</div>
          <div className="mt-1 font-mono text-[9px] uppercase tracking-[0.14em] text-[--color-ink-3]">
            UTHM
          </div>
        </div>

        <nav className="flex flex-1 flex-col gap-0.5 p-2">
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === "/"}
              className={({ isActive }) =>
                cn(
                  "rounded-sm px-2.5 py-1.5 text-[13px] transition-colors",
                  isActive
                    ? "bg-[--color-accent-soft] font-medium text-[--color-accent]"
                    : "text-[--color-ink-2] hover:bg-[--color-surface-2]",
                )
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-[--color-rule] p-3">
          <div className="truncate text-[12px] font-medium">
            {user?.staff?.full_name ?? user?.email}
          </div>
          <div className="mt-0.5 flex items-center gap-1.5">
            <span className="font-mono text-[9px] uppercase tracking-wider text-[--color-ink-3]">
              {user?.role}
            </span>
            {user?.faculty && (
              <span className="font-mono text-[9px] uppercase tracking-wider text-[--color-ink-3]">
                · {user.faculty.code}
              </span>
            )}
          </div>
          {/* A TDPP with no current appointment cannot validate — say so. */}
          {user?.role === "TDPP" && !can.validate && (
            <p className="mt-1.5 text-[10px] leading-snug text-[--color-warn]">
              No active appointment, so you cannot validate submissions yet.
            </p>
          )}
          <Button
            size="sm"
            variant="ghost"
            className="mt-2 w-full"
            onClick={async () => {
              await logout();
              navigate("/login", { replace: true });
            }}
          >
            Sign out
          </Button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-col">
        <header className="flex items-center justify-end gap-3 border-b border-[--color-rule] bg-[--color-surface] px-5 py-2">
          <NavLink
            to="/notifications"
            className="flex items-center gap-1.5 text-[12px] text-[--color-ink-2] hover:text-[--color-accent]"
          >
            Notifications
            {unread > 0 && (
              <span className="tabular rounded-full bg-[--color-crit] px-1.5 py-0.5 font-mono text-[9px] text-white">
                {unread}
              </span>
            )}
          </NavLink>
        </header>

        <main className="min-w-0 flex-1 p-5">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
