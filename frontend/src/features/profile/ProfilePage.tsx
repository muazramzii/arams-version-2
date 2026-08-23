import { useEffect, useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, tokenStore } from "../../lib/api";
import {
  Button,
  Card,
  CardHeader,
  Chip,
  ErrorState,
  Field,
  Input,
  LoadingState,
  Select,
} from "../../components/ui";
import type { Envelope } from "../../types/api";

type ExternalId = {
  provider_id: number;
  provider_code: string | null;
  provider: string | null;
  value: string;
};

type Profile = {
  id: number;
  staff_no: string;
  full_name: string;
  title: string | null;
  phone: string | null;
  specialisation: string | null;
  cv_url: string | null;
  has_photo: boolean;
  managerial_position: boolean;
  position_id: number | null;
  grade_id: number | null;
  researcher_status_id: number | null;
  faculty: { id: number; code: string; name: string; since: string | null } | null;
  external_ids: ExternalId[];
};

type Reference = { id: number; code: string; label: string };

type ReferenceData = {
  positions: Reference[];
  researcher_statuses: Reference[];
  external_id_providers: Reference[];
};

export function ProfilePage() {
  const queryClient = useQueryClient();

  const profile = useQuery({
    queryKey: ["profile"],
    queryFn: () => api.get<Envelope<Profile>>("/profile"),
  });

  if (profile.isLoading) return <LoadingState rows={6} label="Loading your profile" />;
  if (profile.isError) return <ErrorState error={profile.error} onRetry={() => profile.refetch()} />;

  const data = profile.data!.data;

  return (
    <div className="flex flex-col gap-5">
      <div>
        <h1 className="font-serif text-xl font-semibold tracking-tight">My Profile</h1>
        <p className="mt-0.5 text-[13px] text-[--color-ink-3]">
          {data.full_name} · {data.staff_no}
          {data.faculty && ` · ${data.faculty.code}`}
        </p>
      </div>

      <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
        <div className="flex flex-col gap-5">
          <DetailsCard
            profile={data}
            onSaved={() => queryClient.invalidateQueries({ queryKey: ["profile"] })}
          />
          <ExternalIdsCard
            profile={data}
            onSaved={() => queryClient.invalidateQueries({ queryKey: ["profile"] })}
          />
        </div>

        <div className="flex flex-col gap-5">
          <PhotoCard
            hasPhoto={data.has_photo}
            onChanged={() => queryClient.invalidateQueries({ queryKey: ["profile"] })}
          />
          <AffiliationCard profile={data} />
          <PasswordCard />
        </div>
      </div>
    </div>
  );
}

