<?php
/** @noinspection PhpLanguageLevelInspection */
namespace ryunosuke\Test\polyfill\attribute;

use Attribute;
use PhpParser\Node\Expr;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionExtension;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionParameter;
use ReflectionProperty;
use ryunosuke\polyfill\attribute\Reflection;
use ryunosuke\Test\polyfill\attribute\stub\Concrete;
use ryunosuke\Test\polyfill\attribute\stub\ConcreteTrait;

class ReflectionTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_ReflectionOf()
    {
        $this->assertInstanceOf(ReflectionFunction::class, Reflection::reflectionOf(function () { }));

        $this->assertInstanceOf(ReflectionObject::class, Reflection::reflectionOf(new class { }));
        $this->assertInstanceOf(ReflectionObject::class, Reflection::reflectionOf(new Concrete()));
        $this->assertInstanceOf(ReflectionClass::class, Reflection::reflectionOf(Concrete::class));

        $this->assertInstanceOf(ReflectionClassConstant::class, Reflection::reflectionOf([new Concrete(), 'C']));
        $this->assertInstanceOf(ReflectionClassConstant::class, Reflection::reflectionOf([Concrete::class, 'C']));
        $this->assertInstanceOf(ReflectionClassConstant::class, Reflection::reflectionOf(Concrete::class . '::C'));

        $this->assertInstanceOf(ReflectionProperty::class, Reflection::reflectionOf([new Concrete(), '$p']));
        $this->assertInstanceOf(ReflectionProperty::class, Reflection::reflectionOf([Concrete::class, '$p']));
        $this->assertInstanceOf(ReflectionProperty::class, Reflection::reflectionOf(Concrete::class . '::$p'));

        $this->assertInstanceOf(ReflectionMethod::class, Reflection::reflectionOf([new Concrete(), 'm']));
        $this->assertInstanceOf(ReflectionMethod::class, Reflection::reflectionOf([Concrete::class, 'm']));
        $this->assertInstanceOf(ReflectionMethod::class, Reflection::reflectionOf(Concrete::class . '::m'));

        $this->assertInstanceOf(ReflectionParameter::class, Reflection::reflectionOf([new Concrete(), 'm', 0]));
        $this->assertInstanceOf(ReflectionParameter::class, Reflection::reflectionOf([Concrete::class, 'm', 'p2']));

        $this->assertInstanceOf(ReflectionFunction::class, Reflection::reflectionOf(__NAMESPACE__ . '\\stub\\concrete'));

        $this->assertException('is not supported', fn() => Reflection::reflectionOf('undefined'));
        $this->assertException('is not supported', fn() => Reflection::reflectionOf([Concrete::class, 'undefined']));
        $this->assertException('is not supported', fn() => Reflection::reflectionOf(strtolower(Concrete::class)));
        $this->assertException('is not supported', fn() => Reflection::reflectionOf(strtolower(Concrete::class) . '::C'));
        $this->assertException('is not supported', fn() => Reflection::reflectionOf([strtolower(Concrete::class), 'C']));
    }

    /** @noinspection PhpUndefinedClassInspection */
    function test_ReflectionObject()
    {
        $original = new ReflectionObject(new #[Hoge]
        class {
        });
        $reflection = new Reflection($original);
        $this->assertStringContainsString('anonymous', Reflection::getId($original));
        $this->assertEquals(__FILE__, $reflection->getFileName());
        $this->assertStringContainsString('anonymous', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_CLASS, $reflection->getAttributeTarget());
    }

    /** @noinspection PhpUndefinedClassInspection */
    function test_ReflectionClosure()
    {
        $original = new ReflectionFunction(#[Hoge]
        function () {
        });
        $reflection = new Reflection($original);
        $this->assertEquals(__FILE__ . '@' . (__LINE__ - 3) . '-' . (__LINE__ - 2) . '()', Reflection::getId($original));
        $this->assertEquals(__FILE__, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_METHOD, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_ReflectionClass($reflector)
    {
        $original = $reflector(Concrete::class);
        $reflection = new Reflection($original);
        $this->assertEquals(Concrete::class, Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_CLASS, $reflection->getAttributeTarget());

        $original = $reflector(ConcreteTrait::class);
        $reflection = new Reflection($original);
        $this->assertEquals(ConcreteTrait::class, Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_CLASS, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_ReflectionClassConstant($reflector)
    {
        $original = $reflector(Concrete::class)->getReflectionConstant('C');
        $reflection = new Reflection($original);
        $this->assertEquals(Concrete::class . '::C', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_CLASS_CONSTANT, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_ReflectionProperty($reflector)
    {
        $original = $reflector(Concrete::class)->getProperty('p');
        $reflection = new Reflection($original);
        $this->assertEquals(Concrete::class . '::$p', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_PROPERTY, $reflection->getAttributeTarget());

        $original = $reflector(ConcreteTrait::class)->getProperty('p');
        $reflection = new Reflection($original);
        $this->assertEquals(ConcreteTrait::class . '::$p', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_PROPERTY, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_ReflectionMethod($reflector)
    {
        $original = $reflector(Concrete::class)->getMethod('m');
        $reflection = new Reflection($original);
        $this->assertEquals(Concrete::class . '::m()', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_METHOD, $reflection->getAttributeTarget());

        $original = $reflector(ConcreteTrait::class)->getMethod('m');
        $reflection = new Reflection($original);
        $this->assertEquals(ConcreteTrait::class . '::m()', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_METHOD, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideClassReflector
     */
    function test_ReflectionMethodParameter($reflector)
    {
        $original = $reflector(Concrete::class)->getMethod('m')->getParameters()[0];
        $reflection = new Reflection($original);
        $this->assertEquals(Concrete::class . '::m()#0', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_PARAMETER, $reflection->getAttributeTarget());

        $original = $reflector(ConcreteTrait::class)->getMethod('m')->getParameters()[0];
        $reflection = new Reflection($original);
        $this->assertEquals(ConcreteTrait::class . '::m()#0', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_PARAMETER, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideFunctionReflector
     */
    function test_ReflectionFunction($reflector)
    {
        $original = $reflector(__NAMESPACE__ . '\\stub\\concrete');
        $reflection = new Reflection($original);
        $this->assertEquals(__NAMESPACE__ . '\\stub\\concrete()', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_FUNCTION, $reflection->getAttributeTarget());
    }

    /**
     * @dataProvider provideFunctionReflector
     */
    function test_ReflectionFunctionParameter($reflector)
    {
        $original = $reflector(__NAMESPACE__ . '\\stub\\concrete')->getParameters()[0];
        $reflection = new Reflection($original);
        $this->assertEquals(__NAMESPACE__ . '\\stub\\concrete()#0', Reflection::getId($original));
        $this->assertEquals(self::$stubfile, $reflection->getFileName());
        $this->assertEquals('', $reflection->getAnonymousClassName());
        $this->assertEquals(Attribute::TARGET_PARAMETER, $reflection->getAttributeTarget());
    }

    function test_resolveExprValue()
    {
        $reflection = new Reflection((new ReflectionClass($this)));
        $this->assertException('Expression of type dummy cannot be evaluated', fn() => $reflection->resolveExprValue(new class extends Expr {
            public function getType(): string
            {
                return 'dummy';
            }

            public function getSubNodeNames(): array
            {
                return [];
            }
        }));
    }

    function test_notSupported()
    {
        $this->assertException('is not supported', function () {
            return new Reflection(new ReflectionExtension('tokenizer'));
        });
    }
}
