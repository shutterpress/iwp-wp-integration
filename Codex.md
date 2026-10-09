# Repository development notes

FluentCart InstaWP Provisioning is a standalone plugin with entry point fluentcart-instawp-provisioning.php, FCIP_ classes, fcip_ options/hooks/tables, and the fluentcart-instawp-provisioning text domain. Do not add installation migrations or compatibility aliases.

The integrations/fluentcart classes implement product feeds, paid-order provisioning, the customer portal and subscription handlers. Use native FluentCart models and metadata. Core classes manage the InstaWP API and site tracking. Admin actions require WordPress administrator capabilities.

Persist request state before contacting InstaWP and use atomic order locks. Ambiguous API failures must not cause duplicate provisioning. Track every site in fcip_sites.

Run all three tests in tests/, PHP lint, and JavaScript syntax checks. CI targets PHP 7.4 and 8.3. Automated checks must not charge customers or provision live sites.
