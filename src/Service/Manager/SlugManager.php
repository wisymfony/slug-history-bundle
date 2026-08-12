<?php

declare(strict_types=1);

namespace Wisoft\SlugHistoryBundle\Service\Manager;

use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouterInterface;
use Wisoft\SlugHistoryBundle\Attribute\Slugged;
use Wisoft\SlugHistoryBundle\Service\Storage\SlugStorageInterface;

/**
 * Service that manages slug history mappings and generates redirect targets.
 *
 * Responsibilities:
 * - Detect changes on entity slug properties annotated with `Slugged`.
 * - Generate old and new route paths and store their mapping in the slug storage.
 * - Provide a lookup to resolve an old path to the current path for 301 redirects.
 */
final class SlugManager
{
    private array $slugUpdateList = [];

    /**
     * Cache of parsed `Slugged` attribute metadata per class to avoid repeated reflection.
     *
     * @var array<string, array<int, array{name:string,attr:Slugged}>>
     */
    private array $sluggedCache = [];

    /**
     * Constructor.
     *
     * @param SlugStorageInterface $storageInterface Storage used to store slug mappings.
     * @param RouterInterface      $routerInterface Router used to generate route paths.
     */
    public function __construct(
        #[Autowire(
            expression: 'parameter("ws_slug_history.storage") matches \'/^database$/i\' ? service("ws_slug_history.database_slug_storage") : service("ws_slug_history.cache_slug_storage")'
        )]
        private SlugStorageInterface $storageInterface,
        private RouterInterface $routerInterface
    ) {
    }

    public function getRouterInterface(): RouterInterface
    {
        return $this->routerInterface;
    }

    /**
     * Inspect the given object for `Slugged` attributes and prepare slug mappings.
     *
     * This method compares the previous and new slug values (from the provided
     * `$entityChangeSet` when available) and records any mapping from the old
     * route path to the new route path into an internal list for later persistence.
     *
     * @param object $object The entity instance to inspect (passed by reference).
     * @param array  $entityChangeSet Doctrine-style change set ([field => [old, new], ...]).
     *
     * @return void
     */
    public function applySlugged(object &$object, array $entityChangeSet = []): void
    {
        $sluggers = $this->getSlugged($object);
        foreach ($sluggers as $slugger) {
            $attr = $slugger['attr'];
            if (
                !empty($attr->from) &&
                isset($entityChangeSet[$attr->from]) &&
                !isset($entityChangeSet[$slugger['name']])
            ) {
                $fromValue = $entityChangeSet[$attr->from][1];
                $entityChangeSet = array_merge($this->updateSlugField($object, $slugger['name'], $fromValue), $entityChangeSet);
            }


            if (!empty($attr->routeName)) {
                $oldRouteParams = [];
                $newRouteParams = [];
                if (!empty($attr->routeParams)) {
                    $oldRouteParams = array_merge($oldRouteParams, $attr->routeParams);
                    $newRouteParams = array_merge($newRouteParams, $attr->routeParams);
                }

                $oldRouteParams = $this->applyMapperRouteParams($object, $oldRouteParams, $entityChangeSet);
                $newRouteParams = $this->applyMapperRouteParams($object, $newRouteParams);

                $oldPath = $this->routerInterface->generate($attr->routeName, $oldRouteParams);
                $newPath = $this->routerInterface->generate($attr->routeName, $newRouteParams);
                if ($oldPath != $newPath) {
                    $this->slugUpdateList[$oldPath] = [
                        'path' => $newPath,
                        'entityClass' => get_class($object),
                        'lastUpdatedAt' => time(),
                        'oldPath' => $oldPath,
                    ];
                }
            }
        }
    }

    /**
     * Persist the collected slug mappings into the configured storage.
     *
     * This method writes each recorded mapping to the injected
     * `SlugStorageInterface` implementation.
     *
     * @return void
     */
    public function saveSlugUpdateList(): void
    {
        if (!empty($this->slugUpdateList)) {
            foreach ($this->slugUpdateList as $oldPath => $newPathData) {
                $this->storageInterface->savePath($oldPath, $newPathData);
            }
            $this->slugUpdateList = [];
        }
    }

    /**
     * Lookup the current (new) path for a previously known `oldPath`.
     *
     * @param string $oldPath The requested old path to resolve (e.g. `/blog/old-slug`).
     *
     * @return array|null The resolved current path if found in storage, or null when not found.
     */
    public function getNewPath(string $oldPath): ?array
    {
        $visited = [];
        $currentPath = $oldPath;
        $bestMatch = null;

        while ($currentPath !== null && !isset($visited[$currentPath])) {
            $visited[$currentPath] = true;
            $entry = $this->storageInterface->findPath($currentPath);

            if (!is_array($entry) || !isset($entry['path']) || !is_string($entry['path'])) {
                break;
            }

            $entryCreatedAt = isset($entry['lastUpdatedAt']) ? (int) $entry['lastUpdatedAt'] : 0;
            if ($bestMatch === null || $entryCreatedAt > $bestMatch['lastUpdatedAt']) {
                $bestMatch = [
                    'path' => $entry['path'],
                    'lastUpdatedAt' => $entryCreatedAt,
                    'entityClass' => $entry['entityClass'] ?? null
                ];
            }

            if ($entry['path'] === $currentPath) {
                break;
            }

            $currentPath = $entry['path'];
        }

        return $bestMatch;
    }

    /**
     * Generate the current route path(s) for an entity based on its `Slugged` attributes.
     *
     * Returns an array of absolute paths (strings). Useful for callers that need
     * to know the concrete path(s) an entity will occupy (for example to remove
     * any existing redirect entries when a new row is persisted).
     *
     * @param object $object The entity instance to inspect.
     *
     * @return string[] Array of absolute paths for the entity's slugged routes.
     */
    public function getPathsForEntity(object $object): array
    {
        $paths = [];
        $sluggers = $this->getSlugged($object);
        foreach ($sluggers as $slugger) {
            if ($slugger['attr'] instanceof Slugged) {
                $attr = $slugger['attr'];
                if (empty($attr->routeName)) {
                    continue;
                }

                $routeParams = [];
                if (!empty($attr->routeParams)) {
                    $routeParams = array_merge($routeParams, $attr->routeParams);
                }

                $routeParams = $this->applyMapperRouteParams($object, $routeParams);
                $paths[] = $this->routerInterface->generate($attr->routeName, $routeParams);
            }
        }

        return $paths;
    }

    /**
     * Remove a saved slug path from storage and rewire its immediate predecessor.
     *
     * Behaviour:
     * - Reads the stored entry for `$path`. If the entry exists and contains a
     *   `'path'` value (the successor), and also contains an `'oldPath'` value,
     *   then the method will load the entry for that `oldPath` and, if it
     *   currently points to `$path`, update it to point to the successor.
     * - Finally the stored entry for `$path` itself is removed.
     *
     * Notes / limitations:
     * - This rewiring only updates a single predecessor stored in the
     *   `'oldPath'` field of the removed entry; it doesn't traverse or rebuild
     *   full predecessor lists or reverse-indexes.
     *
     * @param string $path Absolute route path to remove from slug history.
     */
    public function removePath(string $path): void
    {
        $this->storageInterface->removePath($path);
    }

    private function applyMapperRouteParams(object $object, array $routeParams, array $entityChangeSet = []): array
    {
        $result = [];
        foreach ($routeParams as $key => $value) {
            $fieldValue = $value;
            if (is_string($value) && str_starts_with($value, '@')) {
                $fieldPath = explode('.', substr($value, 1));
                $fieldValue = $this->getMapperRouteParamaValue($fieldPath, $object, $entityChangeSet);
            }
            $result[$key] = $fieldValue;
        }
        return $result;
    }

    private function getMapperRouteParamaValue(array $fieldPath, object $object, array $entityChangeSet = []): mixed
    {
        $fieldValue = null;
        foreach ($fieldPath as $index => $fieldName) {
            if ($index === 0) {
                if (isset($entityChangeSet[$fieldName])) {
                    $fieldValue = $entityChangeSet[$fieldName][0];
                } else {
                    $fieldValue = $this->getFieldValue($object, $fieldName);
                }
            } else {
                if (is_object($fieldValue)) {
                    $fieldValue = $this->getFieldValue($fieldValue, $fieldName);
                } else {
                    $fieldValue = null;
                    break;
                }
            }
        }
        return $fieldValue;
    }

    private function getFieldValue(object $object, string $fieldName): mixed
    {
        $methodGetSlug = sprintf("get%s", ucfirst($fieldName));
        if (method_exists($object, $methodGetSlug)) {
            return $object->{$methodGetSlug}();
        }
        return null;
    }

    /**
     * Inspect the object with reflection and return properties annotated with `Slugged`.
     *
     * @param object $object The entity instance to scan.
     *
     * @return array<int, array{name:string,attr:Slugged}> List of found slug configurations.
     */
    private function getSlugged(object $object): array
    {
        $class = get_class($object);
        if (isset($this->sluggedCache[$class])) {
            return $this->sluggedCache[$class];
        }

        $slugged = [];
        $reflection = new ReflectionClass($object);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $sluggedAttr = $reflectionProperty->getAttributes(Slugged::class);
            if ($sluggedAttr && !empty($sluggedAttr)) {
                $attr = $sluggedAttr[0]->newInstance();
                $slugged[] = [
                    "name" => $reflectionProperty->getName(),
                    "attr" => $attr
                ];
            }
        }

        $this->sluggedCache[$class] = $slugged;
        return $slugged;
    }

    /**
     * Update the slug field on the object by generating a slug from another value.
     *
     * @param object $object    Entity instance (by reference) to update.
     * @param string $slugField The slug property name to set.
     * @param string $fromValue Source string used to generate the slug.
     *
     * @return void
     */
    private function updateSlugField(object &$object, string $slugField, string $fromValue): array
    {
        $change = [];
        if (!empty($fromValue)) {
            $methodSetSlugValue = sprintf("set%s", ucfirst($slugField));
            if (method_exists($object, $methodSetSlugValue)) {
                $oldSlug = $this->getFieldValue($object, $slugField);
                $slug = $this->generateSlgFrom($fromValue);
                $object->{$methodSetSlugValue}($slug);
                $change[$slugField] = [$oldSlug, $slug];
            }
        }
        return $change;
    }

    /**
     * Generate a URL-friendly slug from arbitrary text.
     *
     * @param string $text    Source text to transliterate and clean.
     * @param string $divider Character used to separate words in the slug.
     *
     * @return string The generated slug (empty string for empty input).
     */
    private function generateSlgFrom(string $text, string $divider = '-'): string
    {
        if (trim($text) === '') {
            return '';
        }

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if (null !== $transliterator) {
                $text = $transliterator->transliterate($text);
            }
        } else {
            $text = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = mb_strtolower($text, 'UTF-8');
        }
        $text = (string) preg_replace('~[^\pL\d]+~u', $divider, $text);
        $text = (string) preg_replace('~[^-\w]+~', '', $text);
        $text = (string) preg_replace('~' . preg_quote($divider, '~') . '+~', $divider, $text);
        return trim($text, $divider);
    }
}
