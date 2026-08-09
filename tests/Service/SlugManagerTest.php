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
        $this->assertSame('/new-path', $manager->getNewPath('/old-path')['path']);
    }

    public function testGetNewPathResolvesRedirectChainToLatestTarget(): void
    {
        $store = [];

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

        $router = $this->createMock(RouterInterface::class);
        $manager = new SlugManager($cache, $router);

        $store['wisymfony_slug_history.'.md5('/old-path')] = [
            'path' => '/mid-path',
            'createdAt' => 100,
        ];
        $store['wisymfony_slug_history.'.md5('/mid-path')] = [
            'path' => '/new-path',
            'createdAt' => 200,
        ];

        $resolved = $manager->getNewPath('/old-path');
        $this->assertSame('/new-path', $resolved['path']);
    }

    public function testSaveSlugUpdateListOverwritesExistingMapping(): void
    {
        $store = [];

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

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route, array $params = []) {
            if (isset($params['slug'])) {
                return match ($params['slug']) {
                    'old-slug' => '/old-path',
                    'new-slug' => '/new-path',
                    default => '/newer-path',
                };
            }
            return '/new-path';
        });

        $manager = new SlugManager($cache, $router);

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

        $manager->applySlugged($entity, ['slug' => ['old-slug', 'new-slug']]);
        $manager->saveSlugUpdateList();

        $entity->setSlug('new-slug');
        $manager->applySlugged($entity, ['slug' => ['new-slug', 'newer-slug']]);
        $manager->saveSlugUpdateList();

        $this->assertSame('/newer-path', $manager->getNewPath('/new-path')['path']);
    }

    public function testGetNewPathReturnsNullWhenNoMapping(): void
    {
        $store = [];

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

        $router = $this->createMock(RouterInterface::class);
        $manager = new SlugManager($cache, $router);

        $this->assertNull($manager->getNewPath('/no-such-path'));
    }

    public function testGetPathsForEntityResolvesPropertyMappingAndLiteralRouteDefaults(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route, array $params = []) {
            if ($route === 'app_product_show' && isset($params['category'], $params['slug'], $params['source'])) {
                return sprintf('/product/%s/%s/%s', $params['category'], $params['slug'], $params['source']);
            }
            return '/unknown';
        });

        $manager = new SlugManager($cache, $router);

        $entity = new class {
            #[Slugged(routeName: 'app_product_show', routeSlugParam: 'slug', routeDefaultParams: ['category' => '@category', 'source' => 'legacy'])]
            private ?string $slug = 'my-slug';

            private string $category = 'electronics';

            public function getSlug(): ?string
            {
                return $this->slug;
            }

            public function getCategory(): string
            {
                return $this->category;
            }
        };

        $paths = $manager->getPathsForEntity($entity);

        $this->assertSame(['/product/electronics/my-slug/legacy'], $paths);
    }
}
