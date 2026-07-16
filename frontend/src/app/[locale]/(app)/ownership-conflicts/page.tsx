/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { listOwnershipConflicts } from "@/lib/ownership";
export default function OwnershipConflictsPage() {
  const [rows, setRows] = useState<any[]>([]);
  useEffect(()=>{ listOwnershipConflicts().then((r:any)=>setRows(r.data?.data||r.data||[])).catch(()=>{}); }, []);
  return <PageSection title="Ownership conflicts" description="Do not auto-resolve conflicting ownership."><ul className="space-y-2 text-sm">{rows.map((r:any)=>(<li key={r.id} className="rounded border p-3">{r.conflict_type} · customer {r.customer_id} · {r.status}</li>))}</ul></PageSection>;
}
