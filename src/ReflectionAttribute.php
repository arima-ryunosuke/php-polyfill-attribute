<?php

if (!class_exists(ReflectionAttribute::class)) {
    class ReflectionAttribute implements Reflector
    {
        public const IS_INSTANCEOF = 2;

        private string $name;
        private array  $arguments;
        private bool   $repeated;
        private int    $target;

        private function __construct(string $name, array $arguments, bool $repeated, int $target)
        {
            $this->name = $name;
            $this->arguments = $arguments;
            $this->repeated = $repeated;
            $this->target = $target;
        }

        public function __clone()
        {
            throw new Error('Trying to clone an uncloneable object of class ReflectionAttribute');
        }

        public static function export()
        {
            throw new Error('Call to undefined method ReflectionAttribute::export()');
        }

        public function __toString(): string
        {
            throw new Error('Object of class ReflectionAttribute could not be converted to string');
        }

        public function getName(): string
        {
            return $this->name;
        }

        public function getArguments(): array
        {
            return $this->arguments;
        }

        public function isRepeated(): bool
        {
            return $this->repeated;
        }

        public function getTarget(): int
        {
            return $this->target;
        }

        public function newInstance(): object
        {
            $newNamedInstance = static function (ReflectionClass $reflectionClass, array $arguments) {
                $constructor = $reflectionClass->getConstructor();
                if (!$constructor && $arguments) {
                    throw new Error(sprintf('Attribute class %s does not have a constructor, cannot pass arguments', $reflectionClass->getName()));
                }

                $parammap = [];
                $defaults = [];
                foreach ($constructor ? $constructor->getParameters() : [] as $parameter) {
                    $parammap[$parameter->getName()] = $parameter->getPosition();
                    if ($parameter->isDefaultValueAvailable()) {
                        $defaults[$parameter->getPosition()] = $parameter->getDefaultValue();
                    }
                }

                $args = [];
                foreach ($arguments as $n => $argument) {
                    $args[is_string($n) ? $parammap[$n] : $n] = $argument;
                }

                $args += array_slice($defaults, 0, max(array_keys($args ?: [0])));
                ksort($args);

                return $reflectionClass->newInstanceArgs($args);
            };

            if (!class_exists($this->name)) {
                throw new Error(sprintf('Attribute class "%s" not found', $this->name));
            }

            $reflectionClass = new ReflectionClass($this->name);

            // this error check is meaningless in an production. but it's a heavy process a little, so look at 'zend.assertions'
            if (ini_get('zend.assertions') == 1) {
                static $provider = null;
                $attributeReflection = ($provider ??= new \ryunosuke\polyfill\attribute\Provider())->getAttribute($reflectionClass, Attribute::class, ReflectionAttribute::IS_INSTANCEOF);

                if ($attributeReflection === null) {
                    throw new Error(sprintf('Attempting to use non-attribute class "%s" as attribute', $this->name));
                }

                $attributeInstance = $newNamedInstance(new ReflectionClass($attributeReflection->getName()), $attributeReflection->getArguments());
                if (!($attributeInstance->flags & $this->target)) {
                    $map = [
                        Attribute::TARGET_CLASS          => 'class',
                        Attribute::TARGET_FUNCTION       => 'function',
                        Attribute::TARGET_METHOD         => 'method',
                        Attribute::TARGET_PROPERTY       => 'property',
                        Attribute::TARGET_CLASS_CONSTANT => 'class constant',
                        Attribute::TARGET_PARAMETER      => 'parameter',
                    ];
                    throw new Error(sprintf('Attribute "%s" cannot target %s (allowed targets: %s)',
                            $this->name,
                            $map[$this->target],
                            implode(', ', array_filter($map, fn($flag) => $attributeInstance->flags & $flag, ARRAY_FILTER_USE_KEY))
                        )
                    );
                }

                if (!($attributeInstance->flags & Attribute::IS_REPEATABLE) && $this->repeated) {
                    throw new Error(sprintf('Attribute "%s" must not be repeated', $this->name));
                }
            }

            return $newNamedInstance($reflectionClass, $this->arguments);
        }
    }
}
