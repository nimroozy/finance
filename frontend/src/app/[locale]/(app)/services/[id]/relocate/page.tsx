"use client";

import { useEffect } from "react";
import { useParams } from "next/navigation";
import { useRouter } from "@/i18n/navigation";
import { LoadingState } from "@/components/ui/layout";

export default function ServiceRelocateRedirectPage() {
  const params = useParams();
  const router = useRouter();
  const id = String(params.id || "");

  useEffect(() => {
    if (id) router.replace(`/services/${id}?tab=relocate`);
  }, [id, router]);

  return <LoadingState label="Loading…" />;
}
