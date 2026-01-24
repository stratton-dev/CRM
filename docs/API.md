# CRM API Documentation

Base URL: `/api`

Authentication:
- All `/v1/*` routes require a Keycloak JWT bearer token.
- Header: `Authorization: Bearer <access_token>`
- Public route: `GET /offers/{token}`.

Keycloak config (env):
- `KC_BASE_URL`, `KC_REALM`, `KC_CLIENT_ID`
- Optional: `KC_AUDIENCE`, `KC_ISSUER`, `KC_JWKS_URL`

Role/permission mapping:
- Configure `config/keycloak.php` for role priority and optional role/permission sync.
- Roles in token are read from `realm_access.roles` and `resource_access.<client_id>.roles`.

Permissions:
- Each action requires the listed permission via route middleware.
- Permissions are simple strings stored in `permissions.code`.

---

## Public Offer

- `GET /offers/{token}`
  - Auth: no
  - Description: Open offer by public token. Marks offer as opened if first access.

---

## Organizations

- `GET /v1/organizations` (organizations.view)
- `GET /v1/organizations/{organization}` (organizations.view)
- `POST /v1/organizations` (organizations.create)
- `PUT/PATCH /v1/organizations/{organization}` (organizations.update)
- `DELETE /v1/organizations/{organization}` (organizations.delete)

---

## Roles

- `GET /v1/roles` (roles.view)
- `GET /v1/roles/{role}` (roles.view)
- `POST /v1/roles` (roles.create)
- `PUT/PATCH /v1/roles/{role}` (roles.update)
- `DELETE /v1/roles/{role}` (roles.delete)

---

## Permissions

- `GET /v1/permissions` (permissions.view)
- `GET /v1/permissions/{permission}` (permissions.view)
- `POST /v1/permissions` (permissions.create)
- `PUT/PATCH /v1/permissions/{permission}` (permissions.update)
- `DELETE /v1/permissions/{permission}` (permissions.delete)

---

## Companies

- `GET /v1/companies` (companies.view)
  - Filters: `search`, `per_page`
- `GET /v1/companies/{company}` (companies.view)
- `POST /v1/companies` (companies.create)
- `PUT/PATCH /v1/companies/{company}` (companies.update)
- `DELETE /v1/companies/{company}` (companies.delete)

---

## Employees

- `GET /v1/employees` (employees.view)
  - Filters: `company_id`, `search`, `per_page`
- `GET /v1/employees/{employee}` (employees.view)
- `POST /v1/employees` (employees.create)
- `PUT/PATCH /v1/employees/{employee}` (employees.update)
- `DELETE /v1/employees/{employee}` (employees.delete)

---

## Clients

- `GET /v1/clients` (clients.view)
  - Filters: `organization_id`, `search`, `per_page`
- `GET /v1/clients/{client}` (clients.view)
- `POST /v1/clients` (clients.create)
- `PUT/PATCH /v1/clients/{client}` (clients.update)
- `DELETE /v1/clients/{client}` (clients.delete)

### Client Contacts (shallow)

- `GET /v1/clients/{client}/contacts` (client-contacts.view)
- `POST /v1/clients/{client}/contacts` (client-contacts.create)
- `GET /v1/contacts/{contact}` (client-contacts.view)
- `PUT/PATCH /v1/contacts/{contact}` (client-contacts.update)
- `DELETE /v1/contacts/{contact}` (client-contacts.delete)

### Consents

- `GET /v1/consents` (consents.view)
- `GET /v1/consents/{consent}` (consents.view)
- `POST /v1/consents` (consents.create)
- `PUT/PATCH /v1/consents/{consent}` (consents.update)
- `DELETE /v1/consents/{consent}` (consents.delete)

### Client Consents (shallow)

- `GET /v1/clients/{client}/consents` (client-consents.view)
- `POST /v1/clients/{client}/consents` (client-consents.create)
- `GET /v1/consents/{clientConsent}` (client-consents.view)
- `PUT/PATCH /v1/consents/{clientConsent}` (client-consents.update)
- `DELETE /v1/consents/{clientConsent}` (client-consents.delete)

---

## Meetings

- `GET /v1/meetings` (meetings.view)
  - Filters: `client_id`, `user_id`, `status`, `per_page`
- `GET /v1/meetings/{meeting}` (meetings.view)
- `POST /v1/meetings` (meetings.create)
- `PUT/PATCH /v1/meetings/{meeting}` (meetings.update)
- `DELETE /v1/meetings/{meeting}` (meetings.delete)

### Meeting Analyses

- `GET /v1/meeting-analyses` (meeting-analyses.view)
  - Filters: `meeting_id`, `per_page`
- `GET /v1/meeting-analyses/{meeting_analysis}` (meeting-analyses.view)
- `POST /v1/meeting-analyses` (meeting-analyses.create)
- `PUT/PATCH /v1/meeting-analyses/{meeting_analysis}` (meeting-analyses.update)
- `DELETE /v1/meeting-analyses/{meeting_analysis}` (meeting-analyses.delete)

