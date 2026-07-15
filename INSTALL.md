# Install

## Requirements

Docker Engine + Docker Compose v2, or local PHP 8.3 + Node 22 + PostgreSQL + Redis for development.

## Production install

1. Point `finance.mns.af` to the VPS.
2. Sync this repository to `/opt/collection-system`.
3. Copy `.env.example` → `.env` and set strong secrets.
4. Run `./scripts/deploy.sh`.
5. Log in and change the temporary administrator password.

## Local API development

```bash
cd backend
cp .env.example .env
# configure sqlite or local postgres
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan app:install --name="Admin" --email=admin@example.com --username=admin --password='ChangeMeNow1!' --company="MNS Collection"
php artisan serve
```

## Local frontend

```bash
cd frontend
npm install
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api/v1 npm run dev
```
