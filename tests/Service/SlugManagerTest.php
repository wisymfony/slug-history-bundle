<?php

declare(strict_types=1);

namespace Wisoft\SlugHistoryBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Wisoft\SlugHistoryBundle\Attribute\Slugged;
use Wisoft\SlugHistoryBundle\Service\Manager\SlugManager;
use Wisoft\SlugHistoryBundle\Service\Storage\SlugStorageInterface;

final class SlugManagerTest extends TestCase
{
    public function testApplySluggedStoresUpdatedRouteWhenSlugChanges(): void
    {
        $entity = new class() {
            #[Slugged(routeName: 'test_route', routeParams: ['slug' => '@slug'])]
            private ?string $slug = null;

            public function __construct()
            {
                $this->slug = 'old-slug';
            }

            public function getSlug(): ?string
            {
                return $this->slug;
            }

            public function setSlug(string $slug): self
            {
                $this->slug = $slug;

                return $this;
            }
        };

        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::exactly(2))
            ->method('generate')
            ->willReturnCallback(static function (string $routeName, array $parameters) {
                if ($routeName === 'test_route' && $parameters === ['slug' => 'old-slug']) {
                    return '/test/old-slug';
                }

                if ($routeName === 'test_route' && $parameters === ['slug' => 'new-slug']) {
                    return '/test/new-slug';
                }

                throw new \RuntimeException(sprintf('Unexpected route generation: %s %s', $routeName, json_encode($parameters)));
            });

        $storage = $this->createMock(SlugStorageInterface::class);
        $storage->expects(self::once())
            ->method('savePath')
            ->with(
                '/test/old-slug',
                self::callback(static function (array $data): bool {
                    return $data['path'] === '/test/new-slug'
                        && is_string($data['entityClass'])
                        && is_int($data['lastUpdatedAt'])
                        && $data['oldPath'] === '/test/old-slug';
                })
            );

        $manager = new SlugManager($storage, $router);
        $manager->applySlugged($entity, ['slug' => ['old-slug', 'new-slug']]);
        $manager->saveSlugUpdateList();
    }

    public function testGetNewPathResolvesRedirectChain(): void
    {
        $storage = $this->createMock(SlugStorageInterface::class);
        $storage->expects(self::exactly(3))
            ->method('findPath')
            ->willReturnCallback(static function (string $path): ?array {
                $responses = [
                    '/test/old-slug' => ['path' => '/test/middle', 'lastUpdatedAt' => 1, 'entityClass' => 'App\\Entity\\Test'],
                    '/test/middle' => ['path' => '/test/new', 'lastUpdatedAt' => 2, 'entityClass' => 'App\\Entity\\Test'],
                    '/test/new' => null,
                ];

                return $responses[$path] ?? null;
            });

        $router = $this->createMock(RouterInterface::class);
        $manager = new SlugManager($storage, $router);

        $result = $manager->getNewPath('/test/old-slug');

        self::assertSame(
            [
                'path' => '/test/new',
                'lastUpdatedAt' => 2,
                'entityClass' => 'App\\Entity\\Test',
            ],
            $result
        );
    }
}
