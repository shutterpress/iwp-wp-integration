# FluentCart InstaWP Provisioning

Provision InstaWP sites from paid FluentCart purchases, with snapshots, hosting plans, subscriptions, and customer site management.

## Setup

1. Activate FluentCart and this plugin. Configure your InstaWP API key and team under **InstaWP → Settings**.
2. Enable integration and automatic site creation.
3. Add an **InstaWP** feed in a FluentCart product’s **Integrations** tab. Select a snapshot, optional plan, expiry, and optional deferred setup. Use variation conditions for variant-specific plans.
4. Complete a test purchase. Sites and deferred setup appear on the FluentCart thank-you page.
5. Add `[fcip_sites]` to an account page for returning customers. Guest access uses the private FluentCart `order_hash` receipt credential. `[fcip_site_creator]` provides standalone snapshot creation.

## Behavior

- One site per paid order line item. Persisted request states and atomic locks prevent duplicates from repeated hooks, overlapping feeds, and concurrent requests.
- Pending, partially paid, canceled, failed, and fully refunded orders do not provision. Renewals manage existing sites.
- Customers can view site status, receive credentials, use magic login, clear cache, and map hosting domains.
- Subscription events manage reservations; plan changes update existing sites. Deferred setup collects an optional admin username and subdomain after payment.
- Ambiguous API failures are held for administrator review to avoid duplicate remote provisioning.

This standalone plugin uses the `FCIP_` class prefix, `fcip_` options, hooks and database tables, and the `fluentcart-instawp-provisioning` folder and text domain. Activation creates its complete schema and fresh defaults. It has no installation migration or compatibility aliases.

See [documentation](docs/index.md). The integration targets FluentCart’s native product feed API and documented event payloads, inspected at version 1.7.1. Verify checkout, subscriptions, and plan changes with your installed payment modules in staging.

## Development

Run `php tests/fluentcart.php`, `php tests/reservations.php`, and `php tests/install.php`. CI checks PHP 7.4 and 8.3 behavior and syntax, JavaScript syntax, and dependency isolation. Use `bash build-zip.sh` to create a ZIP with the correct plugin folder.

Source repository: [shutterpress/iwp-wp-integration](https://github.com/shutterpress/iwp-wp-integration). Licensed under GPL-2.0-or-later; derived from InstaWP’s GPL plugin.
