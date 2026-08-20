import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError } from "../../lib/api";
import {
  Button,
  Card,
  CardHeader,
  Chip,
  EmptyState,
  ErrorState,
  Field,
  LoadingState,
  Select,
  Stat,
  StatRow,
  Table,
  Td,
  Th,
} from "../../components/ui";
import type { Envelope } from "../../types/api";

type FacultyRow = {
  id: number;
  code: string;
  name: string;
  staff_count: number;
  leaders: { id: number; staff_id: number; name: string | null; valid_from: string | null }[];
  needs_tdpp: boolean;
};

type Appointable = {
  id: number;
  full_name: string;
  staff_no: string;
  email: string | null;
  serving: number[];
};

type UserRow = {
  id: number;
  email: string;
  role: string;
  is_active: boolean;
  full_name: string | null;
  staff_no: string | null;
  is_archived: boolean;
};

type DataQuality = {
  records_missing_effective_date: Record<string, number>;
  total_missing: number;
  approvals_without_approver: number;
  archived_staff: number;
};

export function AdminPage() {
  const queryClient = useQueryClient();
  const [appointing, setAppointing] = useState<FacultyRow | null>(null);

  const faculties = useQuery({
    queryKey: ["admin", "faculties"],
    queryFn: () => api.get<Envelope<FacultyRow[]>>("/admin/faculties"),
  });

  const quality = useQuery({
    queryKey: ["admin", "data-quality"],
    queryFn: () => api.get<Envelope<DataQuality>>("/admin/data-quality"),
  });

  const endAppointment = useMutation({
    mutationFn: (id: number) => api.delete(`/admin/faculty-leaders/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin"] }),
  });

  const rows = faculties.data?.data ?? [];
  const uncovered = rows.filter((row) => row.needs_tdpp);

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">Administration</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          Faculty coverage, accounts, and data that needs attention.
        </p>
      </div>

      {quality.data && (
        <StatRow>
          <Stat
            label="Records with no effective date"
            value={quality.data.data.total_missing}
            tone={quality.data.data.total_missing > 0 ? "warn" : undefined}
            hint="Excluded from period KPI"
          />
          <Stat
            label="Approvals with no approver"
            value={quality.data.data.approvals_without_approver}
            tone={quality.data.data.approvals_without_approver > 0 ? "warn" : undefined}
            hint="Migrated from ARAMS 1.0"
          />
          <Stat label="Archived staff" value={quality.data.data.archived_staff} />
          <Stat
            label="Faculties without a TDPP"
            value={uncovered.length}
            tone={uncovered.length > 0 ? "crit" : undefined}
          />
        </StatRow>
      )}

      {/*
        D1 removed the Admin validation fallback, so a faculty with staff and
        no serving TDPP cannot process submissions at all. Appointing someone
        is the only remedy, which is why this sits at the top.
      */}
      {uncovered.length > 0 && (
        <Card className="border-l-2 border-l-[--color-crit]">
          <div className="px-4 py-3">
            <p className="text-[13px] text-[--color-ink-2]">
              <strong>
                {uncovered.length} faculty(ies) have staff but nobody who can validate.
              </strong>{" "}
              Lecturers there cannot submit research until a TDPP is appointed.
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
              {uncovered.map((faculty) => (
                <Button key={faculty.id} size="sm" onClick={() => setAppointing(faculty)}>
                  Appoint for {faculty.code}
                </Button>
              ))}
            </div>
          </div>
        </Card>
      )}

      <Card>
        <CardHeader title="Faculty coverage" />
        {faculties.isLoading && <LoadingState rows={5} label="Loading faculties" />}
        {faculties.isError && (
          <ErrorState error={faculties.error} onRetry={() => faculties.refetch()} />
        )}
        {rows.length > 0 && (
          <Table>
            <thead>
              <tr>
                <Th>Faculty</Th>
                <Th>Staff</Th>
                <Th>Serving TDPP</Th>
                <Th />
              </tr>
            </thead>
            <tbody>
              {rows.map((faculty) => (
                <tr key={faculty.id}>
                  <Td>
                    <span className="font-medium">{faculty.code}</span>
                    <span className="ml-2 text-[--color-ink-3]">{faculty.name}</span>
                  </Td>
                  <Td className="tabular">{faculty.staff_count}</Td>
                  <Td>
                    {faculty.leaders.length === 0 ? (
                      faculty.needs_tdpp ? (
                        <Chip tone="crit">None — cannot validate</Chip>
                      ) : (
                        <span className="text-[--color-ink-3]">—</span>
                      )
                    ) : (
                      <ul className="flex flex-col gap-1">
                        {faculty.leaders.map((leader) => (
                          <li key={leader.id} className="flex items-center gap-2">
                            <span>{leader.name}</span>
                            <button
                              className="text-[11px] text-[--color-ink-3] hover:text-[--color-crit]"
                              onClick={() => endAppointment.mutate(leader.id)}
                              disabled={endAppointment.isPending}
                            >
                              End
                            </button>
                          </li>
                        ))}
                      </ul>
                    )}
                  </Td>
                  <Td>
                    <Button size="sm" onClick={() => setAppointing(faculty)}>
                      Appoint
                    </Button>
                  </Td>
                </tr>
              ))}
            </tbody>
          </Table>
        )}
      </Card>

      <UsersCard />

      {appointing && (
        <AppointDialog
          faculty={appointing}
          onClose={() => setAppointing(null)}
          onDone={() => {
            queryClient.invalidateQueries({ queryKey: ["admin"] });
            setAppointing(null);
          }}
        />
      )}
    </div>
  );
}

function AppointDialog({
  faculty,
  onClose,
  onDone,
}: {
  faculty: FacultyRow;
  onClose: () => void;
  onDone: () => void;
}) {
  const [staffId, setStaffId] = useState("");

  const staff = useQuery({
    queryKey: ["admin", "appointable-staff"],
    queryFn: () => api.get<Envelope<Appointable[]>>("/admin/appointable-staff"),
  });

  const appoint = useMutation({
    mutationFn: () =>
      api.post(`/admin/faculties/${faculty.id}/leaders`, {
        staff_profile_id: Number(staffId),
      }),
    onSuccess: onDone,
  });

  const error = appoint.error instanceof ApiError ? appoint.error : null;
  const candidates = staff.data?.data ?? [];

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4"
      role="dialog"
      aria-modal="true"
      aria-label={`Appoint a TDPP for ${faculty.code}`}
      onClick={(event) => event.target === event.currentTarget && onClose()}
    >
      <div className="flex w-full max-w-md flex-col gap-3 border border-[--color-rule] bg-[--color-surface] p-5">
        <h2 className="font-serif text-lg font-semibold tracking-tight">
          Appoint a TDPP for {faculty.code}
        </h2>

        <p className="text-[13px] text-[--color-ink-2]">
          They will be able to validate submissions from this faculty. This grants faculty scope,
          not the TDPP role itself — only someone who already holds that role can be appointed.
        </p>

        {staff.isLoading && <LoadingState rows={2} />}
        {staff.data && candidates.length === 0 && (
          <EmptyState
            title="No one holds the TDPP role"
            description="Change a user's role to TDPP first, then appoint them here."
          />
        )}

        {candidates.length > 0 && (
          <Field label="Person" required error={error?.fieldError("staff_profile_id")}>
            <Select value={staffId} onChange={(event) => setStaffId(event.target.value)}>
              <option value="">Choose…</option>
              {candidates.map((person) => (
                <option key={person.id} value={person.id}>
                  {person.full_name} ({person.staff_no})
                  {person.serving.length > 0 ? " — already serving elsewhere" : ""}
                </option>
              ))}
            </Select>
          </Field>
        )}

        {error && !error.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">
            {error.detail}
          </p>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant="primary"
            disabled={!staffId || appoint.isPending}
            onClick={() => appoint.mutate()}
          >
            {appoint.isPending ? "Appointing…" : "Appoint"}
          </Button>
        </div>
      </div>
    </div>
  );
}

function UsersCard() {
  const queryClient = useQueryClient();
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");

  const users = useQuery({
    queryKey: ["admin", "users", search, role],
    queryFn: () =>
      api.get<Envelope<UserRow[]>>("/admin/users", {
        search: search || undefined,
        role: role || undefined,
      }),
  });

  const setActivation = useMutation({
    mutationFn: ({ id, active }: { id: number; active: boolean }) =>
      api.put(`/admin/users/${id}/activation`, { is_active: active }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin"] }),
  });

  const setRoleMutation = useMutation({
    mutationFn: ({ id, value }: { id: number; value: string }) =>
      api.put(`/admin/users/${id}/role`, { role: value }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["admin"] }),
  });

  const rows = users.data?.data ?? [];

  return (
    <Card>
      <CardHeader
        title="Accounts"
        action={
          <div className="flex gap-2">
            <input
              aria-label="Search accounts"
              className="rounded-sm border border-[--color-rule-strong] px-2 py-1 text-xs"
              placeholder="Search name or email"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
            <Select
              aria-label="Filter by role"
              className="w-auto py-1 text-xs"
              value={role}
              onChange={(event) => setRole(event.target.value)}
            >
              <option value="">All roles</option>
              <option value="Lecturer">Lecturer</option>
              <option value="TDPP">TDPP</option>
              <option value="Admin">Admin</option>
            </Select>
          </div>
        }
      />

      {users.isLoading && <LoadingState rows={5} label="Loading accounts" />}
      {users.isError && <ErrorState error={users.error} onRetry={() => users.refetch()} />}
      {users.data && rows.length === 0 && <EmptyState title="No accounts match" />}

      {rows.length > 0 && (
        <Table>
          <thead>
            <tr>
              <Th>Name</Th>
              <Th>Email</Th>
              <Th>Role</Th>
              <Th>Status</Th>
              <Th />
            </tr>
          </thead>
          <tbody>
            {rows.map((user) => (
              <tr key={user.id}>
                <Td>
                  {user.full_name ?? "—"}
                  {user.is_archived && (
                    <span className="ml-2">
                      <Chip>Archived</Chip>
                    </span>
                  )}
                </Td>
                <Td className="text-[--color-ink-3]">{user.email}</Td>
                <Td>
                  <Select
                    aria-label={`Role for ${user.email}`}
                    className="w-auto py-0.5 text-xs"
                    value={user.role}
                    onChange={(event) =>
                      setRoleMutation.mutate({ id: user.id, value: event.target.value })
                    }
                  >
                    <option value="Lecturer">Lecturer</option>
                    <option value="TDPP">TDPP</option>
                    <option value="Admin">Admin</option>
                  </Select>
                </Td>
                <Td>
                  {user.is_active ? <Chip tone="good">Active</Chip> : <Chip>Inactive</Chip>}
                </Td>
                <Td>
                  <Button
                    size="sm"
                    variant={user.is_active ? "secondary" : "primary"}
                    disabled={setActivation.isPending}
                    onClick={() =>
                      setActivation.mutate({ id: user.id, active: !user.is_active })
                    }
                  >
                    {user.is_active ? "Deactivate" : "Activate"}
                  </Button>
                </Td>
              </tr>
            ))}
          </tbody>
        </Table>
      )}
    </Card>
  );
}
