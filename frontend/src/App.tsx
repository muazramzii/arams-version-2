import { BrowserRouter, Navigate, Route, Routes, useLocation } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import type { ReactNode } from "react";

import { AuthProvider, useAuth } from "./features/auth/AuthContext";
import { LoginPage } from "./features/auth/LoginPage";
import { AppLayout } from "./components/AppLayout";
import { DashboardPage } from "./features/dashboard/DashboardPage";
import { ResearchListPage } from "./features/research/ResearchListPage";
import { SubmissionDetailPage } from "./features/submissions/SubmissionDetailPage";
import { ValidationQueuePage } from "./features/validation/ValidationQueuePage";
import { AnalyticsPage } from "./features/analytics/AnalyticsPage";
import { ReportsPage } from "./features/reports/ReportsPage";
import { AuditPage } from "./features/audit/AuditPage";
import { NotificationsPage } from "./features/notifications/NotificationsPage";
import { KpiPage } from "./features/kpi/KpiPage";
import { AdminPage } from "./features/admin/AdminPage";
import { LoadingState } from "./components/ui";
import { ApiError } from "./lib/api";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      // A 401 or 403 will not resolve by asking again.
      retry: (failureCount, error) => {
        if (error instanceof ApiError && error.status >= 400 && error.status < 500) return false;
        return failureCount < 2;
      },
    },
  },
});

/**
 * Route guards keep the interface honest about what a role can reach. They are
 * NOT the authorization — every one of these views calls an API that enforces
 * the same rule server-side. ARAMS 1.0's failure was having only this half.
 */
function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) return <LoadingState rows={6} label="Restoring your session" />;
  if (!user) return <Navigate to="/login" replace state={{ from: location }} />;

  return <>{children}</>;
}

function RedirectIfAuthed({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();

  if (loading) return <LoadingState rows={4} label="Loading" />;
  if (user) return <Navigate to="/" replace />;

  return <>{children}</>;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <Routes>
            <Route
              path="/login"
              element={
                <RedirectIfAuthed>
                  <LoginPage />
                </RedirectIfAuthed>
              }
            />

            <Route
              element={
                <RequireAuth>
                  <AppLayout />
                </RequireAuth>
              }
            >
              <Route index element={<DashboardPage />} />
              <Route path="research" element={<ResearchListPage />} />
              <Route path="submissions/:id" element={<SubmissionDetailPage />} />
              <Route path="validation" element={<ValidationQueuePage />} />
              <Route path="kpi" element={<KpiPage />} />
              <Route path="analytics" element={<AnalyticsPage />} />
              <Route path="admin" element={<AdminPage />} />
              <Route path="reports" element={<ReportsPage />} />
              <Route path="audit" element={<AuditPage />} />
              <Route path="notifications" element={<NotificationsPage />} />
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  );
}
