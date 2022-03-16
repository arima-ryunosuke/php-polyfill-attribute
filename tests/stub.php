<?php
namespace {

    use ryunosuke\Test\polyfill\attribute\Attributes\MagicAttribute;

    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
    class GlobalMagic
    {
    }
}

namespace ryunosuke\Test\polyfill\attribute\Attributes {

    use Attribute;

    #[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
    class ClassAttribute
    {
        public $arg;

        public function __construct($arg)
        {
            $this->arg = $arg;
        }
    }

    #[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
    class ClassAttributeSub extends ClassAttribute
    {
    }

    #[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
    class ClassConstantAttribute
    {
        public $arg;

        public function __construct($arg)
        {
            $this->arg = $arg;
        }
    }

    #[Attribute(Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
    class FunctionAttribute
    {
        public $arg;

        public function __construct($arg)
        {
            $this->arg = $arg;
        }
    }

    #[Attribute]
    class MagicAttribute
    {
        public $args;

        public function __construct(...$args)
        {
            $this->args = $args;
        }
    }

    #[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
    class MethodAttribute
    {
        public $arg;

        public function __construct($arg)
        {
            $this->arg = $arg;
        }
    }

    #[Attribute]
    class NoConstructorAttribute
    {
    }

    #[Attribute(Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
    class ParameterAttribute
    {
        public $arg;

        public function __construct($arg)
        {
            $this->arg = $arg;
        }
    }

    #[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
    class PropertyAttribute
    {
        public $arg;

        public function __construct($arg)
        {
            $this->arg = $arg;
        }
    }

    #[Attribute(Attribute::TARGET_METHOD)]
    class TestAttribute
    {
        public $args;

        public function __construct($a = 1, $b = 2, $c = 3, ...$z)
        {
            $this->args = compact('a', 'b', 'c', 'z');
        }
    }

    #[Attribute(Attribute::TARGET_ALL & ~Attribute::TARGET_METHOD)]
    class NoMethodAttribute
    {
    }
}

namespace ryunosuke\Test\polyfill\attribute\stub {

    use ArrayObject;
    use ryunosuke\Test\polyfill\attribute\Attributes\ClassAttribute;
    use ryunosuke\Test\polyfill\attribute\Attributes\ClassAttributeSub;
    use ryunosuke\Test\polyfill\attribute\Attributes\ClassConstantAttribute;
    use ryunosuke\Test\polyfill\attribute\Attributes\FunctionAttribute;
    use ryunosuke\Test\polyfill\attribute\Attributes\MagicAttribute;
    use ryunosuke\Test\polyfill\attribute\Attributes\MethodAttribute;
    use ryunosuke\Test\polyfill\attribute\Attributes\ParameterAttribute;
    use ryunosuke\Test\polyfill\attribute\Attributes\PropertyAttribute;

    #[ClassAttribute('class1')]
    #[ClassAttribute('class2')]
    trait ConcreteTrait
    {
        #[PropertyAttribute('property1'), PropertyAttribute('property2')]
        private $p;

        #[MethodAttribute('method1'), MethodAttribute('method2')]
        function m(
            #[ParameterAttribute('parameter11'), ParameterAttribute('parameter12')]
            $p1,
            #[ParameterAttribute('parameter21')]
            #[ParameterAttribute('parameter22')]
            $p2
        ) {
        }
    }

    #[ClassAttribute('class1')]
    #[ClassAttribute('class2')]
    class Concrete
    {
        #[ClassConstantAttribute('constant1')]
        #[ClassConstantAttribute('constant2')]
        private const C = '';

        #[PropertyAttribute('property1'), PropertyAttribute('property2')]
        private $p;

        #[MethodAttribute('method1'), MethodAttribute('method2')]
        private function m(
            #[ParameterAttribute('parameter11'), ParameterAttribute('parameter12')]
            $p1,
            #[ParameterAttribute('parameter21')]
            #[ParameterAttribute('parameter22')]
            $p2
        ) {
        }
    }

    #[ClassAttribute('class3')]
    #[ClassAttributeSub('class4')]
    #[MagicAttribute('class5')]
    class SubConcrete extends Concrete
    {
    }

    class Multiple
    {
        #[ClassConstantAttribute('constantM')]
        const C = '';

        #[ClassConstantAttribute('constantM12')]
        const C1 = '', C2 = '';

        #[PropertyAttribute('propertyM')]
        private $p;

        #[PropertyAttribute('propertyM12')]
        private $p1, $p2;
    }

    #[FunctionAttribute('function1')]
    #[FunctionAttribute('function2')]
    function concrete(
        #[ParameterAttribute('parameter11'), ParameterAttribute('parameter12')]
        $p1,
        #[ParameterAttribute('parameter21')]
        #[ParameterAttribute('parameter22')]
        $p2
    ) {
    }

    function sinple_function() { }

    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
    class Magic
    {
        #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
        const C = '';

        #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
        private $p;

        #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
        function m(
            #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
            $p1,
            #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
            $p2
        ) {
        }

        static function magic_anonymous()
        {
            return new
            #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
            class {
                #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
                function m(
                    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
                    $p1,
                    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
                    $p2
                ) {
                }
            };
        }

        static function magic_closure()
        {
            return
                #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
                function (
                    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
                    $p1,
                    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
                    $p2
                ) {
                };
        }
    }

    #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
    function magic(
        #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
        $p1,
        #[MagicAttribute(dir: __DIR__, file: __FILE__, line: __LINE__, namespace: __NAMESPACE__, class: __CLASS__, trait: __TRAIT__, method: __METHOD__, function: __FUNCTION__)]
        $p2
    ) {
    }

    #[HogeAttribute(builtin: [null, false, true], const: [M_PI, ArrayObject::ARRAY_AS_PROPS, ArrayObject::class], magic: [__CLASS__, __LINE__], expression: ['hello' . 'world', 3.14 * 2, 1 << 8], nestarray: ['x' => ['y' => ['z' => ['xyz']]]])]
    class Misc
    {
    }
}
