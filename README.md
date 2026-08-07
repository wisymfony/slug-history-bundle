# Symfony Slug History Bundle

An automated SEO preservation bundle for Symfony 6.4, 7.x, and 8.x.

When a resource slug changes (for example, an article title is updated), old links can break and return 404 Not Found. This bundle tracks slug updates in Doctrine entities and intercepts 404 errors to return a 301 Moved Permanently redirect to the current URL.

---

## Features

- Zero-controller redirection: redirections happen automatically via a kernel exception listener.
- PHP 8 attributes: mark entity slug fields with #[Slugged].
- Cache-only storage: no database table or migration is required; slug mappings are stored with Symfony Cache.
- Doctrine ORM integration: listens to preUpdate/postUpdate lifecycle events to detect slug changes.
- Lightweight and modern: compatible with Symfony 6.4, 7.x, and 8.x.

---

## How It Works

1. Change tracking: When an entity property annotated with #[Slugged] changes, `DoctrineSlugListener` records the old route path and the new route path in cache.

2. Automatic redirection: When Symfony throws a `NotFoundHttpException` for an old slug URL, `ExceptionRedirectListener` checks the cache and returns a `301 Moved Permanently` redirect to the new path.

---

## Installation

### 1. Install via Composer

```bash
composer require wisymfony/slug-history-bundle
```

## Usage
Add the #[Slugged] attribute to any Doctrine entity whose slug changes should be tracked:

```php
<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\ORM\Mapping as ORM;
use WiSymfony\SlugHistoryBundle\Attribute\Slugged;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[Slugged(routeName: 'app_article_show', slugProperty: 'slug', routeParamName: 'slug' )]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $slug;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
```

## Attribute Parameters

| Parameter | Type | Default | Description |
| :--- | :---: | ---: | :--- |
| routeName | string | Required | The target Symfony route name used to generate the redirect URL. |
| slugProperty | string | 'slug' | The property name on the entity storing the slug value. |
| routeParamName | string | 'slug' | The parameter name expected by the Symfony route (e.g., {slug}). |


## Testing
Run unit and integration tests using PHPUnit:

```bash
vendor/bin/phpunit
```

Run static analysis using PHPStan:

```bash
vendor/bin/phpstan analyse src
```

## License
This bundle is released under the MIT License.