function DetailsCard({ profile, onSaved }: { profile: Profile; onSaved: () => void }) {
  const [form, setForm] = useState({
    full_name: profile.full_name,
    title: profile.title ?? "",
    phone: profile.phone ?? "",
    specialisation: profile.specialisation ?? "",
    cv_url: profile.cv_url ?? "",
    position_id: profile.position_id ? String(profile.position_id) : "",
    researcher_status_id: profile.researcher_status_id ? String(profile.researcher_status_id) : "",
  });
  const [saved, setSaved] = useState(false);

  const references = useQuery({
    queryKey: ["reference-data"],
    queryFn: () => api.get<Envelope<ReferenceData>>("/reference-data"),
    staleTime: 10 * 60_000,
  });

  const save = useMutation({
    mutationFn: () =>
      api.put<Envelope<Profile>>("/profile", {
        full_name: form.full_name,
        title: form.title || null,
        phone: form.phone || null,
        specialisation: form.specialisation || null,
        cv_url: form.cv_url || null,
        position_id: form.position_id ? Number(form.position_id) : null,
        researcher_status_id: form.researcher_status_id ? Number(form.researcher_status_id) : null,
      }),
    onSuccess: () => {
      setSaved(true);
      onSaved();
    },
  });

  // Success feedback that clears itself, rather than a toast the user must dismiss.
  useEffect(() => {
    if (!saved) return;
    const timer = setTimeout(() => setSaved(false), 3000);
    return () => clearTimeout(timer);
  }, [saved]);

  const error = save.error instanceof ApiError ? save.error : null;
  const refs = references.data?.data;

  return (
    <Card>
      <CardHeader
        title="Details"
        action={saved ? <Chip tone="good">Saved</Chip> : undefined}
      />
      <form
        className="flex flex-col gap-3 p-4"
        onSubmit={(event) => {
          event.preventDefault();
          save.mutate();
        }}
      >
        <div className="grid grid-cols-[100px_1fr] gap-3">
          <Field label="Title">
            <Input
              value={form.title}
              placeholder="Dr."
              onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
            />
          </Field>
          <Field label="Full name" required error={error?.fieldError("full_name")}>
            <Input
              required
              value={form.full_name}
              onChange={(e) => setForm((f) => ({ ...f, full_name: e.target.value }))}
            />
          </Field>
        </div>

        <Field label="Field of expertise" error={error?.fieldError("specialisation")}>
          <Input
            value={form.specialisation}
            placeholder="e.g. Machine learning, water resources engineering"
            onChange={(e) => setForm((f) => ({ ...f, specialisation: e.target.value }))}
          />
        </Field>

        <div className="grid grid-cols-2 gap-3">
          <Field label="Phone" error={error?.fieldError("phone")}>
            <Input
              value={form.phone}
              onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
            />
          </Field>
          <Field label="CV or profile link" error={error?.fieldError("cv_url")}>
            <Input
              type="url"
              value={form.cv_url}
              placeholder="https://community.uthm.edu.my/…"
              onChange={(e) => setForm((f) => ({ ...f, cv_url: e.target.value }))}
            />
          </Field>
        </div>

        {refs && (
          <div className="grid grid-cols-2 gap-3">
            <Field label="Position">
              <Select
                value={form.position_id}
                onChange={(e) => setForm((f) => ({ ...f, position_id: e.target.value }))}
              >
                <option value="">— not set —</option>
                {refs.positions.map((option) => (
                  <option key={option.id} value={option.id}>{option.label}</option>
                ))}
              </Select>
            </Field>
            <Field label="Researcher status">
              <Select
                value={form.researcher_status_id}
                onChange={(e) => setForm((f) => ({ ...f, researcher_status_id: e.target.value }))}
              >
                <option value="">— not set —</option>
                {refs.researcher_statuses.map((option) => (
                  <option key={option.id} value={option.id}>{option.label}</option>
                ))}
              </Select>
            </Field>
          </div>
        )}

        {error && !error.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">{error.detail}</p>
        )}

        <div className="flex justify-end">
          <Button type="submit" variant="primary" disabled={save.isPending}>
            {save.isPending ? "Saving…" : "Save changes"}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function ExternalIdsCard({ profile, onSaved }: { profile: Profile; onSaved: () => void }) {
  const references = useQuery({
    queryKey: ["reference-data"],
    queryFn: () => api.get<Envelope<ReferenceData>>("/reference-data"),
    staleTime: 10 * 60_000,
  });

  const [values, setValues] = useState<Record<number, string>>(() =>
    Object.fromEntries(profile.external_ids.map((id) => [id.provider_id, id.value])),
  );
  const [saved, setSaved] = useState(false);

  const save = useMutation({
    mutationFn: () =>
      api.put("/profile/external-ids", {
        ids: Object.entries(values)
          .filter(([, value]) => value.trim() !== "")
          .map(([providerId, value]) => ({
            provider_id: Number(providerId),
            value: value.trim(),
          })),
      }),
    onSuccess: () => {
      setSaved(true);
      onSaved();
    },
  });

  useEffect(() => {
    if (!saved) return;
    const timer = setTimeout(() => setSaved(false), 3000);
    return () => clearTimeout(timer);
  }, [saved]);

  const error = save.error instanceof ApiError ? save.error : null;
  const providers = references.data?.data.external_id_providers ?? [];

  return (
    <Card>
      <CardHeader
        title="Researcher identifiers"
        action={saved ? <Chip tone="good">Saved</Chip> : undefined}
      />
      <form
        className="flex flex-col gap-3 p-4"
        onSubmit={(event) => {
          event.preventDefault();
          save.mutate();
        }}
      >
        <p className="text-[12px] text-[--color-ink-3]">
          Used to match your work in Scopus and Web of Science. Each identifier can belong to only
          one researcher.
        </p>

        {references.isLoading && <LoadingState rows={3} />}

        {providers.map((provider) => (
          <Field key={provider.id} label={provider.label}>
            <Input
              value={values[provider.id] ?? ""}
              onChange={(e) =>
                setValues((v) => ({ ...v, [provider.id]: e.target.value }))
              }
            />
          </Field>
        ))}

        {error && (
          <p role="alert" className="text-[12px] text-[--color-crit]">{error.detail}</p>
        )}

        <div className="flex justify-end">
          <Button type="submit" variant="primary" disabled={save.isPending}>
            {save.isPending ? "Saving…" : "Save identifiers"}
          </Button>
        </div>
      </form>
    </Card>
  );
}

function PhotoCard({ hasPhoto, onChanged }: { hasPhoto: boolean; onChanged: () => void }) {
  const fileInput = useRef<HTMLInputElement>(null);
  const [preview, setPreview] = useState<string | null>(null);

  // The photo route needs the bearer token, so it is fetched rather than
  // pointed at with a plain <img src>.
  useEffect(() => {
    if (!hasPhoto) {
      setPreview(null);
      return;
    }

    let objectUrl: string | null = null;
    let cancelled = false;

    api
      .download("/profile/photo")
      .then((blob) => {
        if (cancelled) return;
        objectUrl = URL.createObjectURL(blob);
        setPreview(objectUrl);
      })
      .catch(() => setPreview(null));

    return () => {
      cancelled = true;
      if (objectUrl) URL.revokeObjectURL(objectUrl);
    };
  }, [hasPhoto]);

  const upload = useMutation({
    mutationFn: async (file: File) => {
      const body = new FormData();
      body.append("photo", file);

      const response = await fetch("/api/v1/profile/photo", {
        method: "POST",
        headers: { Accept: "application/json", Authorization: `Bearer ${tokenStore.get()}` },
        body,
      });

      const payload = await response.json();
      if (!response.ok) {
        throw new ApiError(response.status, payload.title, payload.detail, payload.errors);
      }
      return payload;
    },
    onSuccess: onChanged,
  });

  const remove = useMutation({
    mutationFn: () => api.delete("/profile/photo"),
    onSuccess: onChanged,
  });

  const error = upload.error instanceof ApiError ? upload.error : null;

  return (
    <Card>
      <CardHeader title="Photo" />
      <div className="flex flex-col items-center gap-3 p-4">
        <div className="flex size-28 items-center justify-center overflow-hidden rounded-sm border border-[--color-rule] bg-[--color-surface-2]">
          {preview ? (
            <img src={preview} alt="Your profile photo" className="size-full object-cover" />
          ) : (
            <span className="text-[11px] text-[--color-ink-3]">No photo</span>
          )}
        </div>

        <input
          ref={fileInput}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(event) => {
            const file = event.target.files?.[0];
            if (file) upload.mutate(file);
            event.target.value = "";
          }}
        />

        <div className="flex gap-2">
          <Button size="sm" disabled={upload.isPending} onClick={() => fileInput.current?.click()}>
            {upload.isPending ? "Uploading…" : hasPhoto ? "Replace" : "Upload"}
          </Button>
          {hasPhoto && (
            <Button size="sm" variant="ghost" disabled={remove.isPending}
              onClick={() => remove.mutate()}>
              Remove
            </Button>
          )}
        </div>

        {error && (
          <p role="alert" className="text-center text-[11px] text-[--color-crit]">{error.detail}</p>
        )}

        <p className="text-center text-[10px] text-[--color-ink-3]">
          JPG, PNG or WebP, up to 2 MB.
        </p>
      </div>
    </Card>
  );
}

