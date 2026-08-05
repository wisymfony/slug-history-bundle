# Symfony Slug History Bundle

An automated SEO preservation bundle for Symfony 6.4, 7.x, and 8.x.

When a resource slug changes (e.g., an article title is updated), old links across the web and search engine indexes break into 404 Not Found pages. This bundle automatically tracks slug changes in Doctrine entities and intercepts 404 errors to issue seamless 301 Moved Permanently HTTP redirects to the new URLs.

---

## Features

- Zero-Controller Logic: Redirections happen automatically via HttpKernel exception listeners.
- PHP 8 Attributes: Configure slug tracking directly on your entities using #[Slugged].
- SEO Preservation: Returns proper 301 HTTP status codes to preserve search engine ranking.
- Doctrine ORM Integration: Listens to postUpdate events to capture slug changes seamlessly.
- Lightweight and Modern: Built natively for Symfony 6.4, 7.x, and 8.x using AbstractBundle.

---

## How It Works

Request (/old-slug) -> 404 Exception -> ExceptionRedirectListener
                                                |
                                       Lookup in SlugHistory
                                                |
                                 +--------------+--------------+
                            [Found]                       [Not Found]
                                |                              |
                   301 Redirect to /new-slug              Pass 404

1. Change Tracking: When an entity marked with #[Slugged] updates its slug, DoctrineSlugListener logs the old slug, new slug, and route parameters into the slug_history database table.

2. Automatic Redirection: When a user or crawler accesses an old URL, Symfony throws a NotFoundHttpException (404). The ExceptionRedirectListener intercepts it, looks up the old slug, and immediately returns a 301 Moved Permanently response pointing to the updated route.

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

Parameter | Type | Default | Description
routeName | string | Required | The target Symfony route name used to generate the redirect URL.
slugProperty | string | 'slug' | The property name on the entity storing the slug value.
routeParamName | string | 'slug' | "The parameter name expected by the Symfony route (e.g.,{slug})."

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