# CRM Backend – Implementation Contract

This document defines the non-negotiable implementation rules
for the CRM backend.

Codex and contributors MUST follow this contract strictly.

---

## 1. Architecture

- Framework: Laravel (API only, no Blade)
- Frontend: Tauri (desktop client)
- Architecture style: DDD-lite
- Controllers:
  - HTTP transport only
  - No business logic
  - No role checks
- Business logic:
  - Services = domain rules
  - Actions = use-cases
- Persistence:
  - Eloquent models
  - No fat models
- Async logic:
  - Events + Listeners + Jobs

---

## 2. Authorization Model

Authorization MUST be implemented using:

1. Gates (abilities)
2. Policies (object-level checks)
3. Route middleware only

Forbidden:
- Role checks in controllers
- Role checks in services/actions
- Authorization logic inside models

---

## 3. Roles (conceptual)

- Advisor (Doradca)
- Manager
- Director
- Admin

Roles are NOT checked directly.
They only grant permissions (abilities).

---

## 4. Permissions (examples)

- clients.view
- clients.create
- meetings.create
- meetings.view
- meetings.delete
- calculations.create
- offers.create
- offers.expire
- notifications.view

Endpoints MUST declare permissions explicitly.

---

## 5. Business Rules Handling

- All business rules MUST:
  - Live in Services
  - Throw DomainException on violation
- Actions MUST:
  - Call Services
  - Perform state changes
- Controllers MUST:
  - Call Actions only
  - Never contain if/else business logic

---

## 6. Testing Strategy

- Testing framework: Pest
- What to test:
  - Domain rules
  - Actions (use-cases)
- What NOT to test:
  - CRUD
  - Simple getters/setters
  - Eloquent relations

Tests are considered part of the documentation.

---

## 7. Code Style Rules

- One Action = one business use-case
- Services may be reused by multiple Actions
- Prefer clarity over abstraction
- No premature CQRS or Event Sourcing

---

## 8. Change Policy

If a change violates this contract:
- The contract MUST be updated first
- Implementation comes second

This file is the source of truth.
