/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { KpiCard } from "@/components/ui/kpi-card";
import { branchReceivables } from "@/lib/ownership";
export default function BranchReceivablesPage() {
  const [rows, setRows] = useState<any[]>([]);
  useEffect(()=>{ branchReceivables().then((r:any)=>setRows(r.data||[])).catch(()=>{}); }, []);
  return (
    <PageSection title="Branch receivables" description="Totals from synchronized Zoho invoice balances.">
      <div className="space-y-6">
        {rows.map((r:any)=>(
          <div key={r.branch?.id} className="rounded border p-4">
            <h2 className="mb-3 text-lg font-semibold">{r.branch?.name_en || r.branch?.code}</h2>
            <div className="grid gap-3 md:grid-cols-4">
              <KpiCard label="Receivable" value={r.total_receivable} />
              <KpiCard label="Debtors" value={r.active_debtor_customers} />
              <KpiCard label="Owned" value={r.permanently_owned_customers} />
              <KpiCard label="Unassigned" value={r.unassigned_customers} />
            </div>
          </div>
        ))}
      </div>
    </PageSection>
  );
}
