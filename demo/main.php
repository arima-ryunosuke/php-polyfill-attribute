<?php

namespace demo;

use Attribute;
use ReflectionClass;
use ryunosuke\polyfill\attribute\Provider;

require __DIR__ . '/../vendor/autoload.php';

#[Attribute(Attribute::TARGET_ALL)]
class ExampleAttribute
{
    const VALUE = 'value';

    public $args;

    public function __construct($a = 1, $b = 2, $c = 3, ...$z)
    {
        $this->args = func_get_args();
    }
}

trait ExampleTrait
{
    #[ExampleAttribute('this is trait1')]
    function trait1()
    {
    }

    #[ExampleAttribute('this is trait2')]
    function trait2()
    {
    }
}

#[ExampleAttribute('this is class attribute')]
class ExampleClass
{
    use ExampleTrait {
        trait2 as trait9;
    }

    #[ExampleAttribute('this is variadic arguments', 1, 2, 3)]
    const CONST = 123;

    #[ExampleAttribute('this is named arguments', c: 'C', b: 'B')]
    private $property = 123;

    #[ExampleAttribute('this is literals', 100 + 200, ExampleAttribute::VALUE, __CLASS__)]
    function method(
        #[ExampleAttribute('this is nested array', ['x' => ['y' => ['z' => [__FILE__, __LINE__]]]])]
        $p
    ) {
    }
}

function getReflectionAttributes($reflection)
{
    if (version_compare(PHP_VERSION, 8) >= 0) {
        return $reflection->getAttributes();
    }
    else {
        return (new Provider())->getAttributes($reflection);
    }
}

$exampleReflection = new ReflectionClass(ExampleClass::class);

$results = [
    'simple'   => getReflectionAttributes($exampleReflection)[0]->newInstance()->args,
    'variadic' => getReflectionAttributes($exampleReflection->getReflectionConstant('CONST'))[0]->newInstance()->args,
    'named'    => getReflectionAttributes($exampleReflection->getProperty('property'))[0]->newInstance()->args,
    'literal'  => getReflectionAttributes($exampleReflection->getMethod('method'))[0]->newInstance()->args,
    'array'    => getReflectionAttributes($exampleReflection->getMethod('method')->getParameters()[0])[0]->newInstance()->args,
    'trait'    => getReflectionAttributes($exampleReflection->getMethod('trait1'))[0]->getArguments(),
    'alias'    => getReflectionAttributes($exampleReflection->getMethod('trait9'))[0]->getArguments(),
];
echo json_encode($results, JSON_PRETTY_PRINT);
