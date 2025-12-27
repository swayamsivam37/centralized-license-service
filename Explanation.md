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
