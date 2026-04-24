# CRM – Domain Rules

This document describes the business rules of the CRM
in human-readable, non-technical language.

These rules MUST be enforced by the backend.

---

## 1. Client & Consents

### Rule: Required consents

- A client must accept ALL required consents
- Without required consents:
  - The system blocks meeting creation
  - User cannot proceed to next steps

Source:
- Business requirement
- Legal compliance

---

## 2. Meeting Rules

### Rule: Meeting validity

- A meeting is valid for 90 days from creation
- After 90 days:
  - Meeting becomes expired
  - No new calculations or offers are allowed

### Rule: Meeting ownership

- Advisor who created the meeting is the owner
- Manager and Director can view all meetings
- Only Director can delete meetings

---

## 3. Calculation Rules

### Rule: Calculation availability

- Calculation can be created only for:
  - Active meeting
  - Non-expired meeting

### Rule: Calculation validity

- Each calculation is valid for 14 days
- After expiration:
  - Offer generation is blocked
  - New calculation must be generated

---

## 4. Offer Rules

### Rule: One offer per meeting

- Only one active offer may exist per meeting
- Generating a second offer is forbidden

### Rule: Offer access

- Offer is accessed via public token (email/SMS)
- No authentication required
- Token expiration MUST be enforced

---

## 5. Notifications Rules

### Rule: Automatic notifications

The system automatically sends notifications when:
- Meeting is created
- Offer is opened by client
- Offer or meeting is about to expire

Notifications are informational and auditable.

---

## 6. System Blocking Rules

The system MUST block progress when:
- Required consents are missing
- Meeting is expired
- Calculation is expired
- Offer already exists

Blocking must be explicit and visible to the user.

---

## 7. Audit & Traceability

Every important action must be traceable:
- Who performed the action
- When it happened
- What entity was affected

Events are preferred for audit-related logic.
