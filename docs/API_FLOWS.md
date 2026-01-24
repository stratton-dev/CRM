# CRM – API Flows

This document describes how the CRM backend behaves
from the perspective of the frontend user.

No technical knowledge is required to understand this flow.

---

## 1. Client Onboarding Flow

1. User adds a client using NIP
2. System fetches company data (GUS)
3. User fills missing information
4. User completes required consents
5. System allows moving to meetings

If consents are missing:
- System blocks further steps

---

## 2. Meeting Flow

1. Advisor creates a meeting
2. System sets validity to 90 days
3. Manager and Director see the meeting immediately
4. Meeting appears in dashboards

After 90 days:
- Meeting expires automatically

---

## 3. Calculation Flow

1. Advisor enters employee and payroll data
2. System generates a calculation
3. Calculation is valid for 14 days
4. Advisor may generate an offer during this time

After 14 days:
- Calculation expires
- Offer generation is blocked

---

## 4. Offer Flow

1. Advisor generates an offer
2. System sends email and SMS to client
3. Client opens offer using secure link
4. System marks offer as opened
5. System notifies internal users

If the offer expires:
- Client access is blocked
- Advisor must generate a new calculation

---

## 5. Notification Flow

Notifications are generated automatically when:
- Meetings are created
- Offers are opened
- Expiration dates are approaching

Users see notifications based on their role.

---

## 6. Permissions Flow

- Each action requires explicit permission
- Permissions are checked before the action is executed
- Users only see actions they are allowed to perform

The frontend does not decide permissions.
The backend is the source of truth.

---

## 7. Error & Blocking Flow

When an action is blocked:
- Backend returns a clear error message
- Frontend displays the reason
- User knows what must be fixed to proceed

Silent failures are forbidden.
