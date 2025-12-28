## Problem and Requirements

Organizations operating multiple products or brands often rely on license-based
access to control product usage. In such environments, individual brand systems
typically manage users, subscriptions, and billing independently, while end-user
products require a consistent and reliable way to validate entitlements.

Without a centralized approach, license data becomes fragmented across systems,
leading to challenges such as:
- Inconsistent license validation behavior across products
- Difficulty sharing or bundling entitlements between related offerings
- Increased complexity for end-user product activation and verification

The objective of this project is to design and implement a centralized License
Service that acts as a single source of truth for license lifecycle and product
entitlements across multiple brands or products.

The service is intentionally decoupled from user identity and billing concerns,
allowing brand systems to remain autonomous while integrating with a shared
licensing authority.

### Core Requirements

The License Service must:
- Support multiple brands or tenants with clear isolation
- Allow trusted systems to provision license keys and licenses for customers
- Associate multiple product licenses with a single license key
- Track license lifecycle states (valid, suspended, cancelled, expired)
- Expose APIs for end-user products to activate and validate licenses
- Provide controlled access for trusted systems to query licenses by customer email

### Non-Goals

The following responsibilities are explicitly out of scope for this service:
- User authentication or identity management
- Billing, subscription, or payment processing
- Customer-facing graphical user interfaces

These concerns remain owned by external systems integrating with the License Service.

## Architecture and Design

The Centralized License Service is designed as a standalone, API-driven backend
system that acts as the single source of truth for license lifecycle and product
entitlements across multiple brands.

The architecture emphasizes:
- clear separation of responsibilities
- explicit trust boundaries
- extensibility for future license-related capabilities
- simplicity over premature optimization

No graphical user interface is provided; all interactions with the system are
performed via HTTP APIs.

---

### Actors and Trust Boundaries

The system interacts with two distinct categories of actors, each with different
levels of trust and responsibilities.

#### Brand Systems (Trusted Clients)

Brand systems represent internal backend services operated by individual brands.
These systems are considered trusted and are responsible for integrating business
events such as purchases, renewals, and cancellations with the License Service.

Brand systems can:
- provision license keys and licenses for customers
- associate multiple licenses with a single license key
- update license lifecycle states (e.g. renew, suspend, cancel)
- query licenses by customer email across the ecosystem (restricted access)

Brand systems authenticate as brands and operate within a clearly scoped tenant
context. They do not represent end users directly.

Examples include:
- a subscription backend for a WordPress plugin vendor
- a billing system responsible for managing product purchases

---

#### End-User Products (Untrusted Clients)

End-user products are distributed applications that consume licenses, such as:
- WordPress plugins
- desktop applications
- command-line tools

These clients are considered untrusted and interact with the License Service using
license keys and instance identifiers.

End-user products can:
- activate a license key for a specific instance (e.g. site URL, machine ID)
- check license status and entitlements
- deactivate an activation to free a seat (if applicable)

End-user products never have access to brand-level data and are strictly limited
to operations related to their own license key.

---

### System Boundaries and Non-Goals

The License Service deliberately excludes responsibilities that belong to external
systems. In particular, it does not manage:

- user authentication or identity management
- billing, subscriptions, or payment processing
- customer-facing user interfaces

Brand systems remain the source of truth for users and billing, while the License
Service focuses exclusively on license lifecycle, entitlements, and activation
state.

This separation allows the License Service to remain reusable, brand-agnostic,
and easier to reason about operationally.

---

### High-Level Request Flow

At a high level, interactions with the License Service follow these flows:

1. A brand system provisions a license key and one or more licenses for a customer
   after a successful purchase.
2. The license key is delivered to the customer by the brand system.
3. An end-user product activates the license key for a specific instance.
4. The end-user product periodically validates the license status and entitlements.
5. Brand systems update license lifecycle as subscription state changes over time.

These flows are exposed through explicit, versioned APIs and are designed to be
idempotent and observable in a production environment.

---

### Multi-Tenancy Model (High-Level)

The system is designed as a multi-tenant service where each brand represents a
logical tenant.

All license keys, licenses, and products are scoped to a single brand. Data
isolation is enforced at the application level, ensuring that licenses and license
keys cannot span multiple brands.

Cross-brand queries (such as listing licenses by customer email) are restricted
to trusted brand systems and are not exposed to end-user products.

---
## How the Solution Satisfies Each User Story

This section describes how each user story is addressed by the system, explicitly
calling out which parts are fully implemented and which are currently design-only.

---

### US1: Brand can provision a license — **Implemented**

**User Story**

As a brand system, I can create license keys and licenses for a customer email and
associate them together to grant access to one or more products.

---

#### Implementation Overview

US1 is fully implemented through a brand-facing HTTP API and a dedicated domain
service responsible for license provisioning.

Brand systems can:

- Generate a new license key for a customer
- Associate one or more product-specific licenses with that license key
- Reuse an existing license key to attach additional licenses (e.g. add-ons)
- Retrieve the generated license key to transmit it to end users

Each license:

- Is associated with exactly one product
- Has a lifecycle status (`valid`, `suspended`, `cancelled`)
- Has an explicit expiration date

---

#### Key Components

**API Endpoint (Implemented)**


### Architectural Principles

The following principles guide the overall design:

- **Single source of truth**: All license and entitlement decisions originate from
  the License Service.
- **Explicit trust boundaries**: Brand systems and end-user products have clearly
  separated capabilities.
- **Extensibility**: The model supports future concepts such as seat limits,
  add-ons, and usage-based entitlements.
- **Operational simplicity**: The service is designed to be observable, testable,
  and easy to operate in production.



This endpoint is intended for trusted brand systems and supports:

- Creating a new license key
- Attaching one or more licenses to the key
- Optionally reusing an existing license key to attach additional licenses

**Domain Service (Implemented)**

`LicenseProvisioningService` encapsulates all business rules related to:

- License key creation and reuse
- Brand isolation and validation
- Product ownership validation
- License creation and association

All provisioning logic is executed within a database transaction to ensure
atomicity and consistency.

**Data Model (Implemented)**

The following entities are used to model the licensing domain:

- `Brand` – represents a tenant
- `Product` – represents a licensable product scoped to a brand
- `LicenseKey` – represents a customer-facing license key scoped to a brand
- `License` – represents a product-specific entitlement

Database constraints and application-level checks ensure that:

- License keys cannot span multiple brands
- Each license is associated with a single product
- Duplicate licenses for the same product and license key are prevented

---

#### Design Decisions and Trade-offs

- **Brand context resolution**  
  Brand identity is resolved via route parameters (`/brands/{brand}`) rather than
  authentication tokens. This simplifies the implementation while keeping the
  trust boundary explicit. Authentication is intentionally designed but not
  implemented at this stage.

- **License key format**  
  License keys are generated as opaque, human-readable strings. No cryptographic
  guarantees are provided at this stage, as key secrecy and rotation strategies
  are considered an evolvable concern.

- **User identity handling**  
  The service stores customer email as a reference only and does not manage user
  identities. This aligns with the non-goals of the system.

---

#### Status

- ✅ License key provisioning: **Implemented**
- ✅ Multiple licenses per key: **Implemented**
- ✅ Brand isolation: **Implemented**
- ✅ Add-on scenario (reuse key): **Implemented**
- 🟡 Authentication & authorization: **Designed-only**

---

