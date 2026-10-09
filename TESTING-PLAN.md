# FluentCart acceptance checks

Run `php tests/fluentcart.php` and the PHP/JavaScript CI checks first. In a staging WordPress store with FluentCart and an InstaWP test team:

1. Create product feeds for permanent, temporary, variant-specific and deferred sites.
2. Complete guest and registered-customer purchases using gateway test mode. Confirm the chosen snapshot/plan, email, username, subdomain, expiry, site tracking and activity logs.
3. Trigger payment notifications twice and submit deferred setup twice. Confirm one site per line item; try a pending/partially paid/failed order and confirm no site.
4. Test pooled and asynchronous provisioning. Confirm cron completion retrieves credentials. Confirm delayed credential release hides passwords and magic login links until released.
5. Confirm another account/wrong receipt hash cannot view credentials, create sites, purge cache or map domains. Confirm customer cache purge honors settings.
6. Activate, renew, pause, cancel and reactivate subscriptions. Confirm site reservations change and renewal orders do not create new sites. Confirm unrelated items remain unchanged.
7. Emit a real FluentCart plan change through its installed billing module. Confirm the existing site changes plan, no extra site is created and invalid/ambiguous mappings produce order-log errors.
8. Confirm fresh activation creates only fcip_logs and fcip_sites, and the admin/API/standalone tools work.

API timeouts and worker interruptions must not auto-retry. Inspect the remote dashboard before clearing a stuck request, following docs/troubleshooting.md.
