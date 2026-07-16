# Temporary collection assignments

- Table: `temporary_collection_assignments`
- Overrides permanent ownership only while `start_date`…`end_date` and status `active`
- Overlaps rejected
- Cancel restores permanent owner via work-queue recalculation
- Scheduled: `php artisan assignments:expire-temporary` (hourly)
