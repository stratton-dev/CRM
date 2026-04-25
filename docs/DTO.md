# CRM DTO Formats (from migrations)

Types use: string, integer, boolean, decimal, date, datetime, array, object.
Nullable fields are marked with `nullable`.

---

## OrganizationDTO

- id: integer
- type: string
- name: string
- nip: string
- regon: string, nullable
- krs: string, nullable
- address_json: object, nullable
- gus_synced_at: datetime, nullable
- created_at: datetime
- updated_at: datetime

---

## RoleDTO

- id: integer
- code: string
- name: string
- created_at: datetime
- updated_at: datetime

---

## PermissionDTO

- id: integer
- code: string
- description: string

---

## PermissionRoleDTO (pivot)

- role_id: integer
- permission_id: integer

---

## UserDTO

- id: integer
- supabase_id: string, nullable
- organization_id: integer, nullable
- role_id: integer, nullable
- name: string
- email: string
- email_verified_at: datetime, nullable
- phone: string, nullable
- password: string (hashed)
- active: boolean
- remember_token: string, nullable
- created_at: datetime
- updated_at: datetime

---

## CompanyDTO

- id: integer
- organization_id: integer, nullable
- name: string
- nip: string
- regon: string, nullable
- krs: string, nullable
- address_json: object, nullable
- address_line1: string
- address_line2: string, nullable
- postal_code: string
- city: string
- country: string
- email: string, nullable
- phone: string, nullable
- website: string, nullable
- notes: string, nullable
- industry: string, nullable
- vat_type: string, nullable
- employee_count: integer, nullable
- benefits_enabled: boolean
- created_at: datetime
- updated_at: datetime

---

## ClientDTO

Client uses the `companies` table. DTO is the same as `CompanyDTO`.

---

## EmployeeDTO

- id: integer
- company_id: integer
- first_name: string
- last_name: string
- email: string, nullable
- phone: string, nullable
- birth_date: date, nullable
- age: integer, nullable
- gender: string, nullable (M, K, O)
- contract_type: string
- zus_type: string
- kup: integer, nullable
- kup_percent: decimal, nullable
- tax_free_amount: integer, nullable
- kzp: boolean
- net_total: decimal, nullable
- net_cash: decimal, nullable
- active: boolean
- notes: string, nullable
- created_at: datetime
- updated_at: datetime

---

## ClientContactDTO

- id: integer
- client_id: integer
- name: string
- position: string, nullable
- phone: string, nullable
- email: string, nullable
- is_decision_maker: boolean
- created_at: datetime
- updated_at: datetime

---

## ConsentDTO

- id: integer
- code: string
- description: string
- required: boolean

---

## ClientConsentDTO

- id: integer
- client_id: integer
- consent_id: integer
- accepted_at: datetime
- source: string

---

## MeetingDTO

- id: integer
- client_id: integer
- user_id: integer
- status: string (open, completed, expired)
- calculation_shown: boolean
- valid_until: date
- created_at: datetime
- updated_at: datetime

---

## MeetingAnalysisDTO

- id: integer
- meeting_id: integer
- industry: string, nullable
- tax_model: string, nullable
- zus_cost_level: integer, nullable
- investments_planned: boolean, nullable
- expected_savings: integer, nullable
- debt_level: string, nullable

---

## CalculationDTO

- id: integer
- meeting_id: integer
- employee_count: integer
- savings_amount: integer
- valid_until: date
- created_at: datetime
- updated_at: datetime

---

## OfferDTO

- id: integer
- company_id: integer, nullable
- meeting_id: integer, nullable
- number: string, nullable
- token: string, nullable
- status: string (draft, sent, accepted, rejected, expired)
- valid_from: date, nullable
- valid_to: date, nullable
- opened_at: datetime, nullable
- expires_at: date, nullable
- currency: string
- commission_percent: decimal, nullable
- stratton_raise_percent: decimal, nullable
- subtotal_net: decimal, nullable
- total_vat: decimal, nullable
- total_gross: decimal, nullable
- total_discount: decimal, nullable
- meta: object, nullable
- notes: string, nullable
- created_at: datetime
- updated_at: datetime

---

## OfferItemDTO

- id: integer
- offer_id: integer
- name: string
- description: string, nullable
- qty: decimal
- unit: string
- unit_price: decimal
- discount_percent: decimal, nullable
- vat_rate: decimal
- line_net: decimal, nullable
- line_vat: decimal, nullable
- line_gross: decimal, nullable
- sort_order: integer
- meta: object, nullable
- created_at: datetime
- updated_at: datetime

---

## OfferVerificationDTO

- id: integer
- offer_id: integer
- type: string (email, sms)
- code: string
- verified_at: datetime, nullable

---

## PayrollDTO

- id: integer
- client_id: integer
- month: string
- imported: boolean
- created_at: datetime
- updated_at: datetime

---

## PayrollItemDTO

- id: integer
- payroll_id: integer
- employment_type: string (UOP, UZ)
- salary_gross: integer

---

## PayrollCalculationDTO

- id: integer
- company_id: integer, nullable
- employee_id: integer, nullable
- offer_id: integer, nullable
- created_by: integer, nullable
- period_year: integer, nullable
- period_month: integer, nullable
- engine_version: string, nullable
- config_version: string, nullable
- source: string (manual, import, api, ui)
- inputs_json: object
- outputs_json: object, nullable
- tags: array, nullable
- notes: string, nullable
- created_at: datetime
- updated_at: datetime

---

## CalculatorConfigDTO

- id: integer
- scope: string (global, company, offer)
- scope_id: integer, nullable
- key: string
- version: string, nullable
- value_json: object
- effective_from: date, nullable
- effective_to: date, nullable
- is_active: boolean
- created_by: integer, nullable
- notes: string, nullable
- created_at: datetime
- updated_at: datetime

---

## NotificationDTO

- id: integer
- user_id: integer
- type: string
- title: string
- body: string
- read_at: datetime, nullable
- created_at: datetime
- updated_at: datetime

---

## DocumentDTO

- id: integer
- client_id: integer
- type: string
- file_path: string
- signed_at: datetime, nullable
- created_at: datetime
- updated_at: datetime

---

## MetricDTO

- id: integer
- user_id: integer
- key: string
- value: integer
- period: string
