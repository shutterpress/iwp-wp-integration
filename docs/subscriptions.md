# Subscriptions

For feeds with `manage_subscription=yes`, both immediately ready pooled sites and sites that finish asynchronously apply the subscription’s current reservation state on completion. Active and trialing subscriptions keep sites permanent, even when the feed initially requests temporary expiry. Feeds with `manage_subscription=no` and sites without subscriptions retain their configured expiry.

For feeds with subscription management enabled, FluentCart activation, reactivation, renewal and resume events keep associated sites permanent. Cancellation, pause, renewal payment failure, end of term and validity expiry make them temporary through the existing InstaWP reservation API.

Renewal orders never provision additional sites. Site associations use the FluentCart subscription ID and variant. Only matching sites are updated; unrelated products in the same order are left alone. Superseded subscriptions do not expire a replacement subscription's site.

If a subscription changes while site creation is running, the completion callback checks its current status. API failures are recorded in the FluentCart order activity log. This plugin mirrors status events; it does not change billing, gateway behavior or subscription schedules.
