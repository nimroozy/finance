import { ApiError, apiFetch, apiDownload, toQuery } from "@/lib/api";
import type { PaginationMeta } from "@/lib/types";

export const TRACKING_MODES = [
  "serialized",
  "quantity",
  "batch",
  "non_stock_service",
] as const;

export const RESERVATION_STATUSES = [
  "draft",
  "pending_approval",
  "approved",
  "partially_fulfilled",
  "fulfilled",
  "released",
  "expired",
  "cancelled",
] as const;

export const TRANSFER_STATUSES = [
  "draft",
  "submitted",
  "approved",
  "dispatched",
  "in_transit",
  "received",
  "closed",
  "cancelled",
] as const;

export const EQUIPMENT_STATUSES = [
  "in_stock",
  "reserved",
  "in_transit",
  "installed",
  "custody",
  "repair",
  "sold",
  "scrapped",
  "lost",
] as const;

export type TrackingMode = (typeof TRACKING_MODES)[number];
export type ReservationStatus = (typeof RESERVATION_STATUSES)[number];
export type TransferStatus = (typeof TRANSFER_STATUSES)[number];
export type EquipmentStatus = (typeof EQUIPMENT_STATUSES)[number];

export type ProductCategory = {
  id: number;
  code: string;
  name_en: string;
  name_fa?: string | null;
};

export type Product = {
  id: number;
  code: string;
  sku?: string | null;
  name_en: string;
  name_fa?: string | null;
  category_id?: number | null;
  category?: ProductCategory | null;
  brand?: string | null;
  model?: string | null;
  uom?: string | null;
  barcode?: string | null;
  tracking_mode: string;
  is_purchase?: boolean;
  is_sales?: boolean;
  is_installable?: boolean;
  is_fixed_asset_eligible?: boolean;
  is_consumable?: boolean;
  is_returnable?: boolean;
  is_company_owned?: boolean;
  purchase_price?: number | string | null;
  sales_price?: number | string | null;
  min_stock?: number | string | null;
  reorder_qty?: number | string | null;
  warranty_months?: number | null;
  is_active?: boolean;
  notes?: string | null;
  created_at?: string;
  updated_at?: string;
};

export type InventoryLocation = {
  id: number;
  code: string;
  name: string;
  type?: string;
  branch_id?: number;
  site_id?: number | null;
  tower_id?: number | null;
  is_active?: boolean;
};

export type StockBalance = {
  id: number;
  branch_id: number;
  product_id: number;
  location_id: number;
  on_hand: number | string;
  reserved: number | string;
  in_transit?: number | string;
  available?: number | string;
  product?: Pick<Product, "id" | "code" | "name_en" | "tracking_mode"> | null;
  location?: Pick<InventoryLocation, "id" | "code" | "name" | "type"> | null;
};

export type StockTransaction = {
  id: number;
  branch_id: number;
  type: string;
  product_id: number;
  qty: number | string;
  from_location_id?: number | null;
  to_location_id?: number | null;
  equipment_id?: number | null;
  reason?: string | null;
  created_at?: string;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
};

export type Equipment = {
  id: number;
  equipment_number: string;
  product_id: number;
  serial_number: string;
  mac_address?: string | null;
  barcode?: string | null;
  branch_id: number;
  location_id?: number | null;
  status: string;
  condition?: string | null;
  ownership?: string | null;
  custodian_user_id?: number | null;
  supplier_id?: number | null;
  purchase_cost?: number | string | null;
  purchase_date?: string | null;
  warranty_start?: string | null;
  warranty_end?: string | null;
  customer_id?: number | null;
  installation_id?: number | null;
  site_id?: number | null;
  tower_id?: number | null;
  notes?: string | null;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
  location?: Pick<InventoryLocation, "id" | "code" | "name"> | null;
  customer?: { id: number; name?: string } | null;
  created_at?: string;
};

export type ReservationItem = {
  id: number;
  reservation_id: number;
  product_id: number;
  qty: number | string;
  qty_fulfilled?: number | string;
  equipment_id?: number | null;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
};

