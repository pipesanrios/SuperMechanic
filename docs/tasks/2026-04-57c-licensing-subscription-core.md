# 57C Licensing & Subscription Core

Date: 2026-05-19

## Objective

Create a passive SaaS licensing/subscription context without billing implementation.

## Scope

- enhance `License_Context`
- add passive `Subscription_Context`
- expose normalized entitlement snapshot
- prepare stable local `instance_id`
- bridge current `License_Service` state in read-only mode

## Implemented

Updated:

- `includes/saas/class-license-context.php`

Created:

- `includes/saas/class-subscription-context.php`

`License_Context` now exposes:

- `get_license_key()`
- `get_subscription_status()`
- `get_plan_type()`
- `get_instance_id()`
- `get_entitlement_snapshot()`
- `is_active()`
- `is_trial()`
- `is_expired()`
- `to_array()`
- `from_license_service(...)`

`Subscription_Context` exposes:

- `status`
- `plan`
- `source`
- `renewal_at`
- `expires_at`
- `entitlements`

## Entitlement Snapshot

Normalized shape:

- `max_businesses`
- `max_users`
- `max_vehicles`
- `max_webhooks`
- `feature_flags`

The snapshot maps current local plan limits where available and leaves future-only limits nullable.

## Instance Identity

`License_Context::generate_instance_id()` derives a stable local identifier from WordPress site URLs.

There is no external registration, no central server call and no persisted schema change.

## Current License Bridge

`License_Context::from_license_service(...)` reads current license state passively from `License_Service`.

It does not:

- activate licenses
- deactivate licenses
- enforce subscriptions
- replace `License_Service`
- call billing APIs

## Validation

- `php scripts/php-lint.php --all` -> PASS
  - files checked: 297
  - errors: 0
- `php scripts/qa-runner.php --contract=docs/contracts/validation/57C-validation.md --output=text` -> PASS automated checks
  - PASS: 9
  - FAIL: 0
  - SKIPPED: 0
  - NOT_RUN: 4 manual checks
- static/manual scope verification -> PASS
  - no Stripe, payment provider, external billing API, billing webhooks, schema changes or enforcement takeover found
  - current license service remains unchanged

## Runtime Manual

Runtime real is not required for 57C because this is a passive context layer and does not wire UI, API, schema, billing, webhooks or external integrations.

## Deferred

- SaaS billing provider
- remote subscription authority
- subscription enforcement
- plan metering
- entitlement persistence
- central license server registration

## Status

COMPLETA
