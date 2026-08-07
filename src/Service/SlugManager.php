<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Service;

use ReflectionClass;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Wisymfony\SlugHistoryBundle\Attribute\Slugged;

/**
 * Service that manages slug history mappings and generates redirect targets.
 *
 * Responsibilities:
 * - Detect changes on entity slug properties annotated with `Slugged`.
 * - Generate old and new route paths and store their mapping in cache.
 * - Provide a lookup to resolve an old path to the current path for 301 redirects.
 */
final class SlugManager
{
    private array $slugUpdateList = [];
    /**
     * Constructor.
     *
     * @param CacheInterface  $cacheInterface Cache used to store slug mappings.
     * @param RouterInterface $routerInterface Router used to generate route paths.
     */
    public function __construct(private CacheInterface $cacheInterface, private RouterInterface $routerInterface)
    {
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
            if ($slugger['attr'] instanceof Slugged) {
                $attr = $slugger['attr'];
                $oldSlug = $this->getFieldValue($object, $slugger['name']);
                if (
                    !empty($attr->from) &&
                    isset($entityChangeSet[$attr->from]) &&
                    !isset($entityChangeSet[$slugger['name']])
                ) {
                    $fromValue = $entityChangeSet[$attr->from][1];
                    $this->updateSlugField($object, $slugger['name'], $fromValue);
                }

                $newSlug = $this->getFieldValue($object, $slugger['name']);
                if (isset($entityChangeSet[$slugger['name']])) {
                    $newSlug = $entityChangeSet[$slugger['name']][1];
                }

                if ($newSlug != $oldSlug && !empty($attr->routeName)) {
                    $oldRouteParams = [];
                    $newRouteParams = [];
                    if (!empty($attr->routeSlugParam)) {
                        $oldRouteParams[$attr->routeSlugParam] = $oldSlug;
                        $newRouteParams[$attr->routeSlugParam] = $newSlug;
                    }
                    if (!empty($attr->routeDefaultParams)) {
                        $oldRouteParams = array_merge($oldRouteParams, $attr->routeDefaultParams);
                        $newRouteParams = array_merge($newRouteParams, $attr->routeDefaultParams);
                    }

                    $oldPath = $this->routerInterface->generate($attr->routeName, $oldRouteParams, RouterInterface::ABSOLUTE_PATH);
                    $newPath = $this->routerInterface->generate($attr->routeName, $newRouteParams, RouterInterface::ABSOLUTE_PATH);
                    $this->slugUpdateList[$oldPath] = $newPath;
                }
            }
        }
    }

    /**
     * Persist the collected slug mappings into cache.
     *
     * This method writes each recorded mapping to the configured `CacheInterface`.
     * It clears any existing entries for both the old and new path cache keys
     * before saving the mapping.
     *
     * @return void
     */
    public function saveSlugUpdateList(): void
    {
        if (!empty($this->slugUpdateList)) {
            foreach ($this->slugUpdateList as $oldPath => $newPath) {
                $cacheKey = $this->generateCacheKeyBy($oldPath);

                $this->cacheInterface->delete($cacheKey);
                $this->cacheInterface->delete($this->generateCacheKeyBy($newPath));

                $this->cacheInterface->get($cacheKey, fn () => $newPath);
            }
            $this->slugUpdateList = [];
        }
    }

    /**
     * Lookup the current (new) path for a previously known `oldPath`.
     *
     * @param string $oldPath The requested old path to resolve (e.g. `/blog/old-slug`).
     *
     * @return string|null The resolved current path if found in cache, or null when not found.
     */
    public function getNewPath(String $oldPath): null|string
    {
        return $this->cacheInterface->get($this->generateCacheKeyBy($oldPath), fn () => null);
    }

    /**
     * Get the value of a field from the object using a conventional getter.
     *
     * @param object $object    The entity instance.
     * @param string $fieldName The field/property name (without "get").
     *
     * @return string|null The value returned by the getter, or null if not available.
     */
    private function getFieldValue(object $object, string $fieldName): String
    {
        $methodGetSlug = sprintf("get%s", ucfirst($fieldName));
        $value = null;
        if (method_exists($object, $methodGetSlug)) {
            $value = $object->{$methodGetSlug}();
        }
        return $value;
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
        $slugged = [];
        $reflection = new ReflectionClass($object);
        foreach ($reflection->getProperties() as $reflectionProperty) {
            $sluggedAttr = $reflectionProperty->getAttributes(Slugged::class);
            if ($sluggedAttr && !empty($sluggedAttr)) {
                $slugged[] = [
                    "name" => $reflectionProperty->getName(),
                    "attr" => $sluggedAttr[0]->newInstance()
                ];
            }
        }
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
    private function updateSlugField(object &$object, string $slugField, String $fromValue): void
    {
        if (!empty($fromValue)) {
            $methodSetSlugValue = sprintf("set%s", ucfirst($slugField));
            if (method_exists($object, $methodSetSlugValue)) {
                $slug = $this->generateSlgFrom($fromValue);
                $object->{$methodSetSlugValue}($slug);
            }
        }
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

    /**
     * Generate a stable cache key for a given path.
     *
     * @param string $text The input text (usually a route path).
     *
     * @return string A string suitable as a cache key.
     */
    private function generateCacheKeyBy(string $text): string
    {
        return "wisymfony_slug_history.".md5($text);
    }
}
