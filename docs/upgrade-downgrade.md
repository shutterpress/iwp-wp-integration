# Upgrade & Downgrade

Configure an InstaWP hosting plan on the target FluentCart variant’s product feed. Completed FluentCart Pro upgrades emit `fluent_cart/order/upgraded` with the new order, original order, and original variant ID. The plugin finds the original order’s existing site, applies the target variant’s plan, and transfers site tracking to the new order and subscription. Repeated notifications do not create a replacement site or reapply the plan. Later subscription changes and chained upgrades use the transferred site.

The separate `fluent_cart/plan_changed` event remains supported for plan changes on the same order.

The target variant must have exactly one unambiguous matching plan, and the upgrade order must identify one target product item. Missing or conflicting mappings and InstaWP API rejections are recorded in the new order’s activity log. Failed upgrades preserve the existing site’s plan and order association; paid feed notifications for the upgrade order do not provision a replacement site. An administrator can correct the mapping or API problem and replay the completed-upgrade event.

Test the completed-upgrade flow with your installed FluentCart Pro and payment modules before using it for live billing.
