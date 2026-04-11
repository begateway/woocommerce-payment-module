# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WooCommerce payment gateway plugin for BeGateway payment processor. Supports card payments (Visa/Mastercard), Halva installment cards, and ERIP (Belarusian electronic invoicing). Optionally supports WooCommerce Subscriptions for recurring payments via card tokenization.

**Current version:** 3.1.4 (defined in both `wc-begateway-payment/wc-begateway.php` header and `WC_BeGateway::$version`)

## Build & Package

```bash
# Build the distributable zip (the only Make target)
make

# This creates wc-begateway-payment.zip excluding tests, examples, git files
```

There are no PHP tests, linters, or CI pipelines in this repository.

## Local Development Environment

```bash
# Start WordPress + WooCommerce + MariaDB via Docker
docker-compose up

# WordPress is at http://localhost:80
# Plugin is volume-mounted from ./wc-begateway-payment into the container
# WP credentials: see docker-compose.yml (DB: root/root)
```

The Dockerfile installs WP-CLI and Node.js 16 inside the container. WP-CLI is available as `wp` for admin tasks.

## Translation Workflow

Translations use WordPress i18n. Text domain: `wc-begateway-payment`. Languages: Russian (`ru_RU`), Belarusian (`be_BY`).

```bash
# Inside the container, from /var/www/html/wp-content/plugins/wc-begateway-payment/
wp i18n make-pot . languages/wc-begateway-payment.pot
msgmerge --update languages/wc-begateway-payment-ru_RU.po languages/wc-begateway-payment.pot
msgmerge --update languages/wc-begateway-payment-be_BY.po languages/wc-begateway-payment.pot
wp i18n make-mo languages/
```

## Architecture

### Plugin Entry & Gateway Selection (`wc-begateway-payment/wc-begateway.php`)

`WC_BeGateway` (singleton) is the plugin bootstrap. It registers the payment gateway, admin AJAX actions (capture, cancel, refund, partial capture/refund), admin metabox for order transaction management, and WooCommerce Blocks support.

Gateway class selection happens at runtime in `get_main_gateway()`:
- If WooCommerce Subscriptions is active -> `WC_Gateway_BeGateway_Subscriptions`
- Otherwise -> `WC_Gateway_BeGateway`

### Payment Gateway (`includes/class-wc-gateway-begateway.php`)

Core gateway extending `WC_Payment_Gateway`. Key flow:
1. `process_payment()` redirects to receipt page
2. `generate_begateway_form()` obtains a payment token from BeGateway API, renders a checkout widget/redirect
3. `validate_ipn_request()` handles webhook callbacks from BeGateway (registered at `WC()->api_request_url('WC_Gateway_BeGateway')`)
4. Admin operations: `capture_payment()`, `cancel_payment()`, `refund_payment()` via `child_transaction()`

The `_init()` method configures the BeGateway PHP SDK with shop credentials from settings. Transaction types: `payment` (immediate capture) or `authorization` (manual capture later).

Payment methods that don't support refund/capture/cancel are listed in class constants (`NO_REFUND`, `NO_CAPTURE`, `NO_CANCEL`) — currently only ERIP.

### Subscriptions (`includes/class-wc-gateway-begateway-subscriptions.php`)

Extends the base gateway with WooCommerce Subscriptions support. Handles recurring payments via saved card tokens (`_begateway_card_id` order meta). Overrides `save_transaction_id()`, `save_card_id()`, and `save_locale()` to propagate data to subscription objects. Sets `contract` data to `['recurring', 'card_on_file']` for subscription-aware checkout tokens.

### WooCommerce Blocks (`includes/blocks/class-wc-gateway-begateway-blocks-support.php`)

Registers the gateway for the block-based checkout. Frontend JS at `assets/js/frontend/blocks.js`.

### Settings (`includes/settings-begateway.php`)

Gateway configuration fields returned as a filtered array (`wc_begateway_settings`). Key settings: `shop-id`, `secret-key`, `public-key`, `domain-gateway`, `domain-checkout`, `transaction_type`, `payment_methods`, `mode` (test/live).

### Order Meta Keys

- `_begateway_transaction_id` — BeGateway transaction UID
- `_begateway_transaction_captured` — `yes`/`no`
- `_begateway_transaction_captured_amount` — captured amount
- `_begateway_transaction_refunded_amount` — refunded amount
- `_begateway_transaction_refunded` — `yes` when fully refunded
- `_begateway_transaction_voided` — `yes` when cancelled
- `_begateway_transaction_payment_method` — e.g. `credit_card`, `erip`
- `_begateway_card_id`, `_begateway_card_last_4`, `_begateway_card_brand` — tokenized card data
- `_begateway_transaction_language` — locale for subscription charges

### Vendor Dependencies

`begateway/begateway-api-php` SDK is committed in `vendor/` (no composer install needed). It provides `GetPaymentToken`, `Webhook`, `CaptureOperation`, `VoidOperation`, `RefundOperation`, `PaymentOperation`, `AuthorizationOperation`, etc.

## HPOS Compatibility

The plugin declares High-Performance Order Storage (HPOS) compatibility. `WC_Gateway_BeGateway_Utils::get_edit_order_screen_id()` handles both legacy post-based and HPOS-based order screens.

## Test Credentials

For testing with BeGateway sandbox (Shop ID: 361), see the test card numbers in README.md.
