## Stratton API

This is the Stratton backend API built on Laravel. The sections below point to the main code areas (routes, models, events, etc.) so you can navigate quickly.

### Project structure

- `routes/api.php` - REST API endpoints
- `routes/web.php` - web routes (if any)
- `app/Http/Controllers` - controllers for API/web endpoints
- `app/Models` - Eloquent models
- `database/migrations` - schema migrations
- `database/seeders` - seed data
- `app/Events` - domain events
- `app/Listeners` - event listeners
- `app/Jobs` - queued jobs
- `app/Services` - application services and domain logic
- `app/Http/Requests` - request validation
- `app/Http/Resources` - API response shaping
- `app/Policies` - authorization policies
- `app/Providers` - service providers and bootstrapping
- `config` - app configuration (including Keycloak and Reverb)

### API documentation

- `docs/API.md` - local API reference
