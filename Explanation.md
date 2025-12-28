## 1. Problem and Requirements

Organizations operating multiple products or brands often rely on licenses to
control access to features and services. In many real-world setups, each brand
manages licensing independently, while end-user products (e.g. plugins or apps)
need to validate entitlements in a consistent way.

Without a centralized license system, this leads to:
- fragmented license data across brands
- inconsistent validation logic between products
- difficulty bundling or sharing entitlements
- increased complexity in distributed clients

The goal of this project is to design and implement a **Centralized License
Service** that acts as a single source of truth for license lifecycle and product
entitlements across multiple brands.

The service is intentionally **decoupled** from:
- user identity management
- billing and subscriptions
- customer-facing UI

Brand systems remain responsible for users and payments, while this service
focuses exclusively on licensing concerns.

---

## 2. Architecture and Design

The Centralized License Service is a standalone, API-driven backend system.

### Core Design Principles

- **Single source of truth** for license state and entitlements
- **Explicit trust boundaries** between brand systems and end-user products
- **Clear domain separation** between license keys and licenses
- **Extensibility** for future concepts such as activations and seat limits
- **Simplicity over premature optimization**

No graphical interface is provided; all interactions happen via HTTP APIs.

---

### Actors and Trust Boundaries

#### Brand Systems (Trusted Clients)

Brand systems are internal backend services operated by each brand.
They are trusted and allowed to manage license data.

Brand systems can:
- create license keys
- create and associate licenses
- update license lifecycle
- query license data for their own customers

Brand systems operate in a **brand-scoped tenant context** and never act on behalf
of end users.

---

#### End-User Products (Untrusted Clients)

End-user products are distributed applications such as:
- WordPress plugins
- desktop applications
- CLI tools

They authenticate using license keys and instance identifiers and are treated as
untrusted clients.

---

### Domain Model (High Level)

```

Brand
└── LicenseKey
└── License (one per product)

```

- A **License Key** is a customer-facing token used by end users.
- A **License** represents entitlement to a single product.
- Multiple licenses can be grouped under a single license key.
- Lifecycle state (`valid`, `suspended`, `cancelled`, `expired`) belongs to the
  license, not the license key.

---

### License Activation Model (US3)

License activation represents the association between a license key and a specific
end-user instance (e.g. site URL, machine ID, or host).

An activation indicates that a license key is currently in use on a given instance.
This allows the system to track usage and enables future enforcement of seat limits.

```

Brand
└── LicenseKey
├── License (product entitlement)
└── Activation (instance usage)

```

Key characteristics:
- Activations belong to license keys, not individual licenses
- A single activation can unlock multiple product licenses
- Instance identifiers are opaque strings provided by end-user products
- Deactivation is supported at the data model level but not yet exposed via API

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

The action-based approach keeps lifecycle logic centralized and extensible.

---

### Explicit Brand Context in Routes

**Decision:**
Include `{brand}` in brand-facing routes.

**Why:**

* Makes trust boundaries explicit
* Avoids hidden tenant resolution
* Simplifies reasoning during review

Authentication is intentionally designed but not implemented at this stage.

---

### Error Handling via Domain Exceptions

**Decision:**
Invalid transitions raise domain exceptions.

**Trade-off:**
These currently surface as HTTP 500 responses.

**Reasoning:**
Keeps the domain model clean and explicit. HTTP error mapping is documented as a
future improvement.

---

### Activation at License-Key Level

**Decision:**
Activations are associated with license keys rather than individual licenses.

**Why:**
End-user products typically activate a single license key that unlocks multiple
products or add-ons.

**Alternative considered:**
Tracking activations per license.

**Why rejected:**
It would require multiple activations per instance and complicate validation.

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

* Add `max_activations` at license or license-key level
* Count active activations (`deactivated_at IS NULL`)
* Enforce limits transactionally
* Reject activations when exceeded

**Why not implemented:**
Seat limits add concurrency and policy complexity beyond the scope of this exercise.

---

### Scaling and Evolution Plan

* Add authentication and authorization per brand
* Enforce seat limits
* Introduce audit logs and domain events
* Cache read-heavy validation endpoints
* Map domain errors to proper HTTP status codes

---

## 4. How the Solution Satisfies Each User Story

### User Story 1: Brand Can Provision a License

**Status:** ✅ Implemented

Brands can create license keys and associate one or more licenses with them.
Brand ownership and isolation are enforced.

---

### User Story 2: Brand Can Change License Lifecycle

**Status:** ✅ Implemented

Brands can renew, suspend, resume, or cancel licenses.
Lifecycle transitions are enforced via a domain-level state machine.

---

### User Story 3: End-User Product Can Activate a License

**Status:** ✅ Implemented (seat limits designed only)

End-user products can activate a license key for a specific instance and retrieve
valid entitlements.

**API Endpoint:**

```
POST /api/activate
```

Example request:

```json
{
  "license_key": "ABCDE-12345",
  "instance_id": "https://example.com"
}
```

Example response:

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

**Out of scope (by design):**

* Seat limit enforcement
* License deactivation
* Abuse protection

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

### Example API Calls

#### US1: Provision License

```bash
curl -X POST http://localhost:8000/api/brands/1/license-keys \
  -H "Content-Type: application/json" \
  -d '{
    "customer_email": "user@example.com",
    "licenses": [
      {
        "product_code": "rankmath",
        "expires_at": "2026-01-01"
      }
    ]
  }'
```

---

#### US2: Change License Lifecycle

```bash
PATCH /api/brands/1/licenses/1
```

```json
{ "action": "suspend" }
```

---

#### US3: Activate License Key

```bash
curl -X POST http://localhost:8000/api/activate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "TEST-ACTIVATION-KEY",
    "instance_id": "https://example.com"
  }'
```

---

## 6. Known Limitations and Next Steps

### Known Limitations

* No authentication or authorization layer
* Domain exceptions return HTTP 500 responses
* No audit logs
* No rate limiting
* Seat limits not enforced
* Activation deactivation not exposed via API

### Next Steps

* Implement license validation (US4)
* Map domain errors to proper HTTP status codes
* Add authentication for brand systems
* Add observability (logs, metrics, tracing)
-----
