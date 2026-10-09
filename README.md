# InstaWP Integration for FluentCart

FluentCart-only fork of the InstaWP WordPress integration. Paid product purchases provision sites from InstaWP snapshots and optionally attach hosting plans. WooCommerce code and dependencies have been removed.

## Setup

1. Activate FluentCart and this plugin, then set your API key and team in **InstaWP → Settings**.
2. Enable integration and automatic site creation.
3. Add an **InstaWP** feed in a FluentCart product's **Integrations** tab. Select a snapshot, optional plan, expiry, and optional deferred setup. Use variation conditions for variant-specific plans.
4. Complete a test purchase. Sites and deferred setup appear on the FluentCart thank-you page; status and order activity appear in the native admin screens.
5. Add `[iwp_fluentcart_sites]` to an account page for returning customers. `[iwp_onboarding]` is a FluentCart portal alias. Guest access uses the private FluentCart `order_hash` receipt credential.

## Behavior

- One site per paid order line item, matching the original provisioning behavior. Repeated hooks, overlapping feeds and concurrent requests do not duplicate sites.
- Pending, partially paid, canceled, failed and fully refunded orders do not provision. Renewals update access to existing sites rather than creating more sites.
- Permanent/temporary expiry, snapshot/plan selection, guest/customer ownership, async task tracking, admin credentials release, magic login, cache purge and hosting-domain mapping use the shared InstaWP core.
- Subscription events change site reservations; FluentCart plan-change events update the existing site's hosting plan.
- Deferred setup collects optional admin username and subdomain after payment. Ambiguous API failures are held for administrator review to avoid duplicate remote provisioning.
- Standalone snapshot creation remains available. The updater checks this fork's releases.

See [documentation](docs/index.md) and [migration notes](MIGRATION-GUIDE.md). This integration targets FluentCart's native product feed API and documented event payloads (source inspected at version 1.7.1). Verify checkout, subscription and plan-change flows against your installed FluentCart/payment modules in staging before going live.

## Checks

Run `php tests/fluentcart.php` for standalone provisioning/lifecycle/ownership tests. CI runs PHP syntax and behavior checks on PHP 7.4 and 8.3, JavaScript syntax checks, and a guard against cart code depending on the removed integration.

Original InstaWP code remains GPL-2.0-or-later. This is a fork maintained in `shutterpress/iwp-wp-integration`.
