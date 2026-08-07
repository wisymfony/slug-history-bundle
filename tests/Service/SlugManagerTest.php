<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Wisymfony\SlugHistoryBundle\Service\SlugManager;
use Wisymfony\SlugHistoryBundle\Attribute\Slugged;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Unit tests for the SlugManager service.
 */
final class SlugManagerTest extends TestCase
{
    public function testSlugMappingIsSavedAndResolved(): void
    {
        // In-memory store used by the cache mock callbacks
        $store = [];

        // Create a simple cache mock that persists values in $store
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('delete')->willReturnCallback(function (string $key) use (&$store) {
            unset($store[$key]);
            return true;
        });
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) use (&$store) {
            if (array_key_exists($key, $store)) {
                return $store[$key];
            }
            $value = $callback();
            $store[$key] = $value;
            return $value;
        });

        // Router returns different paths depending on the slug param
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route, array $params = []) {
            if (isset($params['slug']) && $params['slug'] === 'old-slug') {
                return '/old-path';
            }
            return '/new-path';
        });

        $manager = new SlugManager($cache, $router);

        // Create a simple entity with a slug property annotated with #[Slugged]
        $entity = new class {
            #[Slugged(routeName: 'app_test', routeSlugParam: 'slug')]
            private ?string $slug = 'old-slug';

            public function getSlug(): ?string
            {
                return $this->slug;
            }

            public function setSlug(string $slug): void
            {
                $this->slug = $slug;
            }
        };

        // Simulate Doctrine change set where 'slug' changed from old-slug to new-slug
        $changeSet = [
            'slug' => ['old-slug', 'new-slug'],
        ];

        $manager->applySlugged($entity, $changeSet);
        $manager->saveSlugUpdateList();

        // Assert lookup resolves the old path to the new path
        $this->assertSame('/new-path', $manager->getNewPath('/old-path'));
    }
}
