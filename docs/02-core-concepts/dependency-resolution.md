# Dependency Resolution

Laravel Snap treats Composer packages as part of the pattern, not an afterthought. When you capture a feature that uses Stripe, Twilio, or any other third-party library, Snap records those packages in the snapshot and offers to install them when the pattern is planted in a new app.

That is what prevents the classic transplant failure: files copy over, then the first request dies with `Class not found`.

## Capture: detect what the pattern needs

During `snap:pattern`, Snap scans every captured PHP file and builds a Composer dependency list.

### 1. Read `use` statements

Each file is scanned for imported namespaces:

```php
use Stripe\StripeClient;
use Twilio\Rest\Client;
```

Application classes (`App\...` and the project's root namespace), `Illuminate\...`, and `Symfony\...` are ignored. Those are framework or first-party code, not extra Composer packages.

### 2. Match against `vendor/composer/installed.json`

Snap loads the source project's `vendor/composer/installed.json` and builds a map of PSR-4 prefixes → Composer package names.

For example:

| Namespace prefix | Package |
| --- | --- |
| `Stripe\` | `stripe/stripe-php` |
| `Twilio\` | `twilio/sdk` |

If an imported namespace starts with a known prefix, that package is recorded. Snap itself (`mkaram/laravel-snap`) and `laravel/framework` are excluded.

### 3. Persist in `manifest.json`

Detected packages are written into the snapshot manifest:

```json
{
  "pattern": "wallet",
  "version": "1.0.0",
  "is_skeleton": false,
  "dependencies": {
    "composer": [
      "stripe/stripe-php"
    ]
  },
  "created_at": "2026-08-19 01:00:00",
  "layers": {}
}
```

The blueprint is now self-describing: files plus the Composer packages they need.

## Install: require missing packages

During `snap:install`, Snap reads `manifest.json` → `dependencies.composer` and compares that list to the **target** project's `composer.json` (`require` and `require-dev`).

Any package that is not already declared is treated as missing.

If there are missing packages, the CLI prints them and asks:

```text
Would you like Snap to install missing packages via Composer now? (yes/no)
```

- **Yes** — Snap runs `composer require <packages>` in the target application (5-minute timeout) and streams Composer output to the console.
- **No** — Snap prints the exact command so you can run it later:

```bash
composer require stripe/stripe-php
```

If Composer fails, Snap tells you so you can install the packages manually. Pattern files are still installed afterward.

## What is not auto-required

Snap does **not** pull in:

- Laravel framework components (`illuminate/*` via `laravel/framework`)
- Symfony packages that ship with Laravel
- Application code in the project's own namespace
- The Snap package itself

Those already exist in a normal Laravel app.

## Practical example

You capture an OTP feature that talks to Twilio:

```bash
php artisan snap:pattern otp --all
```

`manifest.json` includes `"twilio/sdk"`.

On a fresh app:

```bash
php artisan snap:install otp
```

Snap notices `twilio/sdk` is missing, prompts you, and can run `composer require twilio/sdk` before writing models, notifications, and migrations.

The result is a transplanted feature that autoloads, not a pile of files that fail at runtime.
