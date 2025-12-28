# Centralized License Service

A backend service that acts as a **single source of truth for license lifecycle,
entitlements, and activations** across multiple brands and products.

This project was implemented as a technical assessment and demonstrates system
design, API design, domain modeling, testing strategy, and documentation quality.

---

## Overview

Modern organizations often operate multiple products or brands, each with its own
billing and customer management systems. End-user products (plugins, apps, tools)
still need a **consistent way to validate licenses and entitlements**.

This service provides:

- Centralized license lifecycle management
- Shared license keys across multiple products
- End-user activation and validation APIs
- Clear trust boundaries between brands and end users

The service is intentionally **decoupled from billing, user accounts, and UI**.

---

## Key Concepts

- **Brand** – Logical tenant representing a product ecosystem
- **License Key** – Customer-facing token used by end users
- **License** – Entitlement to a single product
- **Activation** – Usage of a license key by an instance (site, machine, host)

---

## Supported User Stories

| User Story | Description | Status |
|-----------|-------------|--------|
| US1 | Brand can provision license keys and licenses | ✅ Implemented |
| US2 | Brand can change license lifecycle | ✅ Implemented |
| US3 | End-user product can activate a license | ✅ Implemented |
| US4 | End-user can check license status | ✅ Implemented |
| US5 | End-user can deactivate a seat | 🧩 Designed only |
| US6 | Brand can list licenses by customer email | ✅ Implemented |

Detailed architecture, trade-offs, and design decisions are documented in
**Explanation.md**.

---

## API Overview

### Brand APIs (Trusted)

- Create license keys and licenses
- Add licenses to existing keys
- Update license lifecycle
- Query licenses by customer email (including cross-brand queries)

### End-User APIs (Untrusted)

- Activate a license key
- Validate license status and entitlements

---

## Setup & Installation

### Prerequisites

Ensure the following are installed on your system:

- **PHP 8.2+**
- **Composer**
- **Git**
- **SQLite** (default database, no setup required)

Optional (for local frontend tooling, not required for this assessment):
- Node.js 18+

---

### Clone the Repository

```bash
git clone https://github.com/<your-username>/centralized-license-service.git
cd centralized-license-service
````

---

### Install Dependencies

```bash
composer install
```

---

### Environment Setup

```bash
cp .env.example .env
php artisan key:generate
```

---

### Database Setup

This project uses SQLite by default.

```bash
php artisan migrate
```

---

### Run the Application

```bash
php artisan serve
```

The API will be available at:

```
http://localhost:8000
```

---

## Example API Calls

### US1 – Create License Key with License

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

### US2 – Change License Lifecycle

```bash
curl -X PATCH http://localhost:8000/api/brands/1/licenses/1 \
  -H "Content-Type: application/json" \
  -d '{ "action": "suspend" }'
```

---

### US3 – Activate License Key

```bash
curl -X POST http://localhost:8000/api/activate \
  -H "Content-Type: application/json" \
  -d '{
    "license_key": "ABCDE-12345",
    "instance_id": "https://example.com"
  }'
```

---

### US4 – Validate License Key

```bash
curl -X POST http://localhost:8000/api/validate \
  -H "Content-Type: application/json" \
  -d '{ "license_key": "ABCDE-12345" }'
```

---

### US6 – List Licenses by Customer Email

```bash
curl -X GET "http://localhost:8000/api/licenses?email=user@example.com"
```

---

## Testing

The project includes feature tests for all implemented user stories.

```bash
php artisan test
```

CI automatically runs:

* Laravel Pint (linting)
* PHPUnit tests

---

## Architecture & Design

* API-first design, no UI
* Explicit trust boundaries
* Domain services enforce business rules
* Idempotent activation behavior
* Designed for extensibility (seat limits, audit logs)

Refer to **Explanation.md** for full details on architecture, trade-offs,
scaling plan, and implemented vs designed-only features.

---

## Known Limitations

* No authentication or authorization layer
* Domain exceptions return HTTP 500 responses
* Seat limits not enforced
* No rate limiting
* License deactivation (US5) not exposed via API

These are intentional trade-offs documented in the design.

---

## License

This project is licensed under the MIT License.
---