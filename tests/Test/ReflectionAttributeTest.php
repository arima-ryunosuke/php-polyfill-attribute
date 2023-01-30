<?php
/** @noinspection PhpLanguageLevelInspection */
namespace ryunosuke\Test;

use Attribute;
use ReflectionAttribute;
use ryunosuke\polyfill\attribute\Provider;
use ryunosuke\Test\polyfill\attribute\Attributes\NoConstructorAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\NoMethodAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\TestAttribute;

class ReflectionAttributeTest extends \ryunosuke\Test\AbstractTestCase
{
    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute]
    function test_clone($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('Trying to clone an uncloneable object of class ReflectionAttribute', function () use ($attribute) {
            return clone $attribute;
        });
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute]
    function test_stringable($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('could not be converted to string', function () use ($attribute) {
            return (string) $attribute;
        });
        $this->assertException('Call to undefined method', function () use ($attribute) {
            return $attribute->export();
        });
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute(1, 2, 3)]
    #[TestAttribute(7, 8, c: 9)]
    function test_getter($reflector)
    {
        $provider = new Provider();
        $attributes = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__));

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

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute()]
    function test_newInstance_default($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3, 'z' => []], $attribute->newInstance()->args);
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute(7, 8)]
    function test_newInstance_positioned($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 8, 'c' => 3, 'z' => []], $attribute->newInstance()->args);
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute(7, 8, 9, 99)]
    function test_newInstance_valiadic($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 8, 'c' => 9, 'z' => [99]], $attribute->newInstance()->args);
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute(a: 7, c: 9)]
    function test_newInstance_named($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 2, 'c' => 9, 'z' => []], $attribute->newInstance()->args);
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute(7, c: 9)]
    function test_newInstance_mix($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertEquals(['a' => 7, 'b' => 2, 'c' => 9, 'z' => []], $attribute->newInstance()->args);
    }

    /** @noinspection PhpUndefinedClassInspection */
    /**
     * @dataProvider provideClassReflector
     */
    #[UndefinedAttribute]
    function test_newInstance_noExists($reflector)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];

        $this->assertException('not found', function () use ($attribute) {
            return $attribute->newInstance();
        });
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[ReflectionAttributeTest]
    function test_newInstance_noAttribute($reflector, $data)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];
        $this->assertInstanceOf(ReflectionAttribute::class, $attribute);

        if ($data['internal']) {
            $this->assertException('non-attribute class', function () use ($attribute) {
                return $attribute->newInstance();
            });
        }
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[NoMethodAttribute]
    function test_newInstance_noTarget($reflector, $data)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];
        $this->assertInstanceOf(ReflectionAttribute::class, $attribute);

        if ($data['internal']) {
            $this->assertException('cannot target method (allowed targets: class, function, property, class constant, parameter)', function () use ($attribute) {
                return $attribute->newInstance();
            });
        }
    }

    /**
     * @dataProvider provideClassReflector
     */
    #[TestAttribute(1)]
    #[TestAttribute(2)]
    function test_newInstance_noRepeat($reflector, $data)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];
        $this->assertInstanceOf(ReflectionAttribute::class, $attribute);

        if ($data['internal']) {
            $this->assertException('must not be repeated', function () use ($attribute) {
                return $attribute->newInstance();
            });
        }
    }

    /** @noinspection PhpMethodParametersCountMismatchInspection */
    /**
     * @dataProvider provideClassReflector
     */
    #[NoConstructorAttribute(1)]
    function test_newInstance_mismatchParameter($reflector, $data)
    {
        $provider = new Provider();
        $attribute = $provider->getAttributes($reflector(get_class($this))->getMethod(__FUNCTION__))[0];
        $this->assertInstanceOf(ReflectionAttribute::class, $attribute);

        if ($data['internal']) {
            $this->assertException('does not have a constructor', function () use ($attribute) {
                return $attribute->newInstance();
            });
        }
    }
}
