import { CashTransferDetailPage } from "@/components/cash-transfer-pages";

export default async function TransferDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <CashTransferDetailPage id={Number(id)} />;
}
