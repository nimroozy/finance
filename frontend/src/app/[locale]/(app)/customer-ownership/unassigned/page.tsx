/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { listUnassignedOwnership } from "@/lib/ownership";
export default function UnassignedOwnershipPage() {
  const [rows, setRows] = useState<any[]>([]);
  useEffect(() => { listUnassignedOwnership().then((r:any)=>setRows(r.data?.data||r.data||[])).catch(()=>{}); }, []);
  return (
    <PageSection title="Unassigned customers" description="Customers without an effective collector.">
      <ul className="space-y-2 text-sm">{rows.map((r:any)=>(<li key={r.id} className="rounded border p-3">{r.customer?.contact_name || r.customer_id} · {r.total_open_balance} · {r.ownership_source}</li>))}</ul>
    </PageSection>
  );
}
