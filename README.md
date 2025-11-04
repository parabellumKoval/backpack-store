# Backpack-store

[![Build Status](https://travis-ci.org/parabellumKoval/backpack-store.svg?branch=master)](https://travis-ci.org/parabellumKoval/backpack-store)
[![Coverage Status](https://coveralls.io/repos/github/parabellumKoval/backpack-store/badge.svg?branch=master)](https://coveralls.io/github/parabellumKoval/backpack-store?branch=master)

[![Packagist](https://img.shields.io/packagist/v/parabellumKoval/backpack-store.svg)](https://packagist.org/packages/parabellumKoval/backpack-store)
[![Packagist](https://poser.pugx.org/parabellumKoval/backpack-store/d/total.svg)](https://packagist.org/packages/parabellumKoval/backpack-store)
[![Packagist](https://img.shields.io/packagist/l/parabellumKoval/backpack-store.svg)](https://packagist.org/packages/parabellumKoval/backpack-store)

This package provides a quick starter kit for implementing a store for Laravel Backpack. Provides a database, CRUD interface, API routes and more.

## Installation

Install via composer
```bash
composer require parabellumKoval/backpack-store
```

Migrate
```bash
php artisan migrate
```

### Publish

Configuration File
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="config"
```

Views File
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="views"
```

Languages File
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="langs"
```

Migrations File
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="migrations"
```

Routes File
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="routes"
```

Public Files
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="public"
```

Traits File
```bash
php artisan vendor:publish --provider="Backpack\Store\ServiceProvider" --tag="traits"
```

## Usage

### Seeders
```bash
php artisan db:seed --class="Backpack\Store\database\seeders\CategorySeeder"
```

```bash
php artisan db:seed --class="Backpack\Store\database\seeders\ProductSeeder"
```

```bash
php artisan db:seed --class="Backpack\Store\database\seeders\AttributeSeeder"
```

```bash
php artisan db:seed --class="Backpack\Store\database\seeders\OrderSeeder"
```

```bash
php artisan db:seed --class="Backpack\Store\database\seeders\PromocodeSeeder"
```

## Security

If you discover any security related issues, please email 
instead of using the issue tracker.

## Credits

- [](https://github.com/parabellumKoval/backpack-store)
- [All contributors](https://github.com/parabellumKoval/backpack-store/graphs/contributors)


## Invoice subsystem

The package ships with a PDF invoice subsystem that renders invoices "1:1" with the supplied Czech layout, generates SPD QR codes in advance, and exposes API/admin tooling.

### API

All routes are prefixed with `/api/store/invoices` and respect the `dress.store.auth_guard` guard.

| Verb | URI | Description |
| --- | --- | --- |
| `GET` | `/api/store/invoices/{order}` | Inline PDF preview (requires auth) |
| `GET` | `/api/store/invoices/{order}/download` | Download or create PDF (requires auth) |
| `GET` | `/api/store/invoices/{order}/qr` | Return QR image (`format=svg|png`) |
| `GET` | `/api/store/invoices/{order}/signed/{invoice}` | Signed download URL, protected by signature only |

Query parameters:

- `template` (optional) – template key, defaults to `cz.default`
- `locale` (optional) – formatting locale
- `format` (optional, QR route) – `svg` or `png`
- `regenerate=1` – force regeneration even if cached

### Admin UI

Order CRUD now contains three quick actions (preview, download, QR) both in the list and show views. They point to the same API routes and honour permissions of the authenticated user or admin.

### Settings & configuration

The full configuration lives in `config/dress/invoice.php` (publishable via the `config` tag). Each option is exposed in Backpack Settings under the new "Счета и PDF" group, including:

- Default template, locale and numbering rules
- Seller details and assets (logo/stamp/signature)
- Bank accounts per country (used for SPD payloads)
- Storage disks/path masks for PDFs and QR cache
- Signed URL TTL and QR message pattern

Programmatic usage is available through dependency injection of `Backpack\Store\app\Services\Invoice\InvoiceService`. Calling `generate($order)` returns a payload with the rendered binary, the stored `OrderInvoice` record, QR data, and helper methods for signed URLs or downloads.