---

## Offers

- `GET /v1/offers` (offers.view)
  - Filters: `company_id`, `meeting_id`, `status`, `search`, `per_page`
- `GET /v1/offers/{offer}` (offers.view)
- `POST /v1/offers` (offers.create)
- `PUT/PATCH /v1/offers/{offer}` (offers.update)
- `DELETE /v1/offers/{offer}` (offers.delete)

### Offer Items (shallow)

- `GET /v1/offers/{offer}/items` (offer-items.view)
- `POST /v1/offers/{offer}/items` (offer-items.create)
- `GET /v1/items/{item}` (offer-items.view)
- `PUT/PATCH /v1/items/{item}` (offer-items.update)
- `DELETE /v1/items/{item}` (offer-items.delete)

### Offer Verifications (shallow)

- `GET /v1/offers/{offer}/verifications` (offer-verifications.view)
- `POST /v1/offers/{offer}/verifications` (offer-verifications.create)
- `GET /v1/verifications/{verification}` (offer-verifications.view)
- `PUT/PATCH /v1/verifications/{verification}` (offer-verifications.update)
- `DELETE /v1/verifications/{verification}` (offer-verifications.delete)

---

## Payrolls

- `GET /v1/payrolls` (payrolls.view)
  - Filters: `client_id`, `month`, `imported`, `per_page`
- `GET /v1/payrolls/{payroll}` (payrolls.view)
- `POST /v1/payrolls` (payrolls.create)
- `PUT/PATCH /v1/payrolls/{payroll}` (payrolls.update)
- `DELETE /v1/payrolls/{payroll}` (payrolls.delete)

### Payroll Items (shallow)

- `GET /v1/payrolls/{payroll}/items` (payroll-items.view)
- `POST /v1/payrolls/{payroll}/items` (payroll-items.create)
- `GET /v1/items/{item}` (payroll-items.view)
- `PUT/PATCH /v1/items/{item}` (payroll-items.update)
- `DELETE /v1/items/{item}` (payroll-items.delete)

---

## Calculations

- `GET /v1/calculations` (calculations.view)
  - Filters: `meeting_id`, `per_page`
- `GET /v1/calculations/{calculation}` (calculations.view)
- `POST /v1/calculations` (calculations.create)
- `PUT/PATCH /v1/calculations/{calculation}` (calculations.update)
- `DELETE /v1/calculations/{calculation}` (calculations.delete)

---

## Payroll Calculations

- `GET /v1/payroll-calculations` (payroll-calculations.view)
  - Filters: `company_id`, `employee_id`, `offer_id`, `period_year`, `period_month`, `source`, `per_page`
- `GET /v1/payroll-calculations/{payroll_calculation}` (payroll-calculations.view)
- `POST /v1/payroll-calculations` (payroll-calculations.create)
- `PUT/PATCH /v1/payroll-calculations/{payroll_calculation}` (payroll-calculations.update)
- `DELETE /v1/payroll-calculations/{payroll_calculation}` (payroll-calculations.delete)

---

## Calculator Configs

- `GET /v1/calculator-configs` (calculator-configs.view)
  - Filters: `scope`, `scope_id`, `key`, `is_active`, `effective_on`, `per_page`
- `GET /v1/calculator-configs/{calculator_config}` (calculator-configs.view)
- `POST /v1/calculator-configs` (calculator-configs.create)
- `PUT/PATCH /v1/calculator-configs/{calculator_config}` (calculator-configs.update)
- `DELETE /v1/calculator-configs/{calculator_config}` (calculator-configs.delete)

---

## Notifications

- `GET /v1/notifications` (notifications.view)
  - Filters: `user_id`, `type`, `unread`, `per_page`
- `GET /v1/notifications/{notification}` (notifications.view)
- `POST /v1/notifications` (notifications.create)
- `PUT/PATCH /v1/notifications/{notification}` (notifications.update)
- `DELETE /v1/notifications/{notification}` (notifications.delete)

---

## Documents

- `GET /v1/documents` (documents.view)
  - Filters: `client_id`, `type`, `per_page`
- `GET /v1/documents/{document}` (documents.view)
- `POST /v1/documents` (documents.create)
- `PUT/PATCH /v1/documents/{document}` (documents.update)
- `DELETE /v1/documents/{document}` (documents.delete)

---

## Metrics

- `GET /v1/metrics` (metrics.view)
  - Filters: `user_id`, `key`, `period`, `per_page`
- `GET /v1/metrics/{metric}` (metrics.view)
- `POST /v1/metrics` (metrics.create)
- `PUT/PATCH /v1/metrics/{metric}` (metrics.update)
- `DELETE /v1/metrics/{metric}` (metrics.delete)
