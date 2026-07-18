# API List Response Shapes (Stage 10.5)

## Preferred shape

```json
{
  "success": true,
  "data": [],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 25, "total": 0 }
}
```

Most endpoints already return this (`customers.ts`, `payments.ts`, `pickers.ts` consumers).

## Legacy shape (compatibility adapter, not fixed at the source)

A handful of controllers (`CustomerOwnershipController`, `TemporaryAssignmentController`,
`CollectorOwnershipController`) pass a raw Laravel paginator straight into
`ApiResponse::success()` instead of flattening it first, so the wire shape is:

```json
{ "success": true, "data": { "current_page": 1, "data": [], "last_page": 1, "per_page": 25, "total": 0 } }
```

Changing those controllers to flatten at the source is out of scope for Stage
10.5 (higher-risk backend behavior change, more test surface than a UI stage
should touch). Instead, `frontend/src/lib/api.ts` exports `normalizeList()` /
`apiFetchList()`, which accept either shape and always return
`{ data: T[], meta: PaginationMeta }`. `lib/ownership.ts` is the first
consumer — new call sites for these endpoints should use `apiFetchList`
rather than re-adding `res.data?.data || res.data || []`-style unwrapping.
