/* eslint-disable @typescript-eslint/no-explicit-any */
"use client";
import { useEffect, useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { Button } from "@/components/ui/button";
import { listBranchPaymentMappings, listZohoAccounts, listZohoPaymentModes, upsertBranchPaymentMapping, validateBranchPaymentMapping } from "@/lib/ownership";
export default function BranchPaymentMappingsPage() {
  const [mappings, setMappings] = useState<any[]>([]);
  const [accounts, setAccounts] = useState<any[]>([]);
  const [modes, setModes] = useState<any[]>([]);
  const [form, setForm] = useState({ branch_id: "", zoho_account_id: "", zoho_payment_mode_id: "", currency: "AFN" });
  async function load() {
    const [m,a,p] = await Promise.all([listBranchPaymentMappings(), listZohoAccounts("cash"), listZohoPaymentModes()]);
    setMappings((m as any).data || []); setAccounts((a as any).data || []); setModes((p as any).data || []);
  }
  useEffect(()=>{ load().catch(()=>{}); }, []);
  return (
    <PageSection title="Branch Zoho account mapping" description="Select synchronized Zoho accounts. Raw IDs appear only in diagnostics.">
      <form className="mb-6 grid gap-3 md:grid-cols-2" onSubmit={async (e)=>{e.preventDefault(); await upsertBranchPaymentMapping({...form, branch_id:Number(form.branch_id)}); await load();}}>
        <input className="rounded border px-3 py-2" placeholder="Branch ID" value={form.branch_id} onChange={(e)=>setForm({...form, branch_id:e.target.value})} />
        <select className="rounded border px-3 py-2" value={form.zoho_account_id} onChange={(e)=>setForm({...form, zoho_account_id:e.target.value})}>
          <option value="">Zoho account</option>
          {accounts.map((a:any)=>(<option key={a.zoho_account_id} value={a.zoho_account_id}>{a.name} ({a.account_type})</option>))}
        </select>
        <select className="rounded border px-3 py-2" value={form.zoho_payment_mode_id} onChange={(e)=>setForm({...form, zoho_payment_mode_id:e.target.value})}>
          <option value="">Payment mode</option>
          {modes.map((m:any)=>(<option key={m.zoho_payment_mode_id} value={m.zoho_payment_mode_id}>{m.name}</option>))}
        </select>
        <Button type="submit">Save mapping</Button>
      </form>
      <ul className="space-y-3 text-sm">{mappings.map((m:any)=>(
        <li key={m.id} className="rounded border p-3">
          <div className="font-medium">{m.branch?.name_en || m.branch_id} · {m.readiness_status}</div>
          <div>{m.zoho_account_name} · {m.zoho_payment_mode_name} · {m.currency}</div>
          <details className="mt-2 text-xs text-muted"><summary>Diagnostics</summary>account={m.zoho_account_id} location={m.zoho_location_id} version={m.mapping_version}</details>
          <Button className="mt-2" size="sm" variant="secondary" onClick={async()=>{await validateBranchPaymentMapping(m.branch_id); await load();}}>Validate</Button>
        </li>
      ))}</ul>
    </PageSection>
  );
}
