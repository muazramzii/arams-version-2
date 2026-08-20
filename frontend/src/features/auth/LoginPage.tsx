import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "./AuthContext";
import { ApiError } from "../../lib/api";
import { Button, Field, Input } from "../../components/ui";

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await login(email, password);
      navigate("/", { replace: true });
    } catch (err) {
      setError(err instanceof ApiError ? err : new ApiError(0, "Error", "Could not sign in."));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-[--color-paper] px-4">
      <div className="w-full max-w-sm">
        <div className="mb-6">
          <p className="font-mono text-[10px] uppercase tracking-[0.16em] text-[--color-accent]">
            Universiti Tun Hussein Onn Malaysia
          </p>
          <h1 className="mt-1 font-serif text-2xl font-semibold tracking-tight">ARAMS</h1>
          <p className="mt-1 text-[13px] text-[--color-ink-3]">
            Academic Research Analytics and Monitoring System
          </p>
        </div>

        <form
          onSubmit={handleSubmit}
          className="flex flex-col gap-4 border border-[--color-rule] bg-[--color-surface] p-5"
        >
          <Field label="Email address" required error={error?.fieldError("email")}>
            <Input
              type="email"
              name="email"
              autoComplete="username"
              required
              autoFocus
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@uthm.edu.my"
            />
          </Field>

          <Field label="Password" required error={error?.fieldError("password")}>
            <Input
              type="password"
              name="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </Field>

          {/* A failure with no field attached still has to be visible. */}
          {error && !error.errors && (
            <p role="alert" className="text-[12px] text-[--color-crit]">
              {error.detail}
            </p>
          )}

          <Button type="submit" variant="primary" disabled={submitting}>
            {submitting ? "Signing in…" : "Sign in"}
          </Button>
        </form>

        {/*
          No role picker and no demo credentials. ARAMS 1.0 offered both: the
          role was posted by the client, and three working logins were printed
          on the page.
        */}
        <p className="mt-4 text-center text-[11px] text-[--color-ink-3]">
          Trouble signing in? Contact your faculty administrator.
        </p>
      </div>
    </div>
  );
}
