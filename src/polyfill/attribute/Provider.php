<?php

namespace ryunosuke\polyfill\attribute;

use Psr\SimpleCache\CacheInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;

class Provider
{
    private static ?CacheInterface $cache = null;

    /**
     * set cache config
     *
     * @api
     *
     * @param ?CacheInterface $cache if null, disable cache
     */
    public static function setCacheConfig(?CacheInterface $cache = null)
    {
        self::$cache = $cache;
    }

    /**
     * emulation of ReflectionAttribute::getAttributes
     *
     * @api
     *
     * @param ReflectionClass|ReflectionClassConstant|ReflectionProperty|ReflectionFunctionAbstract|ReflectionParameter $reflector
     * @return ReflectionAttribute[]
     */
    public function getAttributes(Reflector $reflector, ?string $name = null, int $flags = 0): array
    {
        // delegate php8's original method
        if (method_exists($reflector, 'getAttributes')) {
            return $reflector->getAttributes($name, $flags); // @codeCoverageIgnore
        }

        // gather or cache
        $attributes = (function ($reflector): array {
            $cacheid = self::$cache === null ? null : urlencode(Reflection::getId($reflector));
            if ($cacheid !== null) {
                $cache = self::$cache->get($cacheid);
                if ($cache !== null) {
                    return $cache;
                }
            }

            $reflection = new Reflection($reflector);
            $attributes = $reflection->getAttributeArray();

            if ($cacheid !== null) {
                self::$cache->set($cacheid, $attributes);
            }
            return $attributes;
        })($reflector);

        // filter
        if ($name !== null) {
            $name = ltrim($name, '\\');
            $attributes = array_values(array_filter($attributes, function (array $attribute) use ($name, $flags) {
                return (false
                    || $attribute['name'] === $name
                    || ($flags & ReflectionAttribute::IS_INSTANCEOF && is_subclass_of($attribute['name'], $name))
                );
            }));
        }

        // create
        $ref = new ReflectionClass(ReflectionAttribute::class);
        return array_map(function ($attribute) use ($ref) {
            $object = $ref->newInstanceWithoutConstructor();
            $constructor = $ref->getConstructor();
            $constructor->setAccessible(true);
            $constructor->invoke($object, $attribute['name'], $attribute['arguments'], $attribute['repeated'], $attribute['target']);
            return $object;
        }, $attributes);
    }

    /**
     * single version of getAttributes
     *
     * @api
     *
     * @param ReflectionClass|ReflectionClassConstant|ReflectionProperty|ReflectionFunctionAbstract|ReflectionParameter $reflector
     * @return ?ReflectionAttribute
     */
    public function getAttribute(Reflector $reflector, ?string $name = null, int $flags = 0): ?ReflectionAttribute
    {
        return $this->getAttributes($reflector, $name, $flags)[0] ?? null;
    }

    /**
     * get Attribute directly without Reflection instance
     *
     * @api
     *
     * @param string|array|object $target
     * @return ReflectionAttribute[]
     */
    public function getAttributesOf($target, ?string $name = null, int $flags = 0): array
    {
        return $this->getAttributes(Reflection::reflectionOf($target), $name, $flags);
    }

    /**
     * single version of getAttributesOf
     *
     * @api
     *
     * @param string|array|object $target
     * @return ?ReflectionAttribute
     */
    public function getAttributeOf($target, ?string $name = null, int $flags = 0): ?ReflectionAttribute
    {
        return $this->getAttributesOf($target, $name, $flags)[0] ?? null;
    }
}
