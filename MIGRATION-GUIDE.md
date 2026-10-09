# Migrating this fork to FluentCart

This version removes the original cart integration and registers InstaWP as a FluentCart product integration. It does not automatically migrate products, historical orders or subscriptions between cart systems.

Configure new InstaWP feeds on FluentCart products and use variation conditions to map FluentCart variant IDs. Existing API key, team and site tracking rows remain intact. New site rows have source `fluentcart`; legacy rows are not linked to new orders by numeric ID.

Replace old customer dashboard pages with `[iwp_fluentcart_sites]`. `[iwp_onboarding]` now renders the FluentCart portal. Post-payment setup is shown on the FluentCart thank-you page; the old checkout fields and redirects have been removed. Configure deferred creation in each product feed.

Automatic updates now use this fork's GitHub releases, so upstream releases cannot overwrite the FluentCart integration. Keep the plugin directory named `iwp-wp-integration`.
