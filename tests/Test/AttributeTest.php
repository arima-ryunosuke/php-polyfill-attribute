<?php
namespace ryunosuke\Test;

use Attribute;
use ReflectionClass;
use ryunosuke\polyfill\attribute\Provider;

class AttributeTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_flags()
    {
        $attribute = new Attribute(255);
        $this->assertEquals(255, $attribute->flags);
    }

    function test_target()
    {
        // Attribute class is attributed Attribute::class

        $provider = new Provider();
        $attributes = $provider->getAttributes(new ReflectionClass(Attribute::class));

        $this->assertCount(1, $attributes);
        $this->assertEquals(Attribute::class, $attributes[0]->getName());
        // builtin Attribute is not specified TARGET_CLASS
        if (version_compare(PHP_VERSION, 8) < 0) {
            $this->assertEquals([Attribute::TARGET_CLASS], $attributes[0]->getArguments());
        }
        $this->assertEquals(Attribute::TARGET_CLASS, $attributes[0]->getTarget());
    }
}
