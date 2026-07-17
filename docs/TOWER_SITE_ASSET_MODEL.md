# Tower, Site & Fixed Asset Model

## Site

Network/infrastructure registry: code, branch, address, GPS, lease/power metadata, status.
Sites may contain many **towers**.

## Tower

Physical structure on a site: height, structure type, GPS, status. Stock locations and fixed assets may reference `tower_id` / `site_id`.

## Fixed asset

Capitalized equipment separate from sellable stock when needed: asset number, category, custodian, acquisition cost/date, condition, warranty, optional link to product/equipment.

## Maintenance

`maintenance_plans` schedule recurring work; `create-task` emits a Stage 7 task without inline WhatsApp.
