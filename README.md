# Stratton Prime CRM

Monorepo systemu CRM dla sieci handlowej Stratton.

## Struktura

```
/
├── frontend/   # Vue 3 + Vite + Tauri (desktop app)
└── backend/    # Laravel 12 REST API
```

## Frontend

```bash
cd frontend
npm install
npm run dev    # http://localhost:3000
```

## Backend

```bash
cd backend
composer install
php artisan migrate
php artisan serve
```

## Docker (lokalny stack)

```bash
cd backend
docker compose up -d
```
