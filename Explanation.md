# Explanation.md

## 1. Problem and Requirements

Organizations operating multiple products or brands often rely on licenses to
control access to features and services. In many real-world setups, each brand
manages licensing independently, while end-user products (e.g. plugins or apps)
need to validate entitlements in a consistent way.

Without a centralized license system, this leads to:
- Fragmented license data across brands
- Inconsistent validation logic between products
- Difficulty bundling or sharing entitlements
- Increased complexity in distributed clients

The goal of this project is to design and implement a **Centralized License
Service** that acts as a single source of truth for license lifecycle and product
entitlements across multiple brands.

The service is intentionally **decoupled** from:
- User identity management
- Billing and subscriptions
- Customer-facing UI

Brand systems remain responsible for users and payments, while this service
focuses exclusively on licensing concerns.

---

## 2. Architecture and Design

The Centralized License Service is a standalone, API-driven backend system.

### Core Design Principles

- **Single source of truth** for license state and entitlements
- **Explicit trust boundaries** between brand systems and end-user products
- **Clear domain separation** between license keys, licenses, and activations
- **Extensibility** for future concepts such as activations and seat limits
- **Simplicity over premature optimization**

No graphical interface is provided; all interactions happen via HTTP APIs.

---

### Actors and Trust Boundaries

#### Brand Systems (Trusted Clients)

Brand systems are internal backend services operated by each brand.
They are trusted and allowed to manage license data.

Brand systems can:
- Create license keys
- Create and associate licenses
- Update license lifecycle
- Query license data for customers (including cross-brand queries)

Brand systems operate in a **brand-scoped tenant context** and never act on behalf
of end users.

---

#### End-User Products (Untrusted Clients)

End-user products are distributed applications such as:
- WordPress plugins
- Desktop applications
- CLI tools

They authenticate using license keys and instance identifiers and are treated as
untrusted clients.

---

### Domain Model (High Level)

```

Brand
└── LicenseKey
├── License (one per product)
└── Activation (instance usage)

```

- A **License Key** is a customer-facing token used by end users
- A **License** represents entitlement to a single product
- Multiple licenses can be grouped under a single license key
- Lifecycle state belongs to the license, not the license key
- Activations represent usage by a specific instance

---

## 3. Trade-offs and Design Decisions

### License-Level Lifecycle vs License-Key-Level Lifecycle

**Decision:**  
Lifecycle state is attached to individual licenses, not license keys.

**Why:**  
This allows independent management of products under a shared license key
(e.g. suspending an add-on without cancelling the main product).

**Alternative considered:**  
Applying lifecycle state at the license key level.

**Why rejected:**  
It would prevent partial suspension or expiration of individual products.

---

### Single Lifecycle Endpoint with Action-Based Payload

**Decision:**  
Use a single endpoint for lifecycle changes:

```

PATCH /api/brands/{brand}/licenses/{license}

````

Payload example:
```json
{ "action": "suspend" }
````

**Alternative considered:**

* `/suspend`
* `/resume`
* `/cancel`
* `/renew`

**Why rejected:**

* Larger API surface
* Harder to evolve
* More duplication

---

### Explicit Brand Context in Routes

**Decision:**
Include `{brand}` in brand-facing routes.

**Why:**

* Makes trust boundaries explicit
* Avoids hidden tenant resolution
* Simplifies reasoning during review

Authentication is intentionally designed but not implemented.

---

### Activation at License-Key Level

**Decision:**
Activations are associated with license keys rather than individual licenses.

**Why:**
End-user products activate a single license key that unlocks multiple products.

**Alternative considered:**
Tracking activations per license.

**Why rejected:**
Would require multiple activations per instance and complicate validation.

---

### Idempotent Activation Requests

**Decision:**
Activating the same license key for the same instance is idempotent.

**Why:**
Distributed clients may retry activation due to network failures.

**Trade-off:**
No explicit signal is returned indicating whether activation was newly created.

---

