<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Attribute used to mark an entity property as a slug that should be tracked.
 *
 * @property string|null $from             Optional source field used to generate the slug automatically.
 * @property string|null $routeName        Target Symfony route name used to generate redirect URLs.
 * @property string|null $routeSlugParam   Name of the route parameter that holds the slug (default: 'slug').
 * @property array       $routeDefaultParams Optional additional default route parameters.
 */
final class Slugged
{
    public function __construct(
        public string|null $from = null,
        public string|null $routeName = null,
        public string|null $routeSlugParam = "slug",
        public array $routeDefaultParams = [],
    ) {
    }
}