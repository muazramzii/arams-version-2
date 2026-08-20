import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from "react";
import { api, tokenStore } from "../../lib/api";
import type { AuthUser, Envelope, Role } from "../../types/api";

type AuthState = {
  user: AuthUser | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  /** Convenience only — the API is the authority on every one of these. */
  can: {
    validate: boolean;
    manageUsers: boolean;
    setInstitutionTargets: boolean;
  };
};

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  // Restore the session on first paint if a token is present.
  useEffect(() => {
    if (!tokenStore.get()) {
      setLoading(false);
      return;
    }

    api
      .get<Envelope<AuthUser>>("/auth/me")
      .then((res) => setUser(res.data))
      .catch(() => {
        tokenStore.clear();
        setUser(null);
      })
      .finally(() => setLoading(false));
  }, []);

  const value = useMemo<AuthState>(() => {
    const role: Role | undefined = user?.role;

    return {
      user,
      loading,
      login: async (email, password) => {
        const res = await api.post<Envelope<{ token: string; user: AuthUser }>>("/auth/login", {
          email,
          password,
        });
        tokenStore.set(res.data.token);
        setUser(res.data.user);
      },
      logout: async () => {
        try {
          await api.post("/auth/logout");
        } finally {
          tokenStore.clear();
          setUser(null);
        }
      },
      can: {
        /**
         * D1: only a TDPP holding a current appointment. Role alone is not
         * enough, which is why this reads the appointment list rather than
         * the role string.
         */
        validate: role === "TDPP" && (user?.validates_faculties.length ?? 0) > 0,
        manageUsers: role === "Admin",
        setInstitutionTargets: role === "Admin",
      },
    };
  }, [user, loading]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthState {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider");
  }

  return context;
}
