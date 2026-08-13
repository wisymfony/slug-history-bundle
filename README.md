# Slug History Bundle

This document summarizes the bundle's features and shows how to use the APIs.

## Overview

The bundle tracks slug changes on Doctrine entities and automatically redirects old URLs to the current route using `301 Moved Permanently`, preserving SEO when entities' slugs change.

### Main components

- **`DoctrineSlugListener`** — detects slug changes on entities during lifecycle events (`preUpdate`, `postUpdate`, `postPersist`).
- **`SlugManager`** — calculates old→new path mappings and persists them to configured storage.
- **`SlugStorageInterface`** — storage abstraction for slug mappings.
- **`CacheSlugStorage`** — cache-backed implementation (default, no database required).
- **`DatabaseSlugStorage`** — database-backed implementation using Doctrine (requires migration).
- **`ExceptionRedirectListener`** — intercepts 404 errors, resolves old paths, and issues `301` redirects.

---

## Flow diagram

<picture>
  <source srcset="assets/logo-dark-mode.svg" media="(prefers-color-scheme: dark)">
  <img src="assets/logo-light-mode.svg" alt="SlugHistory flow diagram" style="max-width:100%;height:auto">
</picture>

**Flow:** `DoctrineSlugListener` → `SlugManager` → `SlugStorageInterface` → `ExceptionRedirectListener` (issues `301` redirect).

---

## Installation

### Option 1: Redirects only (minimal setup)

Install the bundle with Composer:

```bash
composer require wisoft/slug-history-bundle
```

This installs the bundle with:
- ✅ Automatic 301 redirects
- ✅ Cache-based slug storage (default)
- ❌ No form widget

---

### Option 2: With SluggedType form widget (recommended)

Install the bundle and form dependencies:

```bash
composer require wisoft/slug-history-bundle symfony/form symfony/asset
```

Then install assets:

```bash
php bin/console asset:install
```

This installs:
- ✅ Automatic 301 redirects
- ✅ `SluggedType` form widget
- ✅ Form assets (CSS/JS)
- ✅ Live URL preview

---

### Option 3: With database storage

If you prefer persistent database storage over cache:

```bash
composer require wisoft/slug-history-bundle
```

