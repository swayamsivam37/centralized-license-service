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

(Activation and validation are covered in later user stories.)

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

## 3. Trade-offs and Design Decisions

### License-level lifecycle vs License-key-level lifecycle

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

### Single lifecycle endpoint with action-based payload

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
Separate endpoints such as:

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

### Explicit brand context in routes

**Decision:**
Include `{brand}` in brand-facing routes.

**Why:**

* Makes trust boundaries explicit
* Avoids hidden tenant resolution
* Simplifies reasoning during review

Authentication is intentionally designed but not implemented at this stage.

---

### Error handling via domain exceptions

**Decision:**
Invalid transitions raise domain exceptions.

**Trade-off:**
These currently surface as HTTP 500 responses.

**Reasoning:**
Keeps the domain model clean and explicit. HTTP error mapping is documented as a
future improvement.

---

### Scaling and Evolution Plan

* Add authentication and authorization per brand
* Introduce activation tracking and seat limits
* Add audit logs and domain events
* Introduce caching for read-heavy validation endpoints
* Map domain errors to proper HTTP status codes

---

## 4. How the Solution Satisfies Each User Story

### User Story 1: Brand Can Provision a License

**Summary:**
Brands can create license keys and associate one or more licenses (products) with
them for a customer email.

**Status:**
✅ Implemented

**How it is satisfied:**

* Brands can create license keys
* Licenses are product-scoped
* Multiple licenses can share a single license key
* Brand ownership is enforced

**Out of scope (by design):**

* Billing
* User accounts
* UI

---

### User Story 2: Brand Can Change License Lifecycle

**Summary:**
Brands can renew, suspend, resume, or cancel individual licenses.

**Status:**
✅ Implemented

**How it is satisfied:**

* Lifecycle logic is centralized in `LicenseLifecycleService`
* Valid transitions are enforced via a state machine
* Cancelled licenses are treated as terminal
* Brand ownership is validated at the domain level
* API-level feature tests cover valid and invalid scenarios

**Lifecycle rules:**

* `valid → suspended`
* `suspended → valid`
* `valid → renewed`
* `valid | suspended → cancelled`
* `cancelled → (no further transitions)`

---

## 5. How to Run Locally

### Prerequisites

* PHP 8.2+
* Composer
* SQLite (default)

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

#### Create a license key with a license (US1)

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

#### Change license lifecycle (US2)

```bash
PATCH /api/brands/1/licenses/1
```

```json
{
  "action": "suspend"
}
```

---

## 6. Known Limitations and Next Steps

### Known Limitations

* No authentication or authorization layer
* Domain exceptions currently map to HTTP 500 responses
* No audit logs or lifecycle history
* No rate limiting or abuse protection

### Next Steps

* Implement license activation and validation (US3, US4)
* Introduce proper HTTP error mapping
* Add authentication for brand systems
* Add observability (logs, metrics, tracing)
-----