function AffiliationCard({ profile }: { profile: Profile }) {
  return (
    <Card>
      <CardHeader title="Affiliation" />
      <dl className="divide-y divide-[--color-rule] text-[13px]">
        <div className="flex justify-between gap-3 px-4 py-2">
          <dt className="text-[--color-ink-3]">Staff number</dt>
          <dd className="tabular font-medium">{profile.staff_no}</dd>
        </div>
        <div className="flex justify-between gap-3 px-4 py-2">
          <dt className="text-[--color-ink-3]">Faculty</dt>
          <dd className="font-medium">{profile.faculty?.code ?? "—"}</dd>
        </div>
        <div className="flex justify-between gap-3 px-4 py-2">
          <dt className="text-[--color-ink-3]">Since</dt>
          <dd className="tabular">{profile.faculty?.since ?? "—"}</dd>
        </div>
      </dl>
      {/*
        Not editable here on purpose: a transfer writes affiliation history,
        which is what keeps past research attributed to the right faculty.
      */}
      <p className="border-t border-[--color-rule] px-4 py-2.5 text-[11px] text-[--color-ink-3]">
        Staff number and faculty are maintained by the administrator. A faculty transfer is
        recorded with its date, so past research stays credited where it was done.
      </p>
    </Card>
  );
}

function PasswordCard() {
  const [form, setForm] = useState({ current: "", next: "", confirm: "" });
  const [done, setDone] = useState(false);

  const change = useMutation({
    mutationFn: () =>
      api.put("/auth/password", {
        current_password: form.current,
        password: form.next,
        password_confirmation: form.confirm,
      }),
    onSuccess: () => {
      setDone(true);
      setForm({ current: "", next: "", confirm: "" });
    },
  });

  const error = change.error instanceof ApiError ? change.error : null;

  return (
    <Card>
      <CardHeader title="Password" action={done ? <Chip tone="good">Changed</Chip> : undefined} />
      <form
        className="flex flex-col gap-3 p-4"
        onSubmit={(event) => {
          event.preventDefault();
          setDone(false);
          change.mutate();
        }}
      >
        {/* Required, so a hijacked session cannot become a permanent takeover. */}
        <Field label="Current password" required error={error?.fieldError("current_password")}>
          <Input type="password" autoComplete="current-password" required value={form.current}
            onChange={(e) => setForm((f) => ({ ...f, current: e.target.value }))} />
        </Field>
        <Field label="New password" required error={error?.fieldError("password")}
          hint="At least 12 characters.">
          <Input type="password" autoComplete="new-password" required minLength={12}
            value={form.next}
            onChange={(e) => setForm((f) => ({ ...f, next: e.target.value }))} />
        </Field>
        <Field label="Confirm new password" required>
          <Input type="password" autoComplete="new-password" required value={form.confirm}
            onChange={(e) => setForm((f) => ({ ...f, confirm: e.target.value }))} />
        </Field>

        <p className="text-[11px] text-[--color-ink-3]">
          Changing your password signs out your other devices.
        </p>

        {error && !error.errors && (
          <p role="alert" className="text-[12px] text-[--color-crit]">{error.detail}</p>
        )}

        <Button type="submit" variant="primary" disabled={change.isPending}>
          {change.isPending ? "Changing…" : "Change password"}
        </Button>
      </form>
    </Card>
  );
}
