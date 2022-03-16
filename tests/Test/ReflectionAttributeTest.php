<?php
/** @noinspection PhpLanguageLevelInspection */
namespace ryunosuke\Test;

use Attribute;
use ReflectionClass;
use ryunosuke\polyfill\attribute\Provider;
use ryunosuke\Test\polyfill\attribute\Attributes\NoConstructorAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\NoMethodAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\TestAttribute;

class ReflectionAttributeTest extends \ryunosuke\Test\AbstractTestCase
{
    #[TestAttribute]
    function test_clone()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('Trying to clone an uncloneable object of class ReflectionAttribute', function () use ($attribute) {
            return clone $attribute;
        });
    }

    #[TestAttribute]
    function test_stringable()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('could not be converted to string', function () use ($attribute) {
            return (string) $attribute;
        });
        $this->assertException('Call to undefined method', function () use ($attribute) {
            return $attribute->export();
        });
    }

    #[TestAttribute(1, 2, 3)]
    #[TestAttribute(7, 8, c: 9)]
    function test_getter()
    {
        $provider = new Provider();
        $attributes = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__));

        $attribute = $attributes[0];
        $this->assertEquals(TestAttribute::class, $attribute->getName());
        $this->assertEquals([1, 2, 3], $attribute->getArguments());
        $this->assertEquals(Attribute::TARGET_METHOD, $attribute->getTarget());
        $this->assertEquals(true, $attribute->isRepeated());

        $attribute = $attributes[1];
        $this->assertEquals(TestAttribute::class, $attribute->getName());
        $this->assertEquals([7, 8, 'c' => 9], $attribute->getArguments());
        $this->assertEquals(Attribute::TARGET_METHOD, $attribute->getTarget());
        $this->assertEquals(true, $attribute->isRepeated());
    }

    #[TestAttribute()]
    function test_newInstance_default()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3, 'z' => []], $attribute->newInstance()->args);
    }

    #[TestAttribute(7, 8)]
    function test_newInstance_positioned()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 8, 'c' => 3, 'z' => []], $attribute->newInstance()->args);
    }

    #[TestAttribute(7, 8, 9, 99)]
    function test_newInstance_valiadic()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 8, 'c' => 9, 'z' => [99]], $attribute->newInstance()->args);
    }

    #[TestAttribute(a: 7, c: 9)]
    function test_newInstance_named()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 2, 'c' => 9, 'z' => []], $attribute->newInstance()->args);
    }

    #[TestAttribute(7, c: 9)]
    function test_newInstance_mix()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 2, 'c' => 9, 'z' => []], $attribute->newInstance()->args);
    }

    /** @noinspection PhpUndefinedClassInspection */
    #[UndefinedAttribute]
    function test_newInstance_noExists()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('not found', function () use ($attribute) {
            return $attribute->newInstance();
        });
    }

    #[ReflectionAttributeTest]
    function test_newInstance_noAttribute()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('non-attribute class', function () use ($attribute) {
            return $attribute->newInstance();
        });
    }

    #[NoMethodAttribute]
    function test_newInstance_noTarget()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('cannot target method (allowed targets: class, function, property, class constant, parameter)', function () use ($attribute) {
            return $attribute->newInstance();
        });
    }

    #[TestAttribute(1)]
    #[TestAttribute(2)]
    function test_newInstance_noRepeat()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('must not be repeated', function () use ($attribute) {
            return $attribute->newInstance();
        });
    }

    /** @noinspection PhpMethodParametersCountMismatchInspection */
    #[NoConstructorAttribute(1)]
    function test_newInstance_mismatchParameter()
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes((new ReflectionClass($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('does not have a constructor', function () use ($attribute) {
            return $attribute->newInstance();
        });
    }
}
