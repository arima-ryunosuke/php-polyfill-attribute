<?php

namespace ryunosuke\polyfill\attribute;

use Psr\SimpleCache\CacheInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionProperty;

class Provider
{
    private static ?CacheInterface $defaultCache = null;

    private ?CacheInterface $cache;

    /**
     * set cache config
     *
     * @api
     *
     * @param ?CacheInterface $cache if null, disable cache
     * @return CacheInterface previous cache
     */
    public static function setCacheConfig(?CacheInterface $cache = null): ?CacheInterface
    {
        $current = self::$defaultCache;
        self::$defaultCache = $cache;
        return $current;
    }

    public function __construct(?CacheInterface $cache = null)
    {
        $this->cache = $cache ?? self::$defaultCache ?? null;
        if ($this->cache === null) {
            trigger_error('CacheInterface is not set, but it is strongly recommended to be set.', E_USER_DEPRECATED);
        }
    }

    /**
     * emulation of ReflectionAttribute::getAttributes
     *
     * @api
     *
     * @param object|ReflectionClass|ReflectionClassConstant|ReflectionProperty|ReflectionFunctionAbstract|ReflectionParameter $reflector
     * @return ReflectionAttribute[]
     */
    public function getAttributes(object $reflector, ?string $name = null, int $flags = 0): array
    {
        // delegate php8's original method. moreover BetterReflection does not have arguments
        if (method_exists($reflector, 'getAttributes') && strpos(get_class($reflector), 'Roave\\BetterReflection\\') !== 0) {
            return $reflector->getAttributes($name, $flags); // @codeCoverageIgnore
        }

        // gather or cache
        $attributes = (function ($reflector): array {
            $cacheid = $this->cache === null ? null : urlencode(Reflection::getId($reflector));
            if ($cacheid !== null) {
                $cache = $this->cache->get($cacheid);
                if ($cache !== null) {
                    return $cache;
                }
            }

            $reflection = new Reflection($reflector);
            $attributes = $reflection->getAttributeArray();

            if ($cacheid !== null) {
                $this->cache->set($cacheid, $attributes);
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
            // Internal ReflectionAttribute can never be instantiated
            // @codeCoverageIgnoreStart
            if ($ref->isInternal()) {
                return new class(...$attribute) extends ReflectionAttribute {
                    private string $_name;
                    private array  $_arguments;
                    private bool   $_repeated;
                    private int    $_target;

                    /** @noinspection PhpMissingParentConstructorInspection */
                    public function __construct(string $name, array $arguments, bool $repeated, int $target)
                    {
                        $this->_name = $name;
                        $this->_arguments = $arguments;
                        $this->_repeated = $repeated;
                        $this->_target = $target;
                    }

                    public function getName(): string
                    {
                        return $this->_name;
                    }

                    public function getArguments(): array
                    {
                        return $this->_arguments;
                    }

                    public function isRepeated(): bool
                    {
                        return $this->_repeated;
                    }

                    public function getTarget(): int
                    {
                        return $this->_target;
                    }

                    public function newInstance(): object
                    {
                        $name = $this->_name;
                        return new $name(...$this->_arguments);
                    }
                };
            }
            // @codeCoverageIgnoreEnd

            $object = $ref->newInstanceWithoutConstructor();
            $constructor = $ref->getConstructor();
            $constructor->setAccessible(true);
            $constructor->invoke($object, $attribute['name'], $attribute['arguments'], $attribute['repeated'], $attribute['target']);
            return $object;
        }, $attributes);
    }
}
