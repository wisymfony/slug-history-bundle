# Slug History Bundle

This document summarizes the recent changes in the bundle and shows how to use the new/updated APIs.

## Overview

The bundle tracks slug changes on Doctrine entities and redirects old URLs to the current route using `301 Moved Permanently`.

Main components:
- `DoctrineSlugListener` — detects slug changes on entities.
- `SlugManager` — builds old→new mappings and persists them to the configured storage.
- `SlugStorageInterface` — storage abstraction for slug mappings.
- `CacheSlugStorage` — cache-backed implementation (default).
- `DatabaseSlugStorage` — database-backed implementation (now implemented; requires migration/entity).
- `ExceptionRedirectListener` — resolves old paths and issues `301` redirects.

---

## Diagram

<picture>
  <source srcset="assets/logo-dark-mode.svg" media="(prefers-color-scheme: dark)">
  <img src="assets/logo-light-mode.svg" alt="SlugHistory flow diagram" style="max-width:100%;height:auto">
</picture>

Caption: `DoctrineSlugListener` → `SlugManager` → `SlugStorageInterface` → `ExceptionRedirectListener` (301 redirect).

---

## Installation

Install with Composer:

```bash
composer require wisymfony/slug-history-bundle
```

The bundle supports automatic registration (Symfony Flex). If your app doesn't auto-register bundles, see Manual activation below.

---

## Quick Start

### 1) Mark a slug field with `#[Slugged]`

```php
use Wisymfony\SlugHistoryBundle\Attribute\Slugged;

class Product
{
    private ?string $slug = null;
    private string $category = 'electronics';

    #[Slugged(
        from: 'title',
        routeName: 'app_product_show',
        routeParams: [
            'category' => '@category',
            'slug' => '@slug',
            'source' => 'legacy'
        ]
    )]
    private ?string $slugField = null;
}
```

Notes:
- Values in `routeParams` starting with `@` (for example `@category`) map to the entity object properties: the bundle resolves `@category` by calling `getCategory()` on the entity (dot-notation supported for nested objects, e.g. `@owner.email`).
- `from` can be used to auto-generate/update the slug from another field when the slug field itself is not changed directly.


---

## `#[Slugged]` options (role-prefixed)

| Option | Type | Default | Description |
|---|---:|---|---|
| `from` | `string|null` | `null` | Role: source field used to derive the slug value. If the slug property is not updated directly, the value from `from` will be used to regenerate the slug. |
| `routeName` | `string|null` | `null` | Role: route selector. Symfony route name used to generate the old/new path URLs. Route parameters with `@` are resolved from the entity/form. |
| `routeParams` | `array` | `[]` | Role: default route parameters. Additional route parameters for URL generation. Values starting with `@` are resolved from the entity/form (e.g. `"category": "@category"`). |

---

### 2) Use `SluggedType` in a form

`SluggedType` renders a slug input with a preview link. The widget accepts a `route` array and `from` field.

Example (form builder):

```php
$builder->add('slug', SluggedType::class, [
    'route' => [
        'name' => 'app_product_show',
        'slugParam' => 'slug',
        'params' => [
            'slug' => '@slug',
            'category' => '@category',
            'source' => 'legacy'
        ]
    ],
    'from' => 'title',
    'showLabel' => 'Voir la page',
]);
```

Important behavior:
- Any `route.*` value that begins with `@` is resolved against the form's underlying object (entity) at render time.
- `SluggedType` transforms `@field` tokens into placeholders in the preview and provides a `mappingFrom` structure to the client-side widget so it can display the current values.

---

## `SluggedType` options (role-prefixed)

