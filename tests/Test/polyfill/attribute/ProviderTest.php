<?php
/** @noinspection PhpLanguageLevelInspection */
namespace ryunosuke\Test\polyfill\attribute;

use ArrayObject;
use Attribute;
use GlobalMagic;
use Psr\SimpleCache\CacheInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;
use ryunosuke\polyfill\attribute\Provider;
use ryunosuke\Test\polyfill\attribute\Attributes\ClassAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\ClassAttributeSub;
use ryunosuke\Test\polyfill\attribute\Attributes\ClassConstantAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\FunctionAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\MagicAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\MethodAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\ParameterAttribute;
use ryunosuke\Test\polyfill\attribute\Attributes\PropertyAttribute;
use ryunosuke\Test\polyfill\attribute\stub\Concrete;
use ryunosuke\Test\polyfill\attribute\stub\ConcreteTrait;
use ryunosuke\Test\polyfill\attribute\stub\Magic;
use ryunosuke\Test\polyfill\attribute\stub\Misc;
use ryunosuke\Test\polyfill\attribute\stub\Multiple;
use ryunosuke\Test\polyfill\attribute\stub\SubConcrete;

class ProviderTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_cache()
    {
        if (version_compare(PHP_VERSION, 8) >= 0) {
            return $this->markTestSkipped();
        }

        $provider = new Provider();

        $cache = new class () implements CacheInterface {
            public $items = [];

            public function get($key, $default = null)
            {
                return $this->items[$key] ?? null;
            }

            public function set($key, $value, $ttl = null)
            {
                return $this->items[$key] = $value;
            }

            public function delete($key) { }

            public function clear() { }

            public function getMultiple($keys, $default = null) { }

            public function setMultiple($values, $ttl = null) { }

            public function deleteMultiple($keys) { }

            public function has($key) { }
        };

        Provider::setCacheConfig($cache);

        $micrtime = microtime(true);
        $provider->getAttributeOf(Concrete::class);
        $time1 = microtime(true) - $micrtime;

        $micrtime = microtime(true);
        $provider->getAttributeOf(Concrete::class);
        $time2 = microtime(true) - $micrtime;

        $cache->items = [];

        $micrtime = microtime(true);
        $provider->getAttributeOf(Concrete::class);
        $time3 = microtime(true) - $micrtime;

        $this->assertLessThan($time1, $time2);
        $this->assertLessThan($time3, $time2);

        Provider::setCacheConfig(null);
    }

    function test_getAttributes()
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes(new ReflectionClass(SubConcrete::class));
        $this->assertCount(3, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(['class3'], $attributes[0]->getArguments());
        $this->assertEquals(ClassAttributeSub::class, $attributes[1]->getName());
        $this->assertEquals(['class4'], $attributes[1]->getArguments());
        $this->assertEquals(MagicAttribute::class, $attributes[2]->getName());
        $this->assertEquals(['class5'], $attributes[2]->getArguments());

        $attributes = $provider->getAttributes(new ReflectionClass(SubConcrete::class), ClassAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
        $this->assertCount(2, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(['class3'], $attributes[0]->getArguments());
        $this->assertEquals(ClassAttributeSub::class, $attributes[1]->getName());
        $this->assertEquals(['class4'], $attributes[1]->getArguments());

        $attributes = $provider->getAttributes(new ReflectionClass(SubConcrete::class), ClassAttribute::class);
        $this->assertCount(1, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(['class3'], $attributes[0]->getArguments());

        $attributes = $provider->getAttributes(new ReflectionClass(SubConcrete::class), ClassAttributeSub::class);
        $this->assertCount(1, $attributes);
        $this->assertEquals(ClassAttributeSub::class, $attributes[0]->getName());
        $this->assertEquals(['class4'], $attributes[0]->getArguments());

        $attributes = $provider->getAttributes(new ReflectionClass(SubConcrete::class), 'UndefinedAttribute');
        $this->assertCount(0, $attributes);
    }

    function test_getAttribute()
    {
        $provider = new Provider();

        $attribute = $provider->getAttribute(new ReflectionClass(SubConcrete::class), ClassAttribute::class);
        $this->assertEquals(ClassAttribute::class, $attribute->getName());
        $this->assertEquals(['class3'], $attribute->getArguments());

        $attribute = $provider->getAttribute(new ReflectionClass(SubConcrete::class), ClassAttributeSub::class);
        $this->assertEquals(ClassAttributeSub::class, $attribute->getName());
        $this->assertEquals(['class4'], $attribute->getArguments());

        $attribute = $provider->getAttribute(new ReflectionClass(SubConcrete::class), MagicAttribute::class);
        $this->assertEquals(MagicAttribute::class, $attribute->getName());
        $this->assertEquals(['class5'], $attribute->getArguments());

        $attribute = $provider->getAttribute(new ReflectionClass(SubConcrete::class), 'UndefinedAttribute');
        $this->assertNull($attribute);
    }

    function test_getAttributeOf()
    {
        $provider = new Provider();

        $attribute = $provider->getAttributeOf(Concrete::class);
        $this->assertEquals(ClassAttribute::class, $attribute->getName());
        $this->assertEquals(['class1'], $attribute->getArguments());

        $attribute = $provider->getAttributeOf(ConcreteTrait::class);
        $this->assertEquals(ClassAttribute::class, $attribute->getName());
        $this->assertEquals(['class1'], $attribute->getArguments());

        $attribute = $provider->getAttributeOf(__NAMESPACE__ . '\\stub\\concrete');
        $this->assertEquals(FunctionAttribute::class, $attribute->getName());
        $this->assertEquals(['function1'], $attribute->getArguments());
    }

    function test_ClassAttribute()
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes(new ReflectionClass(Concrete::class));
        $this->assertCount(2, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(ClassAttribute::class, $attributes[1]->getName());
        $this->assertEquals(['class1'], $attributes[0]->getArguments());
        $this->assertEquals(['class2'], $attributes[1]->getArguments());
        $this->assertEquals(Attribute::TARGET_CLASS, $attributes[0]->getTarget());
        $this->assertEquals(Attribute::TARGET_CLASS, $attributes[1]->getTarget());
        $this->assertTrue($attributes[0]->isRepeated());
        $this->assertTrue($attributes[1]->isRepeated());
    }

    function test_ClassConstantAttribute()
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes((new ReflectionClass(Concrete::class))->getReflectionConstant('C'));
        $this->assertCount(2, $attributes);
        $this->assertEquals(ClassConstantAttribute::class, $attributes[0]->getName());
        $this->assertEquals(ClassConstantAttribute::class, $attributes[1]->getName());
        $this->assertEquals(['constant1'], $attributes[0]->getArguments());
        $this->assertEquals(['constant2'], $attributes[1]->getArguments());
        $this->assertEquals(Attribute::TARGET_CLASS_CONSTANT, $attributes[0]->getTarget());
        $this->assertEquals(Attribute::TARGET_CLASS_CONSTANT, $attributes[1]->getTarget());
        $this->assertTrue($attributes[0]->isRepeated());
        $this->assertTrue($attributes[1]->isRepeated());
    }

    function test_PropertyAttribute()
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes((new ReflectionClass(Concrete::class))->getProperty('p'));
        $this->assertCount(2, $attributes);
        $this->assertEquals(PropertyAttribute::class, $attributes[0]->getName());
        $this->assertEquals(PropertyAttribute::class, $attributes[1]->getName());
        $this->assertEquals(['property1'], $attributes[0]->getArguments());
        $this->assertEquals(['property2'], $attributes[1]->getArguments());
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attributes[0]->getTarget());
        $this->assertEquals(Attribute::TARGET_PROPERTY, $attributes[1]->getTarget());
        $this->assertTrue($attributes[0]->isRepeated());
        $this->assertTrue($attributes[1]->isRepeated());
    }

    function test_MethodAttribute()
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes((new ReflectionClass(Concrete::class))->getMethod('m'));
        $this->assertCount(2, $attributes);
        $this->assertEquals(MethodAttribute::class, $attributes[0]->getName());
        $this->assertEquals(MethodAttribute::class, $attributes[1]->getName());
        $this->assertEquals(['method1'], $attributes[0]->getArguments());
        $this->assertEquals(['method2'], $attributes[1]->getArguments());
        $this->assertEquals(Attribute::TARGET_METHOD, $attributes[0]->getTarget());
        $this->assertEquals(Attribute::TARGET_METHOD, $attributes[1]->getTarget());
        $this->assertTrue($attributes[0]->isRepeated());
        $this->assertTrue($attributes[1]->isRepeated());
    }

    function test_MethodParameterAttribute()
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $attributes = $provider->getAttributes((new ReflectionClass(Concrete::class))->getMethod('m')->getParameters()[$n]);
            $this->assertCount(2, $attributes);
            $this->assertEquals(ParameterAttribute::class, $attributes[0]->getName());
            $this->assertEquals(ParameterAttribute::class, $attributes[1]->getName());
            $this->assertEquals(['parameter' . ($n + 1) . '1'], $attributes[0]->getArguments());
            $this->assertEquals(['parameter' . ($n + 1) . '2'], $attributes[1]->getArguments());
            $this->assertEquals(Attribute::TARGET_PARAMETER, $attributes[0]->getTarget());
            $this->assertEquals(Attribute::TARGET_PARAMETER, $attributes[1]->getTarget());
            $this->assertTrue($attributes[0]->isRepeated());
            $this->assertTrue($attributes[1]->isRepeated());
        }
    }

    function test_FunctionAttribute()
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes((new ReflectionFunction(__NAMESPACE__ . '\\stub\\concrete')));
        $this->assertCount(2, $attributes);
        $this->assertEquals(FunctionAttribute::class, $attributes[0]->getName());
        $this->assertEquals(FunctionAttribute::class, $attributes[1]->getName());
        $this->assertEquals(['function1'], $attributes[0]->getArguments());
        $this->assertEquals(['function2'], $attributes[1]->getArguments());
        $this->assertEquals(Attribute::TARGET_FUNCTION, $attributes[0]->getTarget());
        $this->assertEquals(Attribute::TARGET_FUNCTION, $attributes[1]->getTarget());
        $this->assertTrue($attributes[0]->isRepeated());
        $this->assertTrue($attributes[1]->isRepeated());
    }

    function test_FunctionParameterAttribute()
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $attributes = $provider->getAttributes((new ReflectionFunction(__NAMESPACE__ . '\\stub\\concrete'))->getParameters()[$n]);
            $this->assertCount(2, $attributes);
            $this->assertEquals(ParameterAttribute::class, $attributes[0]->getName());
            $this->assertEquals(ParameterAttribute::class, $attributes[1]->getName());
            $this->assertEquals(['parameter' . ($n + 1) . '1'], $attributes[0]->getArguments());
            $this->assertEquals(['parameter' . ($n + 1) . '2'], $attributes[1]->getArguments());
            $this->assertEquals(Attribute::TARGET_PARAMETER, $attributes[0]->getTarget());
            $this->assertEquals(Attribute::TARGET_PARAMETER, $attributes[1]->getTarget());
            $this->assertTrue($attributes[0]->isRepeated());
            $this->assertTrue($attributes[1]->isRepeated());
        }
    }

    function test_GlobalMagicClassAttribute()
    {
        $provider = new Provider();

        $reflection = new ReflectionClass(GlobalMagic::class);
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() - 1,
            'namespace' => '',
            'class'     => GlobalMagic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    function test_MagicClassAttribute()
    {
        $provider = new Provider();

        $reflection = new ReflectionClass(Magic::class);
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() - 1,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    function test_MagicClassConstantAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionClass(Magic::class))->getReflectionConstant('C');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getDeclaringClass()->getStartLine() - 1 + 3,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    function test_MagicPropertyAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionClass(Magic::class))->getProperty('p');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getDeclaringClass()->getStartLine() - 1 + 6,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    function test_MagicMethodAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionClass(Magic::class))->getMethod('m');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getDeclaringClass()->getStartLine() - 1 + 9,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => Magic::class . '::m',
            'function'  => 'm',
        ], $attributes[0]->getArguments());
    }

    function test_MagicMethodParameterAttribute()
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $reflection = (new ReflectionClass(Magic::class))->getMethod('m')->getParameters()[$n];
            $attributes = $provider->getAttributes($reflection);
            $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
            $this->assertEquals([
                'dir'       => dirname(self::$stubfile),
                'file'      => self::$stubfile,
                'line'      => $reflection->getDeclaringClass()->getStartLine() - 1 + 11 + ($n * 2),
                'namespace' => __NAMESPACE__ . '\\stub',
                'class'     => Magic::class,
                'trait'     => '',
                'method'    => Magic::class . '::m',
                'function'  => 'm',
            ], $attributes[0]->getArguments());
        }
    }

    function test_MagicAnonymosAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionObject(Magic::magic_anonymous()));
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $arguments = $attributes[0]->getArguments();
        $this->assertArraySubset([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() - 1,
            'namespace' => __NAMESPACE__ . '\\stub',
            'trait'     => '',
            'method'    => Magic::class . '::magic_anonymous',
            'function'  => 'magic_anonymous',
        ], $arguments);
        $this->assertStringContainsString('class@anonymous', $arguments['class']);
    }

    function test_MagicAnonymosMethodAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionObject(Magic::magic_anonymous()))->getMethod('m');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $arguments = $attributes[0]->getArguments();
        $this->assertArraySubset([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() - 1,
            'namespace' => __NAMESPACE__ . '\\stub',
            'trait'     => '',
            'function'  => 'm',
        ], $arguments);
        $this->assertStringContainsString('class@anonymous', $arguments['class']);
        $this->assertStringContainsString('class@anonymous', $arguments['method']);
        $this->assertStringContainsString('::m', $arguments['method']);
    }

    function test_MagicAnonymosMethodParameterAttribute()
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $reflection = (new ReflectionObject(Magic::magic_anonymous()))->getMethod('m')->getParameters()[$n];
            $attributes = $provider->getAttributes($reflection);
            $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
            $arguments = $attributes[0]->getArguments();
            $this->assertArraySubset([
                'dir'       => dirname(self::$stubfile),
                'file'      => self::$stubfile,
                'line'      => $reflection->getDeclaringFunction()->getStartLine() - 1 + 2 + ($n * 2),
                'namespace' => __NAMESPACE__ . '\\stub',
                'trait'     => '',
                'function'  => 'm',
            ], $arguments);
            $this->assertStringContainsString('class@anonymous', $arguments['class']);
            $this->assertStringContainsString('class@anonymous', $arguments['method']);
            $this->assertStringContainsString('::m', $arguments['method']);
        }
    }

    function test_MagicClosureAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionFunction(Magic::magic_closure()));
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() - 1,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => __NAMESPACE__ . '\\stub\{closure}',
            'function'  => __NAMESPACE__ . '\\stub\{closure}',
        ], $attributes[0]->getArguments());
    }

    function test_MagicClosureParameterAttribute()
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $reflection = (new ReflectionFunction(Magic::magic_closure()))->getParameters()[$n];
            $attributes = $provider->getAttributes($reflection);
            $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
            $this->assertEquals([
                'dir'       => dirname(self::$stubfile),
                'file'      => self::$stubfile,
                'line'      => $reflection->getDeclaringClass()->getStartLine() - 1 + 39 + ($n * 2),
                'namespace' => __NAMESPACE__ . '\\stub',
                'class'     => Magic::class,
                'trait'     => '',
                'method'    => __NAMESPACE__ . '\\stub\\{closure}',
                'function'  => __NAMESPACE__ . '\\stub\\{closure}',
            ], $attributes[0]->getArguments());
        }
    }

    function test_MagicFunctionAttribute()
    {
        $provider = new Provider();

        $reflection = (new ReflectionFunction(__NAMESPACE__ . '\\stub\\magic'));
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() - 1,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => '',
            'trait'     => '',
            'method'    => __NAMESPACE__ . '\\stub\\magic',
            'function'  => __NAMESPACE__ . '\\stub\\magic',
        ], $attributes[0]->getArguments());
    }

    function test_MagicFunctionParameterAttribute()
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $reflection = (new ReflectionFunction(__NAMESPACE__ . '\\stub\\magic'))->getParameters()[$n];
            $attributes = $provider->getAttributes($reflection);
            $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
            $this->assertEquals([
                'dir'       => dirname(self::$stubfile),
                'file'      => self::$stubfile,
                'line'      => $reflection->getDeclaringFunction()->getStartLine() - 1 + 2 + ($n * 2),
                'namespace' => __NAMESPACE__ . '\\stub',
                'class'     => '',
                'trait'     => '',
                'method'    => __NAMESPACE__ . '\\stub\\magic',
                'function'  => __NAMESPACE__ . '\\stub\\magic',
            ], $attributes[0]->getArguments());
        }
    }

    function test_misc()
    {
        $provider = new Provider();

        # for multiple const
        $attribute = $provider->getAttribute((new ReflectionClass(Multiple::class))->getReflectionConstant('C'));
        $this->assertEquals(ClassConstantAttribute::class, $attribute->getName());
        $this->assertEquals(['constantM'], $attribute->getArguments());
        $attribute = $provider->getAttribute((new ReflectionClass(Multiple::class))->getReflectionConstant('C1'));
        $this->assertEquals(ClassConstantAttribute::class, $attribute->getName());
        $this->assertEquals(['constantM12'], $attribute->getArguments());
        $attribute = $provider->getAttribute((new ReflectionClass(Multiple::class))->getReflectionConstant('C2'));
        $this->assertEquals(ClassConstantAttribute::class, $attribute->getName());
        $this->assertEquals(['constantM12'], $attribute->getArguments());

        # for multiple property
        $attribute = $provider->getAttribute((new ReflectionClass(Multiple::class))->getProperty('p'));
        $this->assertEquals(PropertyAttribute::class, $attribute->getName());
        $this->assertEquals(['propertyM'], $attribute->getArguments());
        $attribute = $provider->getAttribute((new ReflectionClass(Multiple::class))->getProperty('p1'));
        $this->assertEquals(PropertyAttribute::class, $attribute->getName());
        $this->assertEquals(['propertyM12'], $attribute->getArguments());
        $attribute = $provider->getAttribute((new ReflectionClass(Multiple::class))->getProperty('p2'));
        $this->assertEquals(PropertyAttribute::class, $attribute->getName());
        $this->assertEquals(['propertyM12'], $attribute->getArguments());

        # for literal/expression
        $reflection = new ReflectionClass(Misc::class);
        $this->assertEquals([
            'builtin'    => [null, false, true],
            'const'      => [M_PI, ArrayObject::ARRAY_AS_PROPS, ArrayObject::class],
            'magic'      => [Misc::class, $reflection->getStartLine() - 1],
            'expression' => ['hello' . 'world', 3.14 * 2, 1 << 8],
            'nestarray'  => [
                'x' => [
                    'y' => [
                        'z' => ['xyz'],
                    ],
                ],
            ],
        ], $provider->getAttribute($reflection)->getArguments());
    }
}
