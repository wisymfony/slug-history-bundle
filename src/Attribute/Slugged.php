<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Slugged
{
    public function __construct(
        public String|null $from = null,
        public String|null $routeName = null,
        public String|null $routeSlugParam = "slug",
        public array $routeDefaultParams = [],
    ) {
    }
}