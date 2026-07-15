# Deployment

## Target

- Host: `209.38.194.184`
- Domain: `finance.mns.af`
- Path: `/opt/collection-system`
- Backups: `/opt/collection-backups`

## DNS prerequisite

Create an `A` record:

```
finance.mns.af  ->  209.38.194.184
```

Until DNS propagates, the app is reachable via `http://209.38.194.184`.

## First-time VPS setup

Handled by `scripts/vps-bootstrap.sh` (swap, Docker, UFW 22/80/443).

## Deploy / update

```bash
cd /opt/collection-system
./scripts/deploy.sh
```

## SSL (after DNS)

```bash
./scripts/issue-ssl.sh
```

## Security rules

- Never publish Postgres or Redis ports
- Never commit `.env`
- Keep UFW enabled