### Seat Limits (Designed but Not Implemented)

**Decision:**
Seat limits are intentionally not enforced.

**Intended approach:**

* Add `max_activations`
* Count active activations (`deactivated_at IS NULL`)
* Enforce limits transactionally
* Reject activation when exceeded

**Why not implemented:**
Adds concurrency and policy complexity beyond the scope of this exercise.

---

### Cross-Brand License Queries (US6)

**Decision:**
Allow cross-brand queries by customer email for trusted brand systems.

**Trade-off:**
Requires strong authentication and authorization in real deployments.

---

### Scaling and Evolution Plan

* Add authentication and RBAC
* Enforce seat limits
* Introduce audit logs and domain events
* Cache read-heavy validation endpoints
* Map domain errors to proper HTTP status codes

---

## 4. How the Solution Satisfies Each User Story

### User Story 1: Brand Can Provision a License

**Status:** ✅ Implemented

**API: Create license key with licenses**

```
POST /api/brands/{brand}/license-keys
```

Request:

```json
{
  "customer_email": "user@example.com",
  "licenses": [
    {
      "product_code": "rankmath",
      "expires_at": "2026-01-01"
    }
  ]
}
```

Response:

```json
{
  "license_key": "ABCDE-12345",
  "licenses": [
    {
      "product_code": "rankmath",
      "status": "valid",
      "expires_at": "2026-01-01"
    }
  ]
}
```

**Add additional license**

```
POST /api/brands/{brand}/license-keys/{licenseKey}/licenses
```

---

### User Story 2: Brand Can Change License Lifecycle

**Status:** ✅ Implemented

**API**

```
PATCH /api/brands/{brand}/licenses/{license}
```

Request:

```json
{ "action": "suspend" }
```

Response:

```json
{
  "id": 1,
  "status": "suspended"
}
```

---

### User Story 3: End-User Product Can Activate a License

**Status:** ✅ Implemented (seat limits designed only)

**API**

```
POST /api/activate
```

Request:

```json
{
  "license_key": "ABCDE-12345",
  "instance_id": "https://example.com"
}
```

Response:

```json
{
  "status": "active",
  "licenses": [
    {
      "product_code": "rankmath",
      "status": "valid",
      "expires_at": "2026-01-01"
    }
  ]
}
```

---

### User Story 4: User Can Check License Status

**Status:** ✅ Implemented

**API**

```
POST /api/validate
```

Request:

```json
{
  "license_key": "ABCDE-12345"
}
```

Response:

```json
{
  "status": "valid",
  "licenses": [
    {
      "product_code": "rankmath",
      "expires_at": "2026-01-01"
    }
  ],
  "seats": {
    "used": 1,
    "remaining": null
  }
}
```

---

### User Story 5: Deactivate a Seat

**Status:** 🧩 Designed Only

**Intended API**

```
POST /api/deactivate
```

Request:

```json
{
  "license_key": "ABCDE-12345",
  "instance_id": "https://example.com"
}
```

---

### User Story 6: Brand Can List Licenses by Customer Email

**Status:** ✅ Implemented

**API**

```
GET /api/licenses?email=user@example.com
```

Response:

```json
[
  {
    "brand": "rankmath",
    "product": "rankmath",
    "status": "valid",
    "expires_at": "2026-01-01"
  },
  {
    "brand": "wp-rocket",
    "product": "wp-rocket",
    "status": "valid",
    "expires_at": "2025-12-31"
  }
]
```

---

## 5. How to Run Locally

### Prerequisites

* PHP 8.2+
* Composer
* SQLite

### Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

---

## 6. Known Limitations and Next Steps

### Known Limitations

* No authentication or authorization
* Domain exceptions return HTTP 500
* No audit logs
* No rate limiting
* Seat limits not enforced
* Deactivation not exposed via API

### Next Steps

* Implement US5 deactivation endpoint
* Add authentication and RBAC
* Add observability (logs, metrics, tracing)
* Improve error handling
----