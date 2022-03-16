<?php
namespace ryunosuke\Test;

use ArrayAccess;
use PHPUnit\Framework\Constraint\ArraySubset;
use PHPUnit\Framework\Error\Error;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

abstract class AbstractTestCase extends TestCase
{
    static $stubfile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$stubfile = realpath(__DIR__ . '/../stub.php');
        require_once self::$stubfile;
    }

    public static function forcedCallize($callable, $method = null)
    {
        if (func_num_args() == 2) {
            $callable = func_get_args();
        }

        if (is_string($callable) && strpos($callable, '::') !== false) {
            $parts = explode('::', $callable);
            $method = new ReflectionMethod($parts[0], $parts[1]);
            if (!$method->isPublic() && $method->isStatic()) {
                $method->setAccessible(true);
                return function () use ($method) {
                    return $method->invokeArgs(null, func_get_args());
                };
            }
        }

        if (is_array($callable) && count($callable) === 2) {
            try {
                $method = new ReflectionMethod($callable[0], $callable[1]);
                if (!$method->isPublic()) {
                    $method->setAccessible(true);
                    return function () use ($callable, $method) {
                        return $method->invokeArgs($method->isStatic() ? null : $callable[0], func_get_args());
                    };
                }
            }
            catch (ReflectionException $ex) {
            }
        }

        return $callable;
    }

    public static function assertException($e, $callback)
    {
        $callback = self::forcedCallize($callback);

        try {
            $callback(...array_slice(func_get_args(), 2));
        }
        catch (Error $ex) {
            throw $ex;
        }
        catch (Exception $ex) {
            throw $ex;
        }
        catch (Throwable $ex) {
            $check_code = true;
            if (is_string($e)) {
                $check_code = false;
                if (class_exists($e)) {
                    $e = (new ReflectionClass($e))->newInstanceWithoutConstructor();
                }
                else {
                    if ($ex instanceof \Exception) {
                        $e = new \Exception($e);
                    }
                    if ($ex instanceof \Error) {
                        $e = new \Error($e);
                    }
                }
            }

            self::assertInstanceOf(get_class($e), $ex);
            if ($check_code) {
                self::assertEquals($e->getCode(), $ex->getCode());
            }
            if (strlen($e->getMessage()) > 0) {
                self::assertStringContainsString($e->getMessage(), $ex->getMessage());
            }
            return;
        }
        self::fail(get_class($e) . ' is not thrown.');
    }

    public static function assertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        if (!(is_array($subset) || $subset instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                1,
                'array or ArrayAccess'
            );
        }

        if (!(is_array($array) || $array instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                2,
                'array or ArrayAccess'
            );
        }

        $constraint = new ArraySubset($subset, $checkForObjectIdentity);

        static::assertThat($array, $constraint, $message);
    }
}
