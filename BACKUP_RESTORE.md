# Backup and restore

Daily backups should land in `/opt/collection-backups/<UTC-timestamp>/`.

## Manual backup

```bash
cd /opt/collection-system
./scripts/backup.sh
```

Includes:

- PostgreSQL custom-format dump (+ gzip copy)
- `.env` copy
- Nginx config
- Uploaded files volume (when present)

Retention: last 14 backup directories.

## Restore

```bash
./scripts/restore.sh /opt/collection-backups/YYYYMMDDTHHMMSSZ
```

Type `YES` to confirm. This replaces database contents.

## Cron (recommended)

```cron
15 2 * * * root /opt/collection-system/scripts/backup.sh >> /var/log/collection-backup.log 2>&1
```
