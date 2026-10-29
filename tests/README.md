# Refund verification

Run only on a disposable local WordPress installation with WooCommerce and the
BeGateway plugin activated. No new Composer/npm dependencies are required.

```sh
# Run from the repository root; replace /path/to/wordpress with the local site.
BEGATEWAY_RUN_REFUND_TESTS=1 wp --path=/path/to/wordpress eval-file "$PWD/tests/integration/refunds.php"
BEGATEWAY_RUN_REFUND_TESTS=1 wp --path=/path/to/wordpress eval-file "$PWD/tests/integration/child-transactions.php"
```

`refunds.php` uses actual WooCommerce orders, refund orchestration and storage,
but substitutes the gateway response. It covers full/partial refunds, reasons,
API rejection/rollback, invalid orders/methods/amounts, missing transaction UID,
uncaptured/voided transactions, partial captures, repeated refunds and mixed
standard/custom-panel refunds. Orders are removed afterward; mail is suppressed.

`child-transactions.php` runs the actual SDK/cURL transport against a temporary
loopback HTTP fixture. It verifies JPY and KWD minor-unit conversion when the
store uses USD, plus parent UID and reason. It requires PHP `proc_open` and
loopback sockets; the server and orders are removed afterward.

## Verified 2026-10-29

Environment: WordPress 6.9.4, WooCommerce 10.6.2, PHP 8.4.22-dev,
MariaDB 10.11.19, Playwright 1.64.1 / Chromium. Plugin installed locally;
menaco.by and real-money transactions were not accessed.

- Before the fix: the original 9 regression cases failed. The standard
  `wc_create_refund(..., refund_payment=true)` reproduced the reported generic
  gateway API error without calling BeGateway.
- After the fix: all 12 refund cases passed with both legacy order storage and
  HPOS. Both SDK transport cases passed. PHP syntax checks passed for plugin
  first-party PHP and all test scripts.
- Real sandbox: public demo shop **361**, `test=true`, gateway
  `demo-gateway.begateway.com`, hosted checkout `checkout.begateway.com`.
  The module generated the checkout token and payment redirect. Chromium paid
  **10.00 USD** using the successful test card documented in the project README.
- The normal authenticated HTTP webhook handler marked local order **38** paid
  and stored the original payment UID. **Webhook delivery was relayed locally
  using the actual transaction response queried from the sandbox**, because the
  local server was not publicly reachable. Public gateway-to-site delivery was
  not tested.
- The actual standard WooCommerce order UI issued **3.25 USD** then **6.75 USD**
  API refunds. Final response: `success=true`, `status=fully_refunded`;
  order status `refunded`, two WC refund records, both WC and plugin refunded
  totals **10.00 USD**. Separate gateway queries confirmed successful test
  refunds.

Sandbox identifiers (test transactions only):

| Operation | UID |
| --- | --- |
| Payment, 10.00 USD | `12c5dbc4-471c-4260-96f1-8a3dca67daf7` |
| Refund, 3.25 USD | `6469e3a7-dbdc-4f19-b115-c53253e03b79` |
| Refund, 6.75 USD | `6f88eca9-5088-412a-b377-80e4238e4eeb` |

## Installable artifact

The root `wc-begateway-payment.zip` was rebuilt from the current plugin source
(the previous committed ZIP still contained version 2.0.11). The new archive
contains the patched 3.1.5 source, no obsolete SDK tree or test fixtures. All 80
archive files were compared byte-for-byte with source. This ZIP was installed
and activated on the local test site, then all 14 checks were rerun successfully
against that installed copy (HPOS enabled).

## Scope and limitations

The gateway advertised refunds but inherited WooCommerce's default
`process_refund()` returning `false`. The fix delegates this standard entry point
to the existing refund operation, forwards the reason and error detail, validates
remaining captured funds, and uses the order currency for SDK conversion. The
Transactions panel now checks refund support rather than capture support.

WooCommerce owns its refund records; the gateway does not create a second one.
Existing custom-panel refunds still update plugin metadata only, not WC refund
records/reporting. The shared balance validation prevents over-refunding when
mixing those paths; it does not reconcile historical records. Prefer the standard
WooCommerce refund UI for synchronized reporting.

No production deployment, real card transaction, public webhook delivery,
subscription renewal, or real ERIP refund was tested. ERIP API refunds remain
unsupported. No PHP static analyzer is configured in this repository.

Contract references:
[WooCommerce base gateway](https://github.com/woocommerce/woocommerce/blob/10.6.2/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php#L434-L445),
[WooCommerce refund orchestration](https://github.com/woocommerce/woocommerce/blob/10.6.2/plugins/woocommerce/includes/wc-order-functions.php#L700-L729),
[BeGateway refunds](https://doc.begateway.com/en/integration/card_api/transactions/refund/).
