# Customer Service Equipment

## Purpose

Track CPE / radios / ONUs installed at customer premises for support and recovery.

## Records

`customer_equipment` links `customer_id` to product and/or serialized `equipment_id`, status, install date.

## Installation path

Stage 7/8 installation queue can reserve and install via `/inventory/installation-*` endpoints. Equipment status becomes `installed` and customer equipment row is maintained.

## Returns

Unused return endpoint moves equipment back toward stock without editing quantities directly.