export type Reservation = {
  id: number;
  reservation_number: string;
  branch_id: number;
  status: string;
  location_id: number;
  installation_id?: number | null;
  lead_id?: number | null;
  customer_id?: number | null;
  task_id?: number | null;
  expires_at?: string | null;
  notes?: string | null;
  items?: ReservationItem[];
  created_at?: string;
};

export type TransferItem = {
  id: number;
  transfer_id: number;
  product_id: number;
  qty: number | string;
  qty_received?: number | string;
  equipment_id?: number | null;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
};

export type Transfer = {
  id: number;
  transfer_number: string;
  branch_id: number;
  from_location_id: number;
  to_location_id: number;
  status: string;
  notes?: string | null;
  has_discrepancy?: boolean;
  items?: TransferItem[];
  from_location?: InventoryLocation | null;
  to_location?: InventoryLocation | null;
  fromLocation?: InventoryLocation | null;
  toLocation?: InventoryLocation | null;
  submitted_at?: string | null;
  approved_at?: string | null;
  dispatched_at?: string | null;
  received_at?: string | null;
  created_at?: string;
};

export type Supplier = {
  id: number;
  code: string;
  name: string;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  is_active?: boolean;
};

export type PurchaseRequestItem = {
  id?: number;
  product_id: number;
  qty: number | string;
  notes?: string | null;
};

export type PurchaseRequest = {
  id: number;
  request_number?: string;
  branch_id: number;
  status: string;
  notes?: string | null;
  items?: PurchaseRequestItem[];
  created_at?: string;
};

export type PurchaseOrderItem = {
  id?: number;
  product_id: number;
  qty: number | string;
  unit_cost?: number | string | null;
};

export type PurchaseOrder = {
  id: number;
  po_number?: string;
  branch_id: number;
  supplier_id: number;
  status: string;
  expected_date?: string | null;
  notes?: string | null;
  items?: PurchaseOrderItem[];
  supplier?: Supplier | null;
  created_at?: string;
};

export type GoodsReceipt = {
  id: number;
  receipt_number?: string;
  branch_id: number;
  location_id: number;
  purchase_order_id?: number | null;
  status?: string;
  created_at?: string;
};

export type StockCountLine = {
  id: number;
  stock_count_id: number;
  product_id: number;
  expected_qty?: number | string;
  counted_qty?: number | string | null;
  variance?: number | string | null;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
};

export type StockCount = {
  id: number;
  count_number?: string;
  branch_id: number;
  location_id: number;
  status: string;
  notes?: string | null;
  lines?: StockCountLine[];
  created_at?: string;
};

export type Site = {
  id: number;
  code: string;
  branch_id: number;
  name: string;
  address?: string | null;
  gps_lat?: number | string | null;
  gps_lng?: number | string | null;
  site_type?: string | null;
  status?: string | null;
  power_source?: string | null;
  notes?: string | null;
  towers?: Tower[];
  created_at?: string;
};

export type Tower = {
  id: number;
  code?: string;
  site_id: number;
  branch_id?: number;
  name: string;
  height_m?: number | string | null;
  structure_type?: string | null;
  status?: string | null;
  gps_lat?: number | string | null;
  gps_lng?: number | string | null;
  notes?: string | null;
};

export type FixedAsset = {
  id: number;
  asset_number: string;
  branch_id: number;
  name: string;
  product_id?: number | null;
  equipment_id?: number | null;
  category?: string | null;
  location_id?: number | null;
  site_id?: number | null;
  tower_id?: number | null;
  custodian_user_id?: number | null;
  acquisition_cost?: number | string | null;
  acquisition_date?: string | null;
  condition?: string | null;
  status?: string | null;
  warranty_end?: string | null;
  notes?: string | null;
  created_at?: string;
};

