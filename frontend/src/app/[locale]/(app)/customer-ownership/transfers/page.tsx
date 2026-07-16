"use client";
import { useState } from "react";
import { PageSection } from "@/components/ui/page-section";
import { transferOwnership } from "@/lib/ownership";
import { Button } from "@/components/ui/button";
export default function OwnershipTransfersPage() {
  const [form, setForm] = useState({ customer_id: "", to_collector_id: "", effective_date: new Date().toISOString().slice(0,10), reason: "" });
  const [msg, setMsg] = useState("");
  return (
    <PageSection title="Ownership transfer" description="End previous ownership and assign a new permanent collector without rewriting history.">
      <div className="grid gap-3 md:grid-cols-2">
        {Object.entries(form).map(([k,v]) => (
          <input key={k} className="rounded border px-3 py-2" placeholder={k} value={v} onChange={(e)=>setForm({...form, [k]: e.target.value})} />
        ))}
      </div>
      <Button className="mt-4" onClick={async ()=>{
        await transferOwnership({ ...form, customer_id: Number(form.customer_id), to_collector_id: Number(form.to_collector_id) });
        setMsg("Transfer applied");
      }}>Confirm transfer</Button>
      {msg && <p className="mt-2 text-sm">{msg}</p>}
    </PageSection>
  );
}
