# Repository development notes

This is the FluentCart-only InstaWP integration fork. Do not reintroduce the retired cart integration.

The plugin entry point registers FluentCart classes on init after dependencies boot. includes/integrations/fluentcart contains the native product feed editor, paid-order processor, customer portal and subscription event handlers. Orders, variants and subscriptions use FluentCart models and native metadata. includes/core retains the InstaWP API/service and site tracking layer; admin classes use WordPress administrator capabilities.

New site rows use source fluentcart and store item/variant/subscription context in source_data. Avoid numeric order-ID collisions with historical records. Use atomic order locks and persist the item request before contacting the API. An ambiguous HTTP error must never trigger automatic duplicate provisioning.

Run php tests/fluentcart.php, lint all PHP files and check JavaScript syntax. The CI workflow runs these checks on PHP 7.4 and 8.3. Do not create real orders, charge customers or provision live sites during automated checks.
