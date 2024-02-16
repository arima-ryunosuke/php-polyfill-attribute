<?php
/** @noinspection PhpLanguageLevelInspection */
namespace ryunosuke\Test\polyfill\attribute;

use ArrayObject;
use Attribute;
use GlobalMagic;
use Psr\SimpleCache\CacheInterface;
use ReflectionAttribute;
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
use ryunosuke\Test\polyfill\attribute\stub\Magic;
use ryunosuke\Test\polyfill\attribute\stub\Misc;
use ryunosuke\Test\polyfill\attribute\stub\Multiple;
use ryunosuke\Test\polyfill\attribute\stub\SubConcrete;

class ProviderTest extends \ryunosuke\Test\AbstractTestCase
{
    /**
     * @dataProvider provideClassReflector
     */
    function test_cache($reflector)
    {
        if (version_compare(PHP_VERSION, 8) >= 0) {
            return $this->markTestSkipped();
        }

        $old = Provider::setCacheConfig(null);

        @new Provider();
        $this->assertStringContainsString('CacheInterface is not set', error_get_last()['message']);

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

        $provider = new Provider();

        $micrtime = microtime(true);
        $provider->getAttributes($reflector(Concrete::class))[0];
        $time1 = microtime(true) - $micrtime;

        $micrtime = microtime(true);
        $provider->getAttributes($reflector(Concrete::class))[0];
        $time2 = microtime(true) - $micrtime;

        $cache->items = [];

        $micrtime = microtime(true);
        $provider->getAttributes($reflector(Concrete::class))[0];
        $time3 = microtime(true) - $micrtime;

        $this->assertLessThan($time1, $time2);
        $this->assertLessThan($time3, $time2);

        Provider::setCacheConfig($old);
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_getAttributes($reflector)
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes($reflector(SubConcrete::class));
        $this->assertCount(3, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(['class3'], $attributes[0]->getArguments());
        $this->assertEquals(ClassAttributeSub::class, $attributes[1]->getName());
        $this->assertEquals(['class4'], $attributes[1]->getArguments());
        $this->assertEquals(MagicAttribute::class, $attributes[2]->getName());
        $this->assertEquals(['class5'], $attributes[2]->getArguments());

        $attributes = $provider->getAttributes($reflector(SubConcrete::class), ClassAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
        $this->assertCount(2, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(['class3'], $attributes[0]->getArguments());
        $this->assertEquals(ClassAttributeSub::class, $attributes[1]->getName());
        $this->assertEquals(['class4'], $attributes[1]->getArguments());

        $attributes = $provider->getAttributes($reflector(SubConcrete::class), ClassAttribute::class);
        $this->assertCount(1, $attributes);
        $this->assertEquals(ClassAttribute::class, $attributes[0]->getName());
        $this->assertEquals(['class3'], $attributes[0]->getArguments());

        $attributes = $provider->getAttributes($reflector(SubConcrete::class), ClassAttributeSub::class);
        $this->assertCount(1, $attributes);
        $this->assertEquals(ClassAttributeSub::class, $attributes[0]->getName());
        $this->assertEquals(['class4'], $attributes[0]->getArguments());

        $attributes = $provider->getAttributes($reflector(SubConcrete::class), 'UndefinedAttribute');
        $this->assertCount(0, $attributes);
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_ClassAttribute($reflector)
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes($reflector(Concrete::class));
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

    /**
     * @dataProvider provideClassReflector
     */
    function test_ClassConstantAttribute($reflector)
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes(($reflector(Concrete::class))->getReflectionConstant('C'));
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

    /**
     * @dataProvider provideClassReflector
     */
    function test_PropertyAttribute($reflector)
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes(($reflector(Concrete::class))->getProperty('p'));
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

    /**
     * @dataProvider provideClassReflector
     */
    function test_MethodAttribute($reflector)
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes(($reflector(Concrete::class))->getMethod('m'));
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

    /**
     * @dataProvider provideClassReflector
     */
    function test_MethodParameterAttribute($reflector)
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $attributes = $provider->getAttributes(($reflector(Concrete::class))->getMethod('m')->getParameters()[$n]);
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

    /**
     * @dataProvider provideFunctionReflector
     */
    function test_FunctionAttribute($reflector)
    {
        $provider = new Provider();

        $attributes = $provider->getAttributes(($reflector(__NAMESPACE__ . '\\stub\\concrete')));
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

    /**
     * @dataProvider provideFunctionReflector
     */
    function test_FunctionParameterAttribute($reflector)
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $attributes = $provider->getAttributes(($reflector(__NAMESPACE__ . '\\stub\\concrete'))->getParameters()[$n]);
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

    /**
     * @dataProvider provideClassReflector
     */
    function test_GlobalMagicClassAttribute($reflector, $data)
    {
        $provider = new Provider();

        $reflection = $reflector(GlobalMagic::class);
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() + $data['line-offset'],
            'namespace' => '',
            'class'     => GlobalMagic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_MagicClassAttribute($reflector, $data)
    {
        $provider = new Provider();

        $reflection = $reflector(Magic::class);
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() + $data['line-offset'],
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_MagicClassConstantAttribute($reflector, $data)
    {
        $provider = new Provider();

        $reflection = ($reflector(Magic::class))->getReflectionConstant('C');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getDeclaringClass()->getStartLine() + $data['line-offset'] + 3,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_MagicPropertyAttribute($reflector, $data)
    {
        $provider = new Provider();

        $reflection = ($reflector(Magic::class))->getProperty('p');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getDeclaringClass()->getStartLine() + $data['line-offset'] + 6,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => '',
            'function'  => '',
        ], $attributes[0]->getArguments());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_MagicMethodAttribute($reflector, $data)
    {
        $provider = new Provider();

        $reflection = ($reflector(Magic::class))->getMethod('m');
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getDeclaringClass()->getStartLine() + $data['line-offset'] + 9,
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => Magic::class,
            'trait'     => '',
            'method'    => Magic::class . '::m',
            'function'  => 'm',
        ], $attributes[0]->getArguments());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_MagicMethodParameterAttribute($reflector, $data)
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $reflection = ($reflector(Magic::class))->getMethod('m')->getParameters()[$n];
            $attributes = $provider->getAttributes($reflection);
            $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
            $this->assertEquals([
                'dir'       => dirname(self::$stubfile),
                'file'      => self::$stubfile,
                'line'      => $reflection->getDeclaringClass()->getStartLine() + $data['line-offset'] + 11 + ($n * 2),
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

    /**
     * @dataProvider provideFunctionReflector
     */
    function test_MagicFunctionAttribute($reflector, $data)
    {
        $provider = new Provider();

        $reflection = ($reflector(__NAMESPACE__ . '\\stub\\magic'));
        $attributes = $provider->getAttributes($reflection);
        $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
        $this->assertEquals([
            'dir'       => dirname(self::$stubfile),
            'file'      => self::$stubfile,
            'line'      => $reflection->getStartLine() + $data['line-offset'],
            'namespace' => __NAMESPACE__ . '\\stub',
            'class'     => '',
            'trait'     => '',
            'method'    => __NAMESPACE__ . '\\stub\\magic',
            'function'  => __NAMESPACE__ . '\\stub\\magic',
        ], $attributes[0]->getArguments());
    }

    /**
     * @dataProvider provideFunctionReflector
     */
    function test_MagicFunctionParameterAttribute($reflector, $data)
    {
        $provider = new Provider();

        foreach ([0, 1] as $n) {
            $reflection = ($reflector(__NAMESPACE__ . '\\stub\\magic'))->getParameters()[$n];
            $attributes = $provider->getAttributes($reflection);
            $this->assertEquals(MagicAttribute::class, $attributes[0]->getName());
            $this->assertEquals([
                'dir'       => dirname(self::$stubfile),
                'file'      => self::$stubfile,
                'line'      => $reflection->getDeclaringFunction()->getStartLine() + $data['line-offset'] + 2 + ($n * 2),
                'namespace' => __NAMESPACE__ . '\\stub',
                'class'     => '',
                'trait'     => '',
                'method'    => __NAMESPACE__ . '\\stub\\magic',
                'function'  => __NAMESPACE__ . '\\stub\\magic',
            ], $attributes[0]->getArguments());
        }
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_misc($reflector, $data)
    {
        $provider = new Provider();

        # for multiple const
        $attribute = $provider->getAttributes(($reflector(Multiple::class))->getReflectionConstant('C'))[0];
        $this->assertEquals(ClassConstantAttribute::class, $attribute->getName());
        $this->assertEquals(['constantM'], $attribute->getArguments());
        $attribute = $provider->getAttributes(($reflector(Multiple::class))->getReflectionConstant('C1'))[0];
        $this->assertEquals(ClassConstantAttribute::class, $attribute->getName());
        $this->assertEquals(['constantM12'], $attribute->getArguments());
        $attribute = $provider->getAttributes(($reflector(Multiple::class))->getReflectionConstant('C2'))[0];
        $this->assertEquals(ClassConstantAttribute::class, $attribute->getName());
        $this->assertEquals(['constantM12'], $attribute->getArguments());

        # for multiple property
        $attribute = $provider->getAttributes(($reflector(Multiple::class))->getProperty('p'))[0];
        $this->assertEquals(PropertyAttribute::class, $attribute->getName());
        $this->assertEquals(['propertyM'], $attribute->getArguments());
        $attribute = $provider->getAttributes(($reflector(Multiple::class))->getProperty('p1'))[0];
        $this->assertEquals(PropertyAttribute::class, $attribute->getName());
        $this->assertEquals(['propertyM12'], $attribute->getArguments());
        $attribute = $provider->getAttributes(($reflector(Multiple::class))->getProperty('p2'))[0];
        $this->assertEquals(PropertyAttribute::class, $attribute->getName());
        $this->assertEquals(['propertyM12'], $attribute->getArguments());

        # for literal/expression
        $reflection = $reflector(Misc::class);
        $this->assertEquals([
            'builtin'    => [null, false, true],
            'const'      => [M_PI, ArrayObject::ARRAY_AS_PROPS, ArrayObject::class],
            'magic'      => [Misc::class, $reflection->getStartLine() + $data['line-offset']],
            'expression' => ['hello' . 'world', 3.14 * 2, 1 << 8],
            'nestarray'  => [
                'x' => [
                    'y' => [
                        'z' => ['xyz'],
                    ],
                ],
            ],
        ], $provider->getAttributes($reflection)[0]->getArguments());
    }
}
