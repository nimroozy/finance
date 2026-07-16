/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { listOwnership, assignOwnership } from "@/lib/ownership";
import { Button } from "@/components/ui/button";

export default function OwnershipPage() {
  const [rows, setRows] = useState<any[]>([]);
  const [form, setForm] = useState({ customer_id: "", collector_id: "", start_date: new Date().toISOString().slice(0,10), reason: "" });
  const [error, setError] = useState<string | null>(null);
  async function load() {
    const res = await listOwnership({ status: "active" });
    setRows((res as any).data?.data || (res as any).data || []);
  }
  useEffect(() => { load().catch((e) => setError(e.message)); }, []);
  return (
    <PageSection title="Permanent ownership" description="Assign collectors who permanently own customers.">
      {error && <p className="text-sm text-danger">{error}</p>}
      <form className="mb-6 grid gap-3 md:grid-cols-4" onSubmit={async (e) => {
        e.preventDefault();
        await assignOwnership({ ...form, customer_id: Number(form.customer_id), collector_id: Number(form.collector_id) });
        await load();
      }}>
        <input className="rounded border px-3 py-2" placeholder="Customer ID" value={form.customer_id} onChange={(e)=>setForm({...form, customer_id:e.target.value})} />
        <input className="rounded border px-3 py-2" placeholder="Collector ID" value={form.collector_id} onChange={(e)=>setForm({...form, collector_id:e.target.value})} />
        <input className="rounded border px-3 py-2" type="date" value={form.start_date} onChange={(e)=>setForm({...form, start_date:e.target.value})} />
        <Button type="submit">Assign permanently</Button>
      </form>
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead><tr><th className="p-2 text-start">Customer</th><th className="p-2 text-start">Collector</th><th className="p-2 text-start">Branch</th><th className="p-2 text-start">Start</th><th className="p-2 text-start">Status</th></tr></thead>
          <tbody>
            {rows.map((r:any)=>(
              <tr key={r.id} className="border-t"><td className="p-2">{r.customer?.contact_name || r.customer_id}</td><td className="p-2">{r.collector?.user?.name || r.collector_id}</td><td className="p-2">{r.branch?.name_en || r.branch_id}</td><td className="p-2">{r.start_date}</td><td className="p-2">{r.status}</td></tr>
            ))}
          </tbody>
        </table>
      </div>
    </PageSection>
  );
}
