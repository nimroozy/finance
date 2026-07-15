"use client";

import { useCallback, useEffect, useState } from "react";
import { useLocale, useTranslations } from "next-intl";
import { useParams } from "next/navigation";
import { Link } from "@/i18n/navigation";
import { ApiError } from "@/lib/api";
import {
  acceptAssignment,
  getAssignment,
  markAssignmentViewed,
} from "@/lib/assignments";
import { createNote, listNotes } from "@/lib/notes";
import { listVisits } from "@/lib/visits";
import type {
  CollectionVisit,
  CustomerAssignment,
  CustomerNote,
} from "@/lib/types";
import { formatDate, formatDateTime, formatMoney } from "@/lib/utils";
import { StatusBadge } from "@/components/status-badge";
import { Button } from "@/components/ui/button";
import { TextArea } from "@/components/ui/form";
import {
  Alert,
  EmptyState,
  LoadingState,
  PageHeader,
  Panel,
} from "@/components/ui/layout";

export default function CollectorAssignmentDetailPage() {
  const t = useTranslations("collectorAssignments");
  const tCommon = useTranslations("common");
  const locale = useLocale();
  const params = useParams();
  const id = Number(params.id);

  const [assignment, setAssignment] = useState<CustomerAssignment | null>(null);
  const [notes, setNotes] = useState<CustomerNote[]>([]);
  const [visits, setVisits] = useState<CollectionVisit[]>([]);
  const [noteBody, setNoteBody] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const a = await getAssignment(id);
      setAssignment(a.data);
      void markAssignmentViewed(id).catch(() => undefined);
      if (a.data.status === "assigned") {
        void acceptAssignment(id).catch(() => undefined);
      }
      const [n, v] = await Promise.all([
        listNotes({ customer_id: a.data.customer_id, per_page: 20 }),
        listVisits({ customer_id: a.data.customer_id, per_page: 10 }),
      ]);
      setNotes(n.data);
      setVisits(v.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setLoading(false);
    }
  }, [id, tCommon]);

  useEffect(() => {
    void load();
  }, [load]);

  async function onAddNote(e: React.FormEvent) {
    e.preventDefault();
    if (!assignment) return;
    setSaving(true);
    setError(null);
    try {
      await createNote({
        customer_id: assignment.customer_id,
        assignment_id: assignment.id,
        body: noteBody.trim(),
      });
      setNoteBody("");
      setSuccess(t("noteSuccess"));
      const n = await listNotes({
        customer_id: assignment.customer_id,
        per_page: 20,
      });
      setNotes(n.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : tCommon("error"));
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <LoadingState label={tCommon("loading")} />;
  if (!assignment) {
    return error ? <Alert>{error}</Alert> : <EmptyState label={tCommon("empty")} />;
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title={t("detailTitle")}
        subtitle={
          assignment.customer?.contact_name || `#${assignment.customer_id}`
        }
        actions={
          <Link
            href={`/collector/visits/new?assignment_id=${assignment.id}&customer_id=${assignment.customer_id}`}
          >
            <Button className="h-11">{t("startVisit")}</Button>
          </Link>
        }
      />

      {error ? <Alert>{error}</Alert> : null}
      {success ? <Alert tone="success">{success}</Alert> : null}

      <Panel className="space-y-2 p-4 text-sm">
        <div className="flex items-center justify-between">
          <StatusBadge status={assignment.status} />
          <span>{assignment.priority}</span>
        </div>
        <p>
          {t("outstanding")}:{" "}
          {formatMoney(
            assignment.debt_snapshot_outstanding ??
              assignment.customer?.outstanding_receivable,
            assignment.debt_snapshot_currency ??
              assignment.customer?.currency,
            locale,
          )}
        </p>
        <p>
          {t("due")}: {formatDate(assignment.due_date, locale)}
        </p>
        <p className="whitespace-pre-wrap">{assignment.notes || "—"}</p>
        <Link
          href={`/customers/${assignment.customer_id}`}
          className="inline-block text-primary hover:underline"
        >
          {t("invoices")}
        </Link>
      </Panel>

      <Panel className="p-4">
        <p className="mb-2 font-medium">{t("notes")}</p>
        <form onSubmit={onAddNote} className="space-y-2">
          <TextArea
            value={noteBody}
            onChange={(e) => setNoteBody(e.target.value)}
            required
          />
          <Button type="submit" className="h-11 w-full" disabled={saving}>
            {t("addNote")}
          </Button>
        </form>
        <ul className="mt-4 divide-y divide-border">
          {notes.map((n) => (
            <li key={n.id} className="py-3 text-sm">
              <p className="whitespace-pre-wrap">{n.body}</p>
              <p className="mt-1 text-xs text-muted">
                {n.author?.name || "—"} · {formatDateTime(n.created_at, locale)}
              </p>
            </li>
          ))}
        </ul>
      </Panel>

      <Panel>
        <div className="border-b border-border px-4 py-3 font-medium">
          {t("visitHistory")}
        </div>
        {visits.length === 0 ? (
          <EmptyState label={tCommon("empty")} />
        ) : (
          <ul className="divide-y divide-border">
            {visits.map((v) => (
              <li key={v.id} className="px-4 py-3 text-sm">
                <Link
                  href={`/visits/${v.id}`}
                  className="font-medium text-primary"
                >
                  {v.outcome}
                </Link>
                <p className="text-xs text-muted">
                  {formatDateTime(v.visited_at, locale)}
                </p>
              </li>
            ))}
          </ul>
        )}
      </Panel>
    </div>
  );
}
