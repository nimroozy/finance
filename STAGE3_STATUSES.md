# Stage 3 — Supported Status Values

This documents the **implemented** statuses (code → English / فارسی).  
UI and API must use these codes only.

## Customer assignments (`customer_assignments.status`)

| Code | EN | FA | Active? |
|------|----|----|---------|
| `assigned` | Assigned | تخصیص‌شده | yes |
| `accepted` | Accepted | پذیرفته‌شده | yes |
| `in_progress` | In Progress | در حال اقدام | yes |
| `fully_resolved` | Fully Resolved | کاملاً حل‌شده | no |
| `reassigned` | Reassigned | انتقال‌یافته | no |
| `cancelled` | Cancelled | لغوشده | no |
| `closed` | Closed | بسته‌شده | no |

`is_active=true` only while status is one of `assigned`, `accepted`, `in_progress`.

**Sources:** `manual`, `bulk`, `reassign`, `auto`, `route_import`, `system`.

**Priorities:** `low`, `normal`, `high`, `urgent`.

## Visits (`collection_visits.outcome`)

| Code | EN | FA |
|------|----|----|
| `customer_met` | Customer Met | ملاقات با مشتری |
| `not_available` | Customer Not Available | مشتری در دسترس نبود |
| `not_home` | Not Home | در منزل نبود |
| `no_answer` | No Answer | پاسخ نداد |
| `phone_unreachable` | Phone Unreachable | تلفن در دسترس نیست |
| `wrong_address` | Address Incorrect | آدرس نادرست |
| `customer_moved` | Customer Moved | مشتری جابه‌جا شده |
| `refused` | Customer Refused Payment | امتناع از پرداخت |
| `disputed_balance` | Customer Disputed Balance | اختلاف در مانده |
| `promise_to_pay` | Promise to Pay | وعده پرداخت |
| `requested_manager` | Requested Manager | درخواست مدیر |
| `requested_cancellation` | Requested Service Cancellation | درخواست لغو سرویس |
| `requested_review` | Requested Account Review | درخواست بررسی حساب |
| `business_closed` | Business Closed | مکان تعطیل |
| `follow_up` | Follow-up Required | نیاز به پیگیری |
| `other` | Other | سایر |

Notes required for: `wrong_address`, `customer_moved`, `disputed_balance`, `other`.  
`promise_to_pay` requires promise payload. Follow-up requires date.

**GPS permission:** `granted`, `denied`, `unavailable`, `prompt`.  
**GPS risk:** `ok`, `warning`, `high_risk`.

## Routes (`collection_routes.status`)

| Code | EN | FA |
|------|----|----|
| `draft` | Draft | پیش‌نویس |
| `published` | Assigned/Published | منتشرشده |
| `in_progress` | In Progress | در حال اجرا |
| `completed` | Completed | تکمیل‌شده |
| `cancelled` | Cancelled | لغوشده |

## Route stops (`collection_route_stops.status`)

| Code | EN |
|------|----|
| `pending` | Pending |
| `completed` | Completed |
| `skipped` | Skipped |

## Promises (`promise_to_pay.status`)

| Code | EN | FA |
|------|----|----|
| `active` | Active | فعال |
| `due_soon` | Due Soon | نزدیک سررسید |
| `due_today` | Due Today | سررسید امروز |
| `overdue` | Overdue | معوق |
| `fulfilled` | Fulfilled | انجام‌شده |
| `partially_fulfilled` | Partially Fulfilled | نسبتاً انجام‌شده |
| `broken` | Broken | نقض‌شده |
| `cancelled` | Cancelled | لغوشده |
| `superseded` | Superseded | جایگزین‌شده |

Automatic date transitions via `UpdatePromiseStatusesJob`.  
Fulfillment is manager-only until Stage 4 payments exist.
