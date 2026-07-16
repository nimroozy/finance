/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { bulkAssignOwnership } from "@/lib/ownership";
import { Button } from "@/components/ui/button";
export default function BulkOwnershipPage() {
  const [customerIds, setCustomerIds] = useState("");
  const [collectorId, setCollectorId] = useState("");
  const [result, setResult] = useState<any>(null);
  return (
    <PageSection title="Bulk permanent assignment" description="Preview conflicts via failed list; nothing is silently skipped.">
      <textarea className="mb-3 w-full rounded border p-3 text-sm" rows={4} placeholder="Customer IDs, comma separated" value={customerIds} onChange={(e)=>setCustomerIds(e.target.value)} />
      <input className="mb-3 w-full rounded border px-3 py-2" placeholder="Collector ID" value={collectorId} onChange={(e)=>setCollectorId(e.target.value)} />
      <Button onClick={async ()=>{
        const ids = customerIds.split(/[,\s]+/).map(Number).filter(Boolean);
        setResult(await bulkAssignOwnership({ customer_ids: ids, collector_id: Number(collectorId), start_date: new Date().toISOString().slice(0,10), reason: "bulk" }));
      }}>Assign bulk</Button>
      {result && <pre className="mt-4 overflow-auto rounded bg-sand-soft p-3 text-xs">{JSON.stringify((result as any).data || result, null, 2)}</pre>}
    </PageSection>
  );
}
