/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { collectorPermanentCustomers } from "@/lib/ownership";
export default function PermanentCustomersPage() {
  const [rows, setRows] = useState<any[]>([]);
  useEffect(()=>{ collectorPermanentCustomers().then((r:any)=>setRows(r.data?.data||r.data||[])).catch(()=>{}); }, []);
  return <PageSection title="My permanent customers" description="Customers you permanently own, even with zero balance.">
    <ul className="space-y-2 text-sm">{rows.map((r:any)=>(<li key={r.id} className="rounded border p-3"><span className="me-2 rounded bg-sand-soft px-2 py-0.5 text-xs">Permanent</span>{r.customer?.contact_name || r.customer_id} · since {r.start_date}</li>))}</ul>
  </PageSection>;
}
