# Troubleshooting

- **InstaWP missing in product integrations:** activate FluentCart, configure the API key and enable the integration in InstaWP settings.
- **No site created:** check the feed is enabled and matches the purchased variant, payment is fully paid, and automatic creation is enabled. Renewal and fee line items do not create sites.
- **Site remains in progress:** check WP-Cron and the InstaWP task status. Refresh the account or thank-you page.
- **Credentials hidden:** release them with **InstaWP → Sites → Send Credentials**, or turn off delayed credentials.
- **Setup awaiting review:** inspect the FluentCart order log, the `iwp_sites` row and the InstaWP dashboard before retrying. A failed HTTP response or crashed worker may already have created a remote site.

An interrupted worker leaves `iwp_fc_lock_ORDERID` in WordPress options and may leave an item in `requesting` or `needs_review` in `_iwp_fluentcart_items`. Only after confirming no remote site was created should an administrator remove that lock and change that item's state to `failed` for a manual retry. Never blindly clear locks or request states.
