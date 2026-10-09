# FluentCart Products

In FluentCart, edit a product and open **Integrations → Add Integration → InstaWP**. Configure:

- Feed title and InstaWP snapshot.
- Optional hosting plan.
- Permanent or temporary site, with temporary lifetime in hours.
- Optional deferred setup so customers choose a username and subdomain after payment.
- Subscription reservation management, enabled by default.

Use FluentCart's variation condition selector to map individual variants to different snapshots or plans. Configure only one matching feed per variant. Multiple matching feeds cannot create duplicate sites, but the first matching feed determines initial provisioning.

The integration always runs after successful payment (`order_paid_done`). It cannot be configured to provision from an unpaid order or renewal. Provisioning creates one site per order line item, regardless of quantity.
