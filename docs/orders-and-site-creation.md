# Orders & Site Creation

Paid FluentCart orders trigger matching product feeds. Pending, failed, canceled, partially paid, fully refunded and renewal orders do not create sites. A partially refunded paid order retains access to its existing site.

The plugin saves a per-item request state in FluentCart order metadata, then uses the shared InstaWP API client and `iwp_sites` tracking table. An atomic order lock and persisted item state prevent repeated hooks, overlapping feeds or concurrent setup submissions from creating duplicate sites.

Pooled sites are ready immediately. Asynchronous sites are polled through WordPress cron every minute and receive fresh credentials when complete. Ensure WP-Cron is running on your store. Refresh the thank-you page or account page to see the latest status.

Deferred items save their purchase-time snapshot and plan. Customers submit optional username and subdomain fields from the thank-you page or `[iwp_fluentcart_sites]` page. API failures with uncertain remote outcomes require administrator review; automatic retries would risk duplicate paid sites.
