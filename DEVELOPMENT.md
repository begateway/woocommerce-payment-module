# Development

## Local environment

```bash
docker-compose up -d
```

WordPress is available at http://localhost:80

## Making the site accessible for webhooks

Use localtunnel to expose the local site to the internet so that BeGateway can send webhook notifications:

```bash
lt --port 80 --subdomain woobegateway
```

Then update WordPress URLs to match the tunnel:

```bash
docker exec woocommerce-payment-module-woocommerce-1 wp option update home 'https://woobegateway.loca.lt'
docker exec woocommerce-payment-module-woocommerce-1 wp option update siteurl 'https://woobegateway.loca.lt'
```

To revert back to localhost:

```bash
docker exec woocommerce-payment-module-woocommerce-1 wp option update home 'http://localhost'
docker exec woocommerce-payment-module-woocommerce-1 wp option update siteurl 'http://localhost'
```

## Translations

### Loco Translate

Loco Translate is a WordPress plugin that allows translating plugins and themes directly in the browser with an integrated PO file editor.

### WP-CLI

WP-CLI is the standard command-line interface for WordPress. It can scan for WordPress-specific translation functions like `__()`, `_e()`, and `_x()`.

#### Step A: Create or update the POT file (the template)

The POT file is the master template containing all English strings found in the source code.

```bash
# Navigate to the plugin folder inside the container
cd /var/www/html/wp-content/plugins/wc-begateway-payment/

# Scan code and generate a new POT file
wp i18n make-pot . languages/wc-begateway-payment.pot
```

#### Step B: Update PO files (merge new strings)

Once you have a fresh POT file, merge it into the existing language files without losing previous translations.

```bash
# Update existing PO files using the new POT template
msgmerge --update languages/wc-begateway-payment-ru_RU.po languages/wc-begateway-payment.pot
msgmerge --update languages/wc-begateway-payment-be_BY.po languages/wc-begateway-payment.pot
```

Note: `msgmerge` is part of the `gettext` package, usually installed by default on Linux.

#### Step C: Compile to MO (Machine Object)

WordPress reads `.mo` files, not `.po` files. Compile the changes:

```bash
wp i18n make-mo languages/
```
