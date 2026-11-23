import { beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, within } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { MemoryRouter } from "react-router-dom";
import { AppLayout } from "./AppLayout";
import { AuthProvider } from "../features/auth/AuthContext";
import { api, tokenStore } from "../lib/api";
import type { AuthUser, Role } from "../types/api";

// Only the network edge is replaced. AuthProvider and AppLayout are the real
// components, so `can.validate` (locked decision D1: a TDPP validates only with
// a current appointment) is derived by the production code from the user's role
// and appointments, not re-implemented here.
vi.mock("../lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../lib/api")>();
  return { ...actual, api: { ...actual.api, get: vi.fn() } };
});

function makeUser(role: Role, validatesFaculties: number[] = []): AuthUser {
  return {
    id: 1,
    email: `${role.toLowerCase()}@example.test`,
    role,
    staff: { id: 1, staff_no: "T0001", full_name: `Test ${role}`, title: null },
    faculty: { id: 1, code: "TST", name: "Test Faculty" },
    validates_faculties: validatesFaculties,
  };
}

/** Answers the two requests AppLayout and AuthProvider make; anything else is a bug. */
function serve(user: AuthUser | null) {
  if (user) tokenStore.set("test");

  vi.mocked(api.get).mockImplementation(async (path: string) => {
    if (path === "/auth/me") return { data: user };
    if (path === "/notifications") return { data: [], meta: { unread: 0 } };
    throw new Error(`Unexpected request in test: ${path}`);
  });
}

async function renderLayout(user: AuthUser | null) {
  serve(user);

  render(
    <QueryClientProvider
      client={new QueryClient({ defaultOptions: { queries: { retry: false, refetchInterval: false } } })}
    >
      <MemoryRouter>
        <AuthProvider>
          <AppLayout />
        </AuthProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  );

  // The session is restored asynchronously; wait for the first link to appear.
  if (user) await screen.findByRole("link", { name: "Dashboard" });
}

function navLabels(): string[] {
  return within(screen.getByRole("navigation"))
    .queryAllByRole("link")
    .map((link) => link.textContent ?? "");
}

/**
 * Asserts the exact set of sidebar links, ignoring order. Exact rather than
 * "contains": adding a navigation item for any role must fail here until its
 * visibility has been decided on purpose.
 */
function expectNav(expected: string[]) {
  expect([...navLabels()].sort()).toEqual([...expected].sort());
}

const PERSONAL = ["Dashboard", "My Research", "My Profile"];
const INSTITUTIONAL_VIEWS = ["Analytics", "Reports", "Activity Log"];

describe("AppLayout navigation by role", () => {
  beforeEach(() => {
    localStorage.clear();
    vi.mocked(api.get).mockReset();
  });

  it("shows a Lecturer their own work, KPI and the shared views, but no validation or administration", async () => {
    await renderLayout(makeUser("Lecturer"));

    expectNav([...PERSONAL, "KPI", ...INSTITUTIONAL_VIEWS]);
  });

  it("shows a TDPP with a current appointment the validation queue as well, but not administration", async () => {
    await renderLayout(makeUser("TDPP", [1]));

    expectNav([...PERSONAL, "Validation Queue", "KPI", ...INSTITUTIONAL_VIEWS]);
    expect(screen.queryByText(/No active appointment/)).not.toBeInTheDocument();
  });

  it("hides the validation queue from a TDPP with no appointment, and tells them why (D1)", async () => {
    await renderLayout(makeUser("TDPP", []));

    expectNav([...PERSONAL, "KPI", ...INSTITUTIONAL_VIEWS]);
    expect(screen.getByText(/No active appointment/)).toBeInTheDocument();
  });

  it("shows an Admin administration but never the validation queue (D1) or KPI", async () => {
    await renderLayout(makeUser("Admin"));

    expectNav([...PERSONAL, ...INSTITUTIONAL_VIEWS, "Administration"]);
  });

  it("shows no navigation at all when nobody is signed in", async () => {
    await renderLayout(null);

    expectNav([]);
  });
});