Then configure the database storage (see [Using DatabaseSlugStorage](#using-databaseslug-storage)).

---

## Automatic bundle activation

The bundle supports automatic registration via Symfony Flex. If your app doesn't auto-register bundles, see [Manual activation](#manual-bundle-activation).

---

## Manual bundle activation

If auto-registration is disabled, enable the bundle in `config/bundles.php`:

```php
Wisoft\SlugHistoryBundle\SlugHistoryBundle::class => ['all' => true],
```

---

## Quick Start

### 1) Mark a slug field with `#[Slugged]`

```php
use Wisoft\SlugHistoryBundle\Attribute\Slugged;

class Product
{
    #[Slugged(
        from: 'title',
        routeName: 'app_product_show',
        routeParams: [
            'category' => '@category',
            'slug' => '@slug',
            'source' => 'legacy'
        ]
    )]
    private ?string $slug = null;
    private string $category = 'electronics';
}
```

**Key notes:**

- Values in `routeParams` starting with `@` (e.g., `@category`) are resolved from entity properties by calling their getter methods (e.g., `getCategory()`).
- Dot-notation is supported for nested objects: `@owner.company.name` resolves to `getOwner()->getCompany()->getName()`.
- `from` specifies a source field used to auto-generate/update the slug when the slug field itself is not changed directly.

**Auto-mapping of slug parameter:**

If you don't explicitly map the slug property name in `routeParams`, it will be added automatically with the value `@slug` (or the property name). For example:

```php
class Product
{
    #[Slugged(
        from: 'title',
        routeName: 'app_product_show',
        routeParams: [
            'category' => '@category',
            'source' => 'legacy'
            // 'slug' => '@slug' is added automatically
        ]
    )]
    private ?string $slug = null;
    private string $category = 'electronics';
}
```

In this case, the route parameter `slug` will be automatically mapped to `@slug` (the property name). This allows you to omit redundant mappings.

---

## `#[Slugged]` attribute options

| Option | Type | Default | Description |
|---|---:|---|---|
| `from` | `string\|null` | `null` | Source field name used to derive the slug value. If the slug property is not updated directly, the value from `from` will be used to regenerate the slug. |
| `routeName` | `string\|null` | `null` | Symfony route name used to generate the old and new path URLs. Route parameters with `@` are resolved from the entity. |
| `routeParams` | `array` | `[]` | Route parameters for URL generation. Values starting with `@` are resolved from the entity (e.g., `"category": "@category"`). Supports dot-notation for nested objects. **Note:** If the slug property name (e.g., `'slug'`) is not explicitly mapped, it will be added automatically with the value `@{propertyName}`. |

---

### 2) Use `SluggedType` in a form (optional)

> **Requires:** `symfony/form` and `symfony/asset` packages installed (see [Installation](#installation))

`SluggedType` renders a slug input field with a live preview link. The widget accepts a route configuration and source field mapping.

**Example (form builder):**

```php
use Wisoft\SlugHistoryBundle\Form\Type\SluggedType;

$builder->add('slug', SluggedType::class, [
    'route' => [
        'name' => 'app_product_show',
        'params' => [
            'category' => '@category',
            'source' => 'legacy'
            // 'slug' parameter is auto-detected from the field name
        ]
    ],
    'from' => 'title',
    'showLabel' => 'View the page',
]);
```

**Custom slugParam (when field name differs from route parameter):**

If your route parameter name differs from the form field name, you can explicitly set `slugParam`:

```php
$builder->add('productSlug', SluggedType::class, [
    'route' => [
        'name' => 'app_product_show',
        'slugParam' => 'slug',  // route expects 'slug', but field is 'productSlug'
        'params' => [
            'category' => '@category',
        ]
    ],
    'from' => 'title',
]);
```

**Setup:**

After installing `symfony/form` and `symfony/asset`, publish the form assets:

```bash
php bin/console asset:install
```

This installs the preview widget JavaScript and styling.

**Behavior:**
- `slugParam` is automatically detected from the form field name (e.g., if the field is named `'slug'`, then `slugParam` defaults to `'slug'`).
- Any `route.params` value beginning with `@` is resolved against the form's underlying entity at render time.
- `SluggedType` replaces `@field` tokens with their actual values and builds a `mappingFrom` array to track field dependencies for the client-side preview widget to update dynamically.

---

## `SluggedType` form type options

| Option | Type | Default | Description |
|---|---:|---|---|
| `route` | `array` | `['name' => '', 'params' => []]` | Route configuration for URL preview. Values starting with `@` map to entity/form properties. |
| `route.name` | `string` | `''` | Symfony route name for the preview URL. |
| `route.slugParam` | `string` | `{field name}` | The route parameter name that holds the slug. Auto-detected from the form field name (e.g., if field is `'slug'`, defaults to `'slug'`). Override only if the route parameter has a different name. |
| `route.params` | `array` | `[]` | Default parameters for URL preview. Values beginning with `@` map to entity properties and support dot-notation. |
| `from` | `array` | `[]` | Form field names used to compute preview placeholder values. |
| `showLabel` | `string` | `'Visit →'` | UI label for the preview link button. |

---

## Storage backends

The bundle uses `SlugStorageInterface` to persist slug mappings. Two implementations are provided:

| Backend | Storage | Performance | Use Case |
|---------|---------|-------------|----------|
| `CacheSlugStorage` | Symfony cache pool | Fast (in-memory) | Default; recommended for most apps |
| `DatabaseSlugStorage` | Doctrine ORM | Persistent | Multi-server setups or audit trail requirements |

### Configuration

Choose your backend using **one** of these methods:

#### Method 1: Environment variable (recommended)

Set `WS_SLUG_HISTORY_STORAGE` in your `.env` file:

```bash
# .env or .env.local
WS_SLUG_HISTORY_STORAGE=cache
```

Then alias `SlugStorageInterface` in `config/services.php`:

```php
use Wisoft\SlugHistoryBundle\Service\Storage\SlugStorageInterface;
use Wisoft\SlugHistoryBundle\Service\Storage\CacheSlugStorage;

$services->alias(SlugStorageInterface::class, CacheSlugStorage::class);
```

#### Method 2: Service parameter in `config/services.yaml`

```yaml
parameters:
    ws_slug_history.storage: 'cache'  # or 'database'
```

#### Method 3: Direct service override in `config/services.yaml`

```yaml
services:
    App\Service\MyCustomSlugStorage:
        arguments: ['@my_storage_dependency']

    Wisoft\SlugHistoryBundle\Service\Manager\SlugManager:
        arguments:
            $storageInterface: '@App\Service\MyCustomSlugStorage'
```

---

## Using DatabaseSlugStorage

To use database-backed storage, the bundle provides a `WsSlugHistory` entity. Follow these steps:

### Step 1: Verify the entity exists

The entity is included in the bundle at `src/Entity/WsSlugHistory.php`. It stores:

| Column | Type | Purpose |
|--------|------|---------|
| `id` | UUID or int | Primary key |
| `old_path` | string (2048) | The original URL path |
| `old_path_key` | string (32) | MD5 hash of `old_path` for fast lookup |
| `new_path` | string (2048) | The current URL path |
| `entity_class` | string, nullable | FQCN of the origin entity class |
| `created_at` | datetime | When the mapping was created |
| `last_updated_at` | datetime | When the mapping was last updated |

### Step 2: Create and run the migration

Generate a migration to create the table:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

### Step 3: Switch the storage alias

Update `config/services.php` to use `DatabaseSlugStorage`:

```php
use Wisoft\SlugHistoryBundle\Service\Storage\SlugStorageInterface;
use Wisoft\SlugHistoryBundle\Service\Storage\DatabaseSlugStorage;

$services->alias(SlugStorageInterface::class, DatabaseSlugStorage::class);
```

Alternatively, set the environment variable:

```bash
WS_SLUG_HISTORY_STORAGE=database
```

---

## How slug lookups and redirects work

When a user requests a URL that no longer exists (HTTP 404):

1. **`ExceptionRedirectListener`** intercepts the `NotFoundHttpException`.
2. Calls **`SlugManager::getNewPath($oldRequestPath)`** to look up the mapping.
3. `SlugManager` queries the configured **`SlugStorageInterface`** using `findPath()`.
4. If a mapping is found, returns the new path. If the new path itself has an old mapping, the lookup follows the chain until the current target is found.
5. **Issues a `301 Moved Permanently`** redirect to the current route.

---

## Route parameter mapping with dot-notation

The bundle resolves `@`-prefixed tokens in `routeParams` by calling getter methods on the entity. Nested properties use dot-notation:

**Example entity:**

```php
class Product
{
    private Company $company;
    private string $slug;

    public function getCompany(): Company { return $this->company; }
    public function getSlug(): string { return $this->slug; }
}

class Company
{
    private string $name;

    public function getName(): string { return $this->name; }
}
```

**Attribute configuration:**

```php
#[Slugged(
    routeParams: [
        'company' => '@company.name',      // resolves getCompany()->getName()
        'slug' => '@slug',                 // resolves getSlug()
    ],
    routeName: 'app_product_show',
)]
private ?string $slugField = null;
```

**Generated URL:** `/products/{company}/{slug}` → `/products/acme/my-product`

---

## Key features

- ✅ Automatic 301 redirects preserve SEO when slugs change
- ✅ Pluggable storage (cache or database)
- ✅ Support for derived slugs (`from` field)
- ✅ Dot-notation for nested entity properties
- ✅ Form widget with live URL preview (optional)
- ✅ Multi-server friendly with database backend
- ✅ Compatible with Symfony 6.4, 7.x, and 8.x

---

## Notes & best practices

- **Cache vs. Database:** Use `CacheSlugStorage` for single-server apps (faster, no DB overhead). Use `DatabaseSlugStorage` for distributed systems or when you need a persistent audit trail.
- **Slug preview in forms:** The `SluggedType` widget updates the preview URL in real-time as users edit the form. Ensure `from` field names match your entity properties. Don't forget to run `php bin/console asset:install` after installing `symfony/form` and `symfony/asset`.
- **Chained redirects:** The bundle automatically resolves chains of old redirects. If slug A → B → C, requesting A will be redirected directly to C.
- **Performance:** `old_path_key` (MD5 hash) is indexed for fast lookups on large datasets.
- **Optional dependencies:** `symfony/form` and `symfony/asset` are only required if you want to use the `SluggedType` form widget. The core redirect functionality works without them.

---

## License

MIT