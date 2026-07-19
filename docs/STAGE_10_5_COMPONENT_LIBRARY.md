# Stage 10.5 — Shared Component Library

All Stage 10.5 screens are built from one component library under
`frontend/src/components/ui/`. There is no parallel/duplicate component
system. Components are token-driven (see `STAGE_10_5_PROFESSIONAL_UI.md`),
theme-aware (light/dark), and RTL-safe (logical properties: `ps-*`, `pe-*`,
`ms-*`, `me-*`, `text-start`, `border-s`).

## Data display

| Component | File | Purpose |
|---|---|---|
| `DataTable` | `data-table.tsx` | Dense desktop table with header, loading, empty, filters slot, and built-in pagination. |
| `ResponsiveRecordList` / `MobileRecordCard` | `record-list.tsx` | Card list for mobile-first field workflows. Cards use the locale-aware `Link` for client-side navigation. |
| `KpiCard` | `kpi-card.tsx` | Labelled metric tile with tone + optional link. |
| `StatusBadge` / `Badge` | `../status-badge.tsx`, `badge.tsx` | Status pills; meaning carried by text + tone, not colour alone. |
| `DetailHeader` / `DetailSection` | `detail-header.tsx`, `detail-section.tsx` | Record detail chrome. |
| `RecordTabs` | `record-tabs.tsx` | Lazy tab set for detail pages. |
| `Pagination` | `pagination.tsx` | Standalone pager. |

## State & feedback

| Component | File | Purpose |
|---|---|---|
| `ErrorState` | `error-state.tsx` | Typed error surface — routes 401/403/404/409/422/network/generic to the right message and an optional retry. Never a blank page. |
| `EmptyState` | `empty-state.tsx` | Honest "nothing here" / "not available" state. |
| `LoadingSkeleton` / `CardSkeleton` | `skeleton.tsx` | Content-shaped loading placeholders. |
| `Toast` | `toast.tsx` | Transient action outcomes. |
| `ValidationSummary` | `validation-summary.tsx` | Aggregated field errors. |
| `ConfirmationDialog` (`ConfirmDialog`) | `confirm-dialog.tsx` | Guard before irreversible actions (payment submit, cancel). |

## Inputs & pickers

| Component | File | Purpose |
|---|---|---|
| `SearchableSelect` | `searchable-select.tsx` | Generic async combobox; ARIA combobox/listbox roles, keyboard navigation, clear button. The base for all pickers — no operator ever types a raw id. |
| `EntityPicker` | `entity-picker.tsx` | `SearchableSelect` bound to a `/pickers/*` search function. |
| `CustomerPicker` / `CollectorPicker` / `BranchPicker` / `ServicePicker` / `ProductPicker` / `EquipmentPicker` / `UserPicker` | `pickers.tsx` | Self-contained typed pickers over the enriched picker endpoints (`STAGE_10_5_OPENAPI_RESULTS.md`). |
| `FormField` | `form-field.tsx` | Label + control + error wiring. |
| `Form` primitives (`Input`, `Select`, `TextArea`, `Label`, `FieldError`) | `form.tsx` | Base controls; associated with labels via `htmlFor`/`id` or `aria-label`. |
| `MoneyInput` / `PhoneInput` / `DatePicker` | `money-input.tsx`, `phone-input.tsx`, `date-picker.tsx` | Domain inputs. |

## Layout & navigation

| Component | File | Purpose |
|---|---|---|
| `PageHeader` / `PageToolbar` / `PageSection` | `layout.tsx`, `page-toolbar.tsx`, `page-section.tsx` | Page-level chrome. |
| `FilterBar` | `filter-bar.tsx` | Filter row wrapper. |
| `Breadcrumbs` | `breadcrumbs.tsx` | Trail. |
| `Modal` | `modal.tsx` | Base dialog. |
| `Button` | `button.tsx` | Variants (primary/secondary/ghost/danger), sizes, disabled + loading. |

## Picker contracts

All picker search functions return the `SearchableOption` / `PickerOption`
shape `{ id, label, description?, meta? }`, with typed payloads in
`src/lib/pickers.ts` (`PickerCustomer`, `PickerCollector`, `PickerBranch`,
`PickerService`, `PickerProduct`, `PickerEquipment`). Payloads carry only
the fields the UI needs — no unnecessary sensitive fields
(`STAGE_10_5_OPENAPI_RESULTS.md`).
