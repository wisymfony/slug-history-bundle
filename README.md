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

<div align="center">
    <svg viewBox="0 0 600 480" width="100%" height="auto" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <style>
            .box { fill: #1f2937; stroke: #374151; stroke-width: 2; rx: 6; }
            .text { fill: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; font-weight: 500; text-anchor: middle; dominant-baseline: middle; }
            .arrow { stroke: #6b7280; stroke-width: 2; marker-end: url(#arrowhead); }
            .found { fill: #065f46; stroke: #10b981; }
            .not-found { fill: #991b1b; stroke: #ef4444; }
            </style>
            <marker id="arrowhead" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M 0 0 L 10 5 L 0 10 z" fill="#6b7280"/>
            </marker>
        </defs>

        <!-- Request -->
        <rect x="200" y="20" width="200" height="40" class="box"/>
        <text x="300" y="40" class="text">Request (/old-slug)</text>

        <line x1="300" y1="60" x2="300" y2="85" class="arrow"/>

        <!-- 404 Exception -->
        <rect x="200" y="90" width="200" height="40" class="box"/>
        <text x="300" y="110" class="text">404 Exception</text>

        <line x1="300" y1="130" x2="300" y2="155" class="arrow"/>

        <!-- Listener -->
        <rect x="175" y="160" width="250" height="40" class="box"/>
        <text x="300" y="180" class="text">ExceptionRedirectListener</text>

        <line x1="300" y1="200" x2="300" y2="225" class="arrow"/>

        <!-- Lookup -->
        <rect x="200" y="230" width="200" height="40" class="box"/>
        <text x="300" y="250" class="text">Lookup in SlugHistory</text>

        <!-- Branches -->
        <line x1="300" y1="270" x2="160" y2="315" class="arrow"/>
        <line x1="300" y1="270" x2="440" y2="315" class="arrow"/>

        <!-- Found -->
        <rect x="100" y="320" width="120" height="40" class="box found"/>
        <text x="160" y="340" class="text">Found</text>

        <line x1="160" y1="360" x2="160" y2="395" class="arrow"/>

        <rect x="60" y="400" width="200" height="40" class="box found"/>
        <text x="160" y="420" class="text">301 Redirect (/new-slug)</text>

        <!-- Not Found -->
        <rect x="380" y="320" width="120" height="40" class="box not-found"/>
        <text x="440" y="340" class="text">Not Found</text>

        <line x1="440" y1="360" x2="440" y2="395" class="arrow"/>

        <rect x="370" y="400" width="140" height="40" class="box not-found"/>
        <text x="440" y="420" class="text">Pass 404</text>
    </svg>
</div>


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