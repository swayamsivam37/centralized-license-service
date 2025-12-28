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

```

Brand
└── LicenseKey
├── License (product entitlement)
└── Activation (instance usage)

```

Key characteristics:
- Activations belong to license keys, not individual licenses
- A single activation unlocks multiple product licenses
- Instance identifiers are opaque strings
- Deactivation is supported at the data level but not yet exposed via API

---

## 3. Trade-offs and Design Decisions

### License-Level Lifecycle vs License-Key-Level Lifecycle

**Decision:**  
Lifecycle state is attached to individual licenses.

**Why:**  
Allows partial suspension or expiration of products under one key.

---

### Single Lifecycle Endpoint with Action-Based Payload

**Decision:**  
One endpoint with an `action` field:

```

PATCH /api/brands/{brand}/licenses/{license}

````

```json
{ "action": "suspend" }
````

**Why rejected alternatives:**
Multiple endpoints increase API surface and duplication.

---

### Domain Exceptions vs HTTP Errors (US4)

**Decision:**
Domain services throw exceptions; controllers map them to HTTP responses.

**Why:**
Keeps domain logic transport-agnostic and reusable.

**Example:**
Invalid license key → `InvalidArgumentException` → HTTP `404`.

---

### Seat Limits (Designed, Not Implemented)

Seat limits are intentionally not enforced.

**Intended approach:**

* Add `max_activations`
* Count active activations
* Enforce transactionally

---

## 4. How the Solution Satisfies Each User Story

### US1: Brand Can Provision a License

**Status:** ✅ Implemented

Brands can create license keys and associate one or more licenses.

---

### US2: Brand Can Change License Lifecycle

**Status:** ✅ Implemented

Renew, suspend, resume, and cancel operations are enforced by domain rules.

---

### US3: End-User Product Can Activate a License

**Status:** ✅ Implemented (seat limits designed only)

**API:**

```
POST /api/activate
```

```json
{
  "license_key": "ABCDE-12345",
  "instance_id": "https://example.com"
}
```

---

### US4: User Can Check License Status

**Status:** ✅ Implemented

End users can validate a license key and retrieve active entitlements.

**API:**

```
POST /api/validate
```

**Example request:**

```json
{
  "license_key": "ABCDE-12345",
  "instance_id": "https://example.com"
}
```

**Example response (valid):**

```json
{
  "status": "valid",
  "licenses": [
    {
      "product_code": "rankmath",
      "status": "valid",
      "expires_at": "2026-01-01"
    }
  ],
  "seats": {
    "used": 1,
    "remaining": null
  }
}
```

**Example response (invalid key):**

```json
{
  "message": "Invalid license key."
}
```

---

## 5. How to Run Locally

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

#### US4: Validate License

```bash
curl -X POST http://localhost:8000/api/validate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "ABCDE-12345",
    "instance_id": "https://example.com"
  }'
```

---

## 6. Known Limitations and Next Steps

### Known Limitations

* No authentication
* No rate limiting
* No audit logs
* Seat limits not enforced
* Activation deactivation not exposed

### Next Steps

* US5: Deactivate seat
* US6: Cross-brand license lookup
* Add auth & observability