export type CustomerEquipment = {
  id: number;
  branch_id: number;
  customer_id: number;
  product_id?: number | null;
  equipment_id?: number | null;
  serial_number?: string | null;
  status?: string | null;
  installed_at?: string | null;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
  equipment?: Equipment | null;
};

export type CustodyRecord = {
  id: number;
  branch_id: number;
  user_id: number;
  product_id?: number | null;
  equipment_id?: number | null;
  qty?: number | string | null;
  status?: string;
  issued_at?: string | null;
  returned_at?: string | null;
  notes?: string | null;
  user?: { id: number; name: string } | null;
  product?: Pick<Product, "id" | "code" | "name_en"> | null;
};

export type Repair = {
  id: number;
  branch_id: number;
  equipment_id: number;
  status: string;
  fault_description?: string | null;
  resolution_notes?: string | null;
  repair_cost?: number | string | null;
  assigned_to?: number | null;
  opened_at?: string | null;
  completed_at?: string | null;
  equipment?: Equipment | null;
};

export type MaintenancePlan = {
  id: number;
  branch_id: number;
  name: string;
  equipment_id?: number | null;
  fixed_asset_id?: number | null;
  tower_id?: number | null;
  site_id?: number | null;
  frequency?: string | null;
  next_due_date?: string | null;
  notes?: string | null;
  status?: string | null;
};

export type InventoryDashboard = {
  on_hand: number;
  reserved: number;
  available: number;
  in_transit: number;
  low_stock_products: number;
  open_reservations: number;
  open_transfers: number;
  equipment_in_stock: number;
  equipment_installed: number;
};

type ListParams = Record<string, string | number | boolean | undefined | null>;

function listMeta(meta?: PaginationMeta) {
  return meta;
}

export async function getInventoryDashboard(branchId?: string | number) {
  return apiFetch<InventoryDashboard>(
    `/inventory/dashboard${toQuery({ branch_id: branchId || undefined })}`,
  );
}

export async function searchInventory(q: string, branchId?: string | number) {
  return apiFetch<unknown>(`/inventory/search${toQuery({ q, branch_id: branchId || undefined })}`);
}

export async function listProducts(params: ListParams = {}) {
  const res = await apiFetch<Product[]>(`/inventory/products${toQuery(params)}`);
  return { ...res, meta: listMeta(res.meta) };
}

export async function getProduct(id: number | string) {
  return apiFetch<Product>(`/inventory/products/${id}`);
}