| Option | Type | Default | Description |
|---|---:|---|---|
| `route` | `array` | `['name'=>'','slugParam'=>'','params'=>[]]` | Role: route preview configuration. Used to build the preview URL and placeholders. Values starting with `@` map to entity/form properties. |
| `route.name` | `string` | `''` | Role: preview route name. |
| `route.slugParam` | `string` | `''` | Role: slug parameter in route. |
| `route.params` | `array` | `[]` | Role: default preview parameters. Values beginning with `@` map to form properties. |
| `from` | `array` | `[]` | Role: preview source fields. List of form fields used to compute placeholder values. |
| `showLabel` | `string` | `'Visit →'` | Role: UI label for the preview link. |

---

## Storage backends

The bundle uses `SlugStorageInterface` for storing slug mappings. Implementations provided:

- `CacheSlugStorage` — stores mappings in a Symfony cache pool (default and recommended). No DB migration required.
- `DatabaseSlugStorage` — database-backed implementation persisted via Doctrine. The implementation now includes basic CRUD using a `WiSymfonySlugHistory` entity and `WiSymfonySlugHistoryRepository`. You must add the entity/migration to your project to use DB storage (see below).

Switch backend options (three methods):

1) Environment variable + service alias (recommended): set `WISYFONY_SLUG_HISTORY_STORAGE=cache|database` in `.env` and alias `SlugStorageInterface` accordingly in `config/services.php`.

2) Parameter in `config/services.yaml`: set `wisymfony_slug_history.storage: 'cache'` and alias `SlugStorageInterface` to chosen implementation.

3) Overriding Service Configuration

You can directly override service arguments within your main application's configuration file (`config/services.yaml`).

```yaml
services:
    App\Service\MyCustomSlugStorage: # <- must implements Wisymfony\SlugHistoryBundle\Service\Storage\SlugStorageInterface
        arguments: ['@my_storage_dependency']

    Wisymfony\SlugHistoryBundle\Service\Manager\SlugManager:
        arguments:
            $storageInterface : App\Service\MyCustomSlugStorage
```

---

### Database storage setup

If you choose `database` storage, the bundle references a `WiSymfonySlugHistory` entity. Ensure you add the entity and create a migration with Doctrine. A minimal table should store:

- `id` (PK)
- `old_path` (string)
- `old_path_key` (string, md5 of old_path) — used for fast lookup
- `new_path` (string)
- `entity_class` (string, nullable)
- `last_updated_at` (datetime)

Generate and run a migration for the added entity before switching the storage alias to `DatabaseSlugStorage`.

---

## Manual bundle activation

If auto-registration is disabled, enable the bundle in `config/bundles.php`:

```php
Wisymfony\SlugHistoryBundle\SlugHistoryBundle::class => ['all' => true],
```

---

## How lookups work

- When a `NotFoundHttpException` occurs, `ExceptionRedirectListener` calls `SlugManager::getNewPath($requestedPath)`.
- `SlugManager` uses the configured `SlugStorageInterface` to `findPath()` and may follow chained redirects until the current target is found.

---

## Example: route param mapping

When you configure `routeParams` like:

```php
'routeParams' => [
    'slug' => '@slug',
    'category' => '@category',
]
```

The bundle resolves `@slug` by calling `getSlug()` and `@category` by calling `getCategory()` on the entity. If the token includes a dot (`@owner.email`) the bundle will resolve nested getters (`getOwner()->getEmail()`). If a property is present in the Doctrine change set during `preUpdate`, the change set value is preferred for generating the _old_ value.

---

## Notes & Next steps

- I verified the files you listed. `SlugManager` now depends on `SlugStorageInterface` and resolves `@`-prefixed route params using `getFieldValue` and supports nested dot-notation. `SluggedType` prepares a `mappingFrom` array for the client widget and replaces `@field` tokens with placeholders. `DatabaseSlugStorage` now contains persistence logic and expects a `WiSymfonySlugHistory` entity/repository — you must add the entity migration to use it.

- Would you like me to generate a Doctrine entity (`WiSymfonySlugHistory`) and a sample migration file to go with `DatabaseSlugStorage`? I can scaffold the entity and a migration (SQL) in the bundle or provide a ready-to-copy entity for your app.

---

## License

MIT
