# Upgrade & Downgrade

Configure an InstaWP hosting plan on the target FluentCart variant's product feed. When FluentCart emits `fluent_cart/plan_changed`, the plugin applies that plan to the existing site associated with the old variant and updates tracking. It does not create a replacement site.

The target variant must have exactly one unambiguous matching plan. Missing or conflicting mappings are recorded in the FluentCart order activity log. InstaWP decides which hosting-plan transitions it supports; an API rejection is logged without changing the stored plan.

Test the plan-change flow with your installed FluentCart version and subscription/payment modules before using it for live billing.