export async function createProduct(payload: Record<string, unknown>) {
  return apiFetch<Product>(`/inventory/products`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function updateProduct(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<Product>(`/inventory/products/${id}`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function listCategories(params: ListParams = {}) {
  return apiFetch<ProductCategory[]>(`/inventory/categories${toQuery(params)}`);
}

export async function listLocations(params: ListParams = {}) {
  return apiFetch<InventoryLocation[]>(`/inventory/locations${toQuery(params)}`);
}

export async function listStockBalances(params: ListParams = {}) {
  return apiFetch<StockBalance[]>(`/inventory/stock/balances${toQuery(params)}`);
}

export async function listStockTransactions(params: ListParams = {}) {
  return apiFetch<StockTransaction[]>(`/inventory/stock/transactions${toQuery(params)}`);
}

export async function adjustStock(payload: Record<string, unknown>) {
  return apiFetch<StockTransaction>(`/inventory/stock/adjust`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listEquipment(params: ListParams = {}) {
  return apiFetch<Equipment[]>(`/inventory/equipment${toQuery(params)}`);
}

export async function getEquipment(id: number | string) {
  return apiFetch<Equipment>(`/inventory/equipment/${id}`);
}

export async function receiveEquipment(payload: Record<string, unknown>) {
  return apiFetch<Equipment>(`/inventory/equipment/receive`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function moveEquipment(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<Equipment>(`/inventory/equipment/${id}/move`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listReservations(params: ListParams = {}) {
  return apiFetch<Reservation[]>(`/inventory/reservations${toQuery(params)}`);
}

export async function getReservation(id: number | string) {
  return apiFetch<Reservation>(`/inventory/reservations/${id}`);
}

export async function createReservation(payload: Record<string, unknown>) {
  return apiFetch<Reservation>(`/inventory/reservations`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function reserveReservation(id: number | string) {
  return apiFetch<Reservation>(`/inventory/reservations/${id}/reserve`, { method: "POST" });
}

export async function fulfillReservation(id: number | string) {
  return apiFetch<Reservation>(`/inventory/reservations/${id}/fulfill`, { method: "POST" });
}

export async function releaseReservation(id: number | string) {
  return apiFetch<Reservation>(`/inventory/reservations/${id}/release`, { method: "POST" });
}

export async function listTransfers(params: ListParams = {}) {
  return apiFetch<Transfer[]>(`/inventory/transfers${toQuery(params)}`);
}

export async function getTransfer(id: number | string) {
  return apiFetch<Transfer>(`/inventory/transfers/${id}`);
}

export async function createTransfer(payload: Record<string, unknown>) {
  return apiFetch<Transfer>(`/inventory/transfers`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function submitTransfer(id: number | string) {
  return apiFetch<Transfer>(`/inventory/transfers/${id}/submit`, { method: "POST" });
}

export async function approveTransfer(id: number | string) {
  return apiFetch<Transfer>(`/inventory/transfers/${id}/approve`, { method: "POST" });
}

export async function dispatchTransfer(id: number | string) {
  return apiFetch<Transfer>(`/inventory/transfers/${id}/dispatch`, { method: "POST" });
}

export async function receiveTransfer(
  id: number | string,
  payload?: { received_qtys?: Record<string, number> },
) {
  return apiFetch<Transfer>(`/inventory/transfers/${id}/receive`, {
    method: "POST",
    body: JSON.stringify(payload ?? {}),
  });
}

export async function closeTransfer(id: number | string) {
  return apiFetch<Transfer>(`/inventory/transfers/${id}/close`, { method: "POST" });
}

export async function listPurchaseRequests(params: ListParams = {}) {
  return apiFetch<PurchaseRequest[]>(`/inventory/purchase-requests${toQuery(params)}`);
}

export async function createPurchaseRequest(payload: Record<string, unknown>) {
  return apiFetch<PurchaseRequest>(`/inventory/purchase-requests`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function approvePurchaseRequest(id: number | string) {
  return apiFetch<PurchaseRequest>(`/inventory/purchase-requests/${id}/approve`, {
    method: "POST",
  });
}

export async function listPurchaseOrders(params: ListParams = {}) {
  return apiFetch<PurchaseOrder[]>(`/inventory/purchase-orders${toQuery(params)}`);
}

export async function createPurchaseOrder(payload: Record<string, unknown>) {
  return apiFetch<PurchaseOrder>(`/inventory/purchase-orders`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function approvePurchaseOrder(id: number | string) {
  return apiFetch<PurchaseOrder>(`/inventory/purchase-orders/${id}/approve`, { method: "POST" });
}

export async function createGoodsReceipt(payload: Record<string, unknown>) {
  return apiFetch<GoodsReceipt>(`/inventory/goods-receipts`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listSuppliers(params: ListParams = {}) {
  return apiFetch<Supplier[]>(`/inventory/suppliers${toQuery(params)}`);
}

export async function createSupplier(payload: Record<string, unknown>) {
  return apiFetch<Supplier>(`/inventory/suppliers`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listStockCounts(params: ListParams = {}) {
  return apiFetch<StockCount[]>(`/inventory/stock-counts${toQuery(params)}`);
}

export async function getStockCount(id: number | string) {
  const res = await listStockCounts({ per_page: 100 });
  const found = res.data.find((c) => c.id === Number(id));
  if (!found) {
    throw new ApiError("Stock count not found", 404);
  }
  return { success: true as const, data: found, meta: res.meta, message: null };
}

export async function createStockCount(payload: Record<string, unknown>) {
  return apiFetch<StockCount>(`/inventory/stock-counts`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function recordStockCount(
  id: number | string,
  counted: Record<string, number>,
) {
  return apiFetch<StockCount>(`/inventory/stock-counts/${id}/record`, {
    method: "POST",
    body: JSON.stringify({ counted }),
  });
}

export async function postStockCount(id: number | string) {
  return apiFetch<StockCount>(`/inventory/stock-counts/${id}/post`, { method: "POST" });
}

export async function listSites(params: ListParams = {}) {
  return apiFetch<Site[]>(`/inventory/sites${toQuery(params)}`);
}

export async function getSite(id: number | string) {
  return apiFetch<Site>(`/inventory/sites/${id}`);
}

export async function createSite(payload: Record<string, unknown>) {
  return apiFetch<Site>(`/inventory/sites`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listTowers(params: ListParams = {}) {
  return apiFetch<Tower[]>(`/inventory/towers${toQuery(params)}`);
}

export async function createTower(payload: Record<string, unknown>) {
  return apiFetch<Tower>(`/inventory/towers`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function getTower(id: number | string) {
  const res = await listTowers({ per_page: 100 });
  const found = res.data.find((t) => t.id === Number(id));
  if (!found) {
    throw new ApiError("Tower not found", 404);
  }
  return { success: true as const, data: found, meta: res.meta, message: null };
}

export async function listFixedAssets(params: ListParams = {}) {
  return apiFetch<FixedAsset[]>(`/inventory/fixed-assets${toQuery(params)}`);
}

export async function getFixedAsset(id: number | string) {
  const res = await listFixedAssets({ per_page: 100 });
  const found = res.data.find((a) => a.id === Number(id));
  if (!found) {
    throw new ApiError("Asset not found", 404);
  }
  return { success: true as const, data: found, meta: res.meta, message: null };
}

export async function createFixedAsset(payload: Record<string, unknown>) {
  return apiFetch<FixedAsset>(`/inventory/fixed-assets`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listCustomerEquipment(params: ListParams = {}) {
  return apiFetch<CustomerEquipment[]>(`/inventory/customer-equipment${toQuery(params)}`);
}

export async function listCustody(params: ListParams = {}) {
  return apiFetch<CustodyRecord[]>(`/inventory/custody${toQuery(params)}`);
}

export async function issueCustody(payload: Record<string, unknown>) {
  return apiFetch<CustodyRecord>(`/inventory/custody/issue`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function returnCustody(id: number | string, toLocationId: number) {
  return apiFetch<CustodyRecord>(`/inventory/custody/${id}/return`, {
    method: "POST",
    body: JSON.stringify({ to_location_id: toLocationId }),
  });
}

export async function listRepairs(params: ListParams = {}) {
  return apiFetch<Repair[]>(`/inventory/repairs${toQuery(params)}`);
}

export async function openRepair(payload: Record<string, unknown>) {
  return apiFetch<Repair>(`/inventory/repairs`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function completeRepair(id: number | string, payload: Record<string, unknown>) {
  return apiFetch<Repair>(`/inventory/repairs/${id}/complete`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function listMaintenancePlans(params: ListParams = {}) {
  return apiFetch<MaintenancePlan[]>(`/inventory/maintenance-plans${toQuery(params)}`);
}

export async function createMaintenancePlan(payload: Record<string, unknown>) {
  return apiFetch<MaintenancePlan>(`/inventory/maintenance-plans`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function createMaintenanceTask(id: number | string) {
  return apiFetch<unknown>(`/inventory/maintenance-plans/${id}/create-task`, { method: "POST" });
}

export async function downloadInventoryBalancesReport(branchId?: string | number) {
  await apiDownload(
    `/inventory/reports/balances${toQuery({ branch_id: branchId || undefined })}`,
    "inventory-balances.csv",
  );
}

export async function downloadInventoryTransactionsReport(branchId?: string | number) {
  await apiDownload(
    `/inventory/reports/transactions${toQuery({ branch_id: branchId || undefined })}`,
    "inventory-transactions.csv",
  );
}
