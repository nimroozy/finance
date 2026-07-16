/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { collectorDebtors } from "@/lib/ownership";
export default function CollectorDebtorsPage() {
  const [rows, setRows] = useState<any[]>([]);
  useEffect(()=>{ collectorDebtors().then((r:any)=>setRows(r.data?.data||r.data||[])).catch(()=>{}); }, []);
  return <PageSection title="My current debtors" description="Customers currently requiring collection.">
    <ul className="space-y-2 text-sm">{rows.map((r:any)=>(
      <li key={r.id} className="rounded border p-3">
        <span className="me-2 rounded bg-sand-soft px-2 py-0.5 text-xs capitalize">{r.ownership_source}</span>
        {r.customer?.contact_name || r.customer_id} · {r.total_open_balance} · {r.open_invoice_count} invoices · overdue {r.days_overdue}d
      </li>
    ))}</ul>
  </PageSection>;
}
