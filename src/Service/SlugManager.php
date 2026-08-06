<?php

declare(strict_types=1);

namespace Wisymfony\SlugHistoryBundle\Service;

use ReflectionClass;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Wisymfony\SlugHistoryBundle\Attribute\Slugged;

final class SlugManager
{
    private array $slugUpdateList = [];
    public function __construct(private CacheInterface $cacheInterface, private RouterInterface $routerInterface){
    }

    public function applySlugged(object &$object, array $entityChangeSet = []) : void {
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
                    
                    $oldPath = $this->routerInterface->generate($attr->routeName, $oldRouteParams);
                    $newPath = $this->routerInterface->generate($attr->routeName, $newRouteParams);
                    $this->slugUpdateList[$oldPath] = $newPath;
                }
            }
        }
    }

    public function saveSlugUpdateList() : void {
        if (!empty($this->slugUpdateList)) {
            foreach ($this->slugUpdateList as $oldPath => $newPath) {
                $cacheKey = $this->generateCacheKeyBy($oldPath);
                
                $this->cacheInterface->delete($cacheKey);
                $this->cacheInterface->delete($this->generateCacheKeyBy($newPath));

                $this->cacheInterface->get($cacheKey, fn() => $newPath);
            }
            $this->slugUpdateList = [];
        }
    }

    public function getNewPath(String $oldPath) : string {
        $path = $this->cacheInterface->get($this->generateCacheKeyBy($oldPath), fn() => $oldPath);
        return $path;
    }

    private function getFieldValue(object $object, string $fieldName) : String {
        $methodGetSlug = sprintf("get%s", ucfirst($fieldName));
        $value = null;
        if (method_exists($object, $methodGetSlug)) {
            $value = $object->{$methodGetSlug}();    
        }
        return $value;
    }

    private function getSlugged(object $object) : array {
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
    private function updateSlugField(object &$object, string $slugField, String $fromValue) : void {
        if (!empty($fromValue)) {
            $methodSetSlugValue = sprintf("set%s", ucfirst($slugField));
            if (method_exists($object, $methodSetSlugValue)) {
                $slug = $this->generateSlgFrom($fromValue);
                $object->{$methodSetSlugValue}($slug);
            }
        }
    }

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

    private function generateCacheKeyBy(string $text) : string {
        return "wisymfony_slug_history.".str_replace('/', '__', $text);
    }
}
