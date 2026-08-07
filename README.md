# Sylius Topi Payment Plugin

Integrates [Topi](https://www.topi.eu/) (B2B pay-later / financing) as a Payum-based
payment gateway for Sylius 2.0 — checkout capture/webhook handling, catalog and
shipping-method sync commands, and [Topi Elements](https://developer.topi.eu/docs/topi-elements/using-topi-elements)
rental-price badges on the PDP, product listings, cart, and payment-selection page.

Ported from a Sylius 1.x implementation built for a larger shop application; see
the class-level docblocks on `CatalogSyncService`/`TopiProductExtension`/`WebhookController`
for the app-specific pieces (custom price lists, multi-tenant channel gating, a dedicated
`Payment` entity extension) that had no equivalent in stock Sylius and were simplified
during the port — `EilingIo\SyliusTopiPlugin\Service\VariantPriceResolver` is the
plugin-native replacement for price/tax resolution.

## Requirements

* PHP 8.2 or higher
* Sylius 2.0 or higher
* A Topi seller account — for local/test use, sandbox credentials (client ID/secret)
  and a sandbox Topi Elements widget ID, both issued by Topi

## Installation

Steps to add the plugin to an existing Sylius 2.0 project.

1. Require the package:
   ```bash
   composer require eiling-io/sylius-topi-plugin
   ```

1. Enable the plugin in `config/bundles.php`:
   ```php
   <?php

   return [
       // ...
       EilingIo\SyliusTopiPlugin\EilingIoSyliusTopiPlugin::class => ['all' => true],
   ];
   ```

1. Import the plugin's routes, e.g. in `config/routes/eiling_io_sylius_topi.yaml`:
   ```yaml
   eiling_io_sylius_topi:
     resource: "@EilingIoSyliusTopiPlugin/config/routes.yaml"
   ```

1. Add the required env variables to your `.env` (or `.env.local`) file:
   ```dotenv
   # OAuth2 client credentials for the Topi seller API (identity.topi[-sandbox].eu).
   TOPI_CLIENT_ID=""
   TOPI_CLIENT_SECRET=""
   # 0 = sandbox (seller-api-sandbox.topi-sandbox.eu), 1 = production (seller-api.topi.eu).
   TOPI_ENABLE_LIVE=0

   # Comma-separated Svix signing secrets from the Topi merchant portal's webhook
   # settings — WebhookVerificationService tries each until one verifies.
   TOPI_WEBHOOK_SIGNING_SECRETS=""
   # Keep this off only while wiring things up locally; turn it on once real
   # webhooks need to be trusted.
   TOPI_ENABLE_WEBHOOK_SIGNATURE_CHECKS=0

   # Feature flag for both TopiProductExtension (is_topi_product()/topi_pdp_item())
   # and the Topi Elements badges.
   TOPI_ENABLE=1
   # Widget ID for the Topi Elements script (elements.topi[-sandbox].eu) — issued
   # separately from the client ID/secret above, ask Topi for one per environment.
   TOPI_WIDGET_ID=""
   ```

1. In the Sylius admin (**Configuration → Payment methods → Create**), add a payment
   method and pick **Topi Payment** as the gateway. Enable it for the channel(s) that
   should offer Topi at checkout.

1. Register the webhook URL (`https://your-shop.example/topi-payment/webhook`) in the
   Topi merchant portal so offer/order lifecycle events reach `WebhookController`.

1. Sync your catalog and shipping methods to Topi so the rental-price badges and
   checkout offers have something to price against:
   ```bash
   bin/console topi:catalog:sync
   bin/console topi:shipping-methods:sync
   ```

At this point, checkout should offer "Topi Payment" as a method, and — once
`TOPI_ENABLE`/`TOPI_WIDGET_ID` are set — the PDP, product listings, cart, and
payment-selection page should show Topi's rental-price badges.

> The `<x-topi-checkout-button>` ("Buy now") integration is built (see
> `BuyNowOfferService`/`BuyNowOrderCreator`/`checkout_button_*.html.twig`) but not
> wired into any template by default — Topi's `POST /offers` currently rejects an
> offer with no `shipping_address` for `sales_channel=ecommerce`, which the button's
> "address collected on Topi's hosted checkout" flow relies on. Hook
> `checkout_button_pdp.html.twig`/`checkout_button_cart.html.twig` back into
> `config/config.yaml` once that's resolved with Topi.

## ddev setup (plugin development)

For working on the plugin itself against the bundled Sylius test application
(`vendor/sylius/test-application`), rather than installing it into another project.

1. Install [ddev](https://ddev.com/) and make sure Docker is running.

1. Install PHP dependencies (ddev's PHP version is pinned in `.ddev/config.yaml`, so
   run this through ddev rather than the host's own PHP/Composer):
   ```bash
   ddev start
   ddev composer install
   ```

1. Add your Topi sandbox credentials to `tests/TestApplication/.env` (or a sibling
   `.env.local` — see the env variables listed under [Installation](#installation)
   above; this is the file `vendor/sylius/test-application`'s kernel loads on top of
   its own defaults, see `config/bootstrap.php` in that package for how).

1. Run the project's `init` command — creates the database, runs migrations, loads
   Sylius' demo fixtures, and builds the frontend assets:
   ```bash
   ddev init
   ```
   This is equivalent to running, inside the web container:
   `doctrine:database:create` → `doctrine:migrations:migrate -n` →
   `sylius:fixtures:load -n` → `yarn install && yarn build` (in
   `vendor/sylius/test-application`) → `assets:install`.

1. Open the shop at `https://syliustopiplugin.ddev.site/` and the admin at
   `https://syliustopiplugin.ddev.site/admin/` (default fixture login: `sylius` /
   `sylius`).

Useful follow-ups:

* `ddev exec php vendor/bin/console <command>` — run any console command inside the
  web container (matches the PHP version Sylius actually runs under).
* `ddev mysql <db-name> -e "<query>"` — inspect the database directly; the app
  database is named `sylius_topi_plugin_dev` (see `DATABASE_URL` in
  `tests/TestApplication/.env`).
* `ddev exec php vendor/bin/console cache:clear --env=dev` — after changing PHP,
  Twig hooks, or `config/services.xml`/`config/config.yaml`.
* `ddev logs -s web --tail=50` — tail the web container's logs; app-level errors
  (Guzzle/Topi API responses, webhook failures, ...) also land in
  `var/log/dev.log`.
* `ddev stop` — stop the containers without removing them; `ddev delete` to remove
  the project (including its database) entirely.
