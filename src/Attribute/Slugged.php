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
 * @property array       $routeParams       Optional additional default route parameters.
 */
final class Slugged
{
    public function __construct(
        public string|null $from = null,
        public string|null $routeName = null,
        public array $routeParams = [],
    ) {
    }
}
