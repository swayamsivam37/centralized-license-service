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

### Actors

The Centralized License Service is a standalone backend service that acts as the
single source of truth for licenses and entitlements across multiple brands.

The system is designed around clear boundaries between actors and responsibilities,
favoring explicit APIs and loose coupling.

#### Brand Systems (Trusted Clients)

Brand systems represent internal backend services operated by individual brands
(e.g., RankMath, WP Rocket).

Brand systems are responsible for:
- Creating license keys
- Creating and associating licenses to license keys
- Updating license lifecycle (renew, suspend, cancel)
- Listing licenses by customer email across the ecosystem

Brand systems authenticate as brands and operate in a trusted context.
They do not represent end users directly.

Examples:
- rankmath.com backend
- wp-rocket.me backend

---

#### End-User Products (Untrusted Clients)

End-user products are distributed applications that consume licenses, such as:
- WordPress plugins
- Desktop applications
- CLI tools

End-user products are responsible for:
- Activating a license key for a specific instance (site URL, machine ID, host)
- Checking license status and entitlements
- Deactivating an activation when an instance is removed

End-user products authenticate using license keys and instance identifiers
and must be treated as untrusted clients.

---

### System Boundaries

The Centralized License Service deliberately excludes responsibilities that belong
to brand-specific systems.

Out of scope responsibilities include:
- User authentication and identity management
- Billing, subscriptions, and payments
- Customer-facing user interfaces

Brand systems remain the source of truth for users and billing,
while the License Service focuses exclusively on license lifecycle,
entitlements, and activation state.

---

### High-Level Flow

1. A brand system provisions a license key and associated licenses
   for a customer email after a successful purchase.
2. The license key is delivered to the end user by the brand system.
3. An end-user product activates the license key for a specific instance.
4. The product periodically validates the license status and entitlements.
5. Brand systems may query or update licenses as subscription states change.