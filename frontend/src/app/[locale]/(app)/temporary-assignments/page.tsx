/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { createTemporaryAssignment, listTemporaryAssignments, cancelTemporaryAssignment } from "@/lib/ownership";
import { Button } from "@/components/ui/button";
export default function TemporaryAssignmentsPage() {
  const [rows, setRows] = useState<any[]>([]);
  const [form, setForm] = useState({ customer_id: "", temporary_collector_id: "", start_date: new Date().toISOString().slice(0,10), end_date: new Date().toISOString().slice(0,10), reason: "" });
  async function load(){ const r:any = await listTemporaryAssignments(); setRows(r.data?.data||r.data||[]); }
  useEffect(()=>{ load().catch(()=>{}); }, []);
  return (
    <PageSection title="Temporary assignments" description="Overrides permanent ownership only during the valid period.">
      <form className="mb-4 grid gap-2 md:grid-cols-3" onSubmit={async (e)=>{e.preventDefault(); await createTemporaryAssignment({...form, customer_id:Number(form.customer_id), temporary_collector_id:Number(form.temporary_collector_id)}); await load();}}>
        <input className="rounded border px-3 py-2" placeholder="Customer ID" value={form.customer_id} onChange={(e)=>setForm({...form, customer_id:e.target.value})} />
        <input className="rounded border px-3 py-2" placeholder="Temporary collector ID" value={form.temporary_collector_id} onChange={(e)=>setForm({...form, temporary_collector_id:e.target.value})} />
        <input className="rounded border px-3 py-2" type="date" value={form.end_date} onChange={(e)=>setForm({...form, end_date:e.target.value})} />
        <Button type="submit">Create temporary</Button>
      </form>
      <ul className="space-y-2 text-sm">{rows.map((r:any)=>(
        <li key={r.id} className="flex items-center justify-between rounded border p-3">
          <span>#{r.id} customer {r.customer_id} → collector {r.temporary_collector_id} ({r.status}) {r.start_date}–{r.end_date}</span>
          {r.is_active && <Button size="sm" variant="secondary" onClick={async()=>{await cancelTemporaryAssignment(r.id, "cancelled in UI"); await load();}}>Cancel</Button>}
        </li>
      ))}</ul>
    </PageSection>
  );
}
