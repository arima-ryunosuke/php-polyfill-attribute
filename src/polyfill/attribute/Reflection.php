<?php

namespace ryunosuke\polyfill\attribute;

use Attribute;
use Closure;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\ErrorHandler;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use Reflector;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflection\ReflectionClassConstant;
use Roave\BetterReflection\Reflection\ReflectionFunction;
use Roave\BetterReflection\Reflection\ReflectionFunctionAbstract;
use Roave\BetterReflection\Reflection\ReflectionMethod;
use Roave\BetterReflection\Reflection\ReflectionParameter;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\Reflector\FunctionReflector;
use Roave\BetterReflection\SourceLocator\Ast\Locator as AstLocator;
use Roave\BetterReflection\SourceLocator\Ast\Parser\MemoizingParser;
use Roave\BetterReflection\SourceLocator\Located\LocatedSource;
use Roave\BetterReflection\SourceLocator\SourceStubber\AggregateSourceStubber;
use Roave\BetterReflection\SourceLocator\SourceStubber\PhpStormStubsSourceStubber;
use Roave\BetterReflection\SourceLocator\SourceStubber\ReflectionSourceStubber;
use Roave\BetterReflection\SourceLocator\SourceStubber\SourceStubber;
use Roave\BetterReflection\SourceLocator\Type\AggregateSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\AutoloadSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\EvaledCodeSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\MemoizingSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\PhpInternalSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\SourceLocator;
use UnexpectedValueException;

/**
 * @internal
 */
class Reflection
{
    private static $configuration;

    private static function getConfiguration()
    {
        /**
         * @see \Roave\BetterReflection\BetterReflection
         */
        return self::$configuration ??= new class () {
            private SourceLocator     $sourceLocator;
            private ClassReflector    $classReflector;
            private FunctionReflector $functionReflector;
            private Parser            $phpParser;
            private AstLocator        $astLocator;
            private SourceStubber     $sourceStubber;

            public function sourceLocator(): SourceLocator
            {
                return $this->sourceLocator ??= new MemoizingSourceLocator(
                    new AggregateSourceLocator([
                        new PhpInternalSourceLocator($this->astLocator(), $this->sourceStubber()),
                        new EvaledCodeSourceLocator($this->astLocator(), $this->sourceStubber()),
                        new AutoloadSourceLocator($this->astLocator(), $this->phpParser()),
                    ])
                );
            }

            public function classReflector(): ClassReflector
            {
                return $this->classReflector ??= new ClassReflector($this->sourceLocator());
            }

            public function functionReflector(): FunctionReflector
            {
                return $this->functionReflector ??= new FunctionReflector($this->sourceLocator(), $this->classReflector());
            }

            public function phpParser(): Parser
            {
                return $this->phpParser ??= new MemoizingParser(new class(
                    new Emulative(['usedAttributes' => ['comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos']])
                ) extends Parser\Php7 {
                    public function parse(string $code, ErrorHandler $errorHandler = null)
                    {
                        $traverser = new NodeTraverser();
                        $traverser->addVisitor(new NameResolver());
                        $traverser->addVisitor(new ParentConnectingVisitor());
                        return $traverser->traverse(parent::parse($code, $errorHandler));
                    }
                });
            }

            public function astLocator(): AstLocator
            {
                return $this->astLocator ??= new AstLocator($this->phpParser(), fn() => $this->functionReflector());
            }

            public function sourceStubber(): SourceStubber
            {
                return $this->sourceStubber ??= new AggregateSourceStubber(
                    new PhpStormStubsSourceStubber($this->phpParser()),
                    new ReflectionSourceStubber()
                );
            }
        };
    }

    public static function reflectionOf($target): Reflector
    {
        assert(is_string($target) || is_array($target) || is_object($target));

        if (is_string($target) && strpos($target, '::') !== false) {
            $target = explode('::', $target);
        }

        if ($target instanceof Closure) {
            return new \ReflectionFunction($target);
        }
        if (is_object($target)) {
            return new \ReflectionObject($target);
        }
        if (is_array($target)) {
            [$class, $member] = $target + [1 => ''];
            /** @var ReflectionClass $ref */
            $ref = self::reflectionOf($class);
            if (($member[0] ?? '') === '$' && $ref->hasProperty($property = substr($member, 1))) {
                return new \ReflectionProperty($class, $property);
            }
            if ($ref->hasMethod($member)) {
                if (isset($target[2])) {
                    return new \ReflectionParameter([$class, $member], $target[2]);
                }
                return new \ReflectionMethod($class, $member);
            }
            if ($ref->hasConstant($member)) {
                return new \ReflectionClassConstant($class, $member);
            }
            throw new UnexpectedValueException(sprintf('%s is not supported', json_encode($target)));
        }
        if ((class_exists($target) || trait_exists($target) || interface_exists($target)) && ($ref = new \ReflectionClass($target))->getName() === $target) {
            return $ref;
        }
        if (function_exists($target) && ($ref = new \ReflectionFunction($target))->getName() === $target) {
            return $ref;
        }
        throw new UnexpectedValueException(sprintf('%s is not supported', json_encode($target)));
    }

    public static function getId(\Reflector $reflector): string
    {
        return ($_ = function ($reflector) use (&$_) {
            switch (true) {
                case $reflector instanceof \ReflectionClass:
                    return $reflector->getName();

                case $reflector instanceof \ReflectionClassConstant:
                    return $_($reflector->getDeclaringClass()) . '::' . $reflector->getName();

                case $reflector instanceof \ReflectionProperty:
                    return $_($reflector->getDeclaringClass()) . '::$' . $reflector->getName();

                case $reflector instanceof \ReflectionMethod:
                    return $_($reflector->getDeclaringClass()) . '::' . $reflector->getName() . '()';

                case $reflector instanceof \ReflectionFunction:
                    // closures on the same line are considered identical
                    if ($reflector->isClosure()) {
                        return $reflector->getFileName() . '@' . $reflector->getStartLine() . '-' . $reflector->getEndLine() . '()';
                    }
                    return $reflector->getName() . '()';

                case $reflector instanceof \ReflectionParameter:
                    return $_($reflector->getDeclaringFunction()) . '#' . $reflector->getPosition();
            }
        })($reflector);
    }

    /** @var object|ReflectionClass|ReflectionClassConstant|ReflectionProperty|ReflectionFunctionAbstract|ReflectionParameter */
    private object $reflector;

    public function __construct(\Reflector $reflector)
    {
        $this->reflector = ($_ = function ($reflector) use (&$_) {
            switch (true) {
                case $reflector instanceof \ReflectionClass:
                    if ($reflector->isAnonymous()) {
                        // @fixme ReflectionClass::createFromInstance();
                        $locatedSource = new LocatedSource(file_get_contents($reflector->getFileName()), $reflector->getFileName());

                        /** @var Node\Stmt\Class_ $node */
                        $nodes = self::getConfiguration()->phpParser()->parse($locatedSource->getSource());
                        $node = (new NodeFinder())->findFirst($nodes, function (?Node $node) use ($reflector) {
                            if ($node !== null && $node instanceof Node\Stmt\Class_ && $node->name === null) {
                                $startLine = ($node->attrGroups) ? end($node->attrGroups)->getEndLine() + 1 : $node->getStartLine();
                                if ($startLine === $reflector->getStartLine() && $node->getEndLine() === $reflector->getEndLine()) {
                                    return true;
                                }
                            }
                        });
                        return ReflectionClass::createFromNode(self::getConfiguration()->classReflector(), $node, $locatedSource);
                    }
                    return self::getConfiguration()->classReflector()->reflect($reflector->getName());

                case $reflector instanceof \ReflectionClassConstant:
                    return $_($reflector->getDeclaringClass())->getReflectionConstant($reflector->getName());

                case $reflector instanceof \ReflectionProperty:
                    return $_($reflector->getDeclaringClass())->getProperty($reflector->getName());

                case $reflector instanceof \ReflectionMethod:
                case $reflector instanceof \ReflectionFunction:
                    if ($reflector->isClosure()) {
                        // @fixme return ReflectionFunction::createFromClosure($reflector->getClosure());
                        $locatedSource = new LocatedSource(file_get_contents($reflector->getFileName()), $reflector->getFileName());

                        /** @var Node\Stmt\Function_ $node */
                        $nodes = self::getConfiguration()->phpParser()->parse($locatedSource->getSource());
                        $node = (new NodeFinder())->findFirst($nodes, function (?Node $node) use ($reflector) {
                            if ($node !== null && ($node instanceof Node\Expr\Closure || $node instanceof Node\Expr\ArrowFunction)) {
                                $startLine = ($node->attrGroups) ? end($node->attrGroups)->getEndLine() + 1 : $node->getStartLine();
                                if ($startLine === $reflector->getStartLine() && $node->getEndLine() === $reflector->getEndLine()) {
                                    return true;
                                }
                            }
                        });
                        return ReflectionFunction::createFromNode(self::getConfiguration()->functionReflector(), $node, $locatedSource);
                    }
                    if ($reflector instanceof \ReflectionMethod) {
                        return $_($reflector->getDeclaringClass())->getMethod($reflector->getName());
                    }
                    return self::getConfiguration()->functionReflector()->reflect($reflector->getName());

                case $reflector instanceof \ReflectionParameter:
                    return $_($reflector->getDeclaringFunction())->getParameter($reflector->getName());
            }

            throw new UnexpectedValueException(sprintf('%s is not supported', get_class($reflector)));
        })($reflector);
    }

    public function getAttributeArray(): array
    {
        $node = $this->reflector->getAst();

        $attributes = [];
        foreach ($node->attrGroups ?? [] as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $arguments = [];
                foreach ($attr->args as $arg) {
                    if ($arg->name === null) {
                        $arguments[] = $this->resolveExprValue($arg->value);
                    }
                    else {
                        $arguments[(string) $arg->name] = $this->resolveExprValue($arg->value);
                    }
                }
                $attributes[] = [
                    'name'      => (string) $attr->name,
                    'arguments' => $arguments,
                    'target'    => $this->getAttributeTarget(),
                ];
            }
        }

        $counts = array_count_values(array_column($attributes, 'name'));
        $attributes = array_map(function ($attribute) use ($counts) {
            $attribute['repeated'] = $counts[$attribute['name']] > 1;
            return $attribute;
        }, $attributes);

        return $attributes;
    }

    public function getFileName(): string
    {
        return ($_ = function ($reflector) use (&$_) {
            switch (true) {
                case $reflector instanceof ReflectionClass:
                case $reflector instanceof ReflectionFunction:
                case $reflector instanceof ReflectionMethod:
                    return realpath($reflector->getFileName());

                case $reflector instanceof ReflectionClassConstant:
                case $reflector instanceof ReflectionProperty:
                    return $_($reflector->getDeclaringClass());

                case $reflector instanceof ReflectionParameter:
                    return $_($reflector->getDeclaringFunction());
            }
        })($this->reflector);
    }

    public function getAnonymousClassName(): string
    {
        return ($_ = function ($reflector) use (&$_) {
            switch (true) {
                case $reflector instanceof ReflectionClass:
                    return $reflector->isAnonymous() ? $reflector->getName() : '';

                case $reflector instanceof ReflectionClassConstant:
                case $reflector instanceof ReflectionProperty:
                case $reflector instanceof ReflectionMethod:
                    return $_($reflector->getDeclaringClass());

                case $reflector instanceof ReflectionParameter:
                    return $_($reflector->getDeclaringFunction());

                case $reflector instanceof ReflectionFunction:
                    return '';
            }
        })($this->reflector);
    }

    public function getAttributeTarget(): int
    {
        return (function ($reflector) {
            switch (true) {
                case $reflector instanceof ReflectionClass:
                    return Attribute::TARGET_CLASS;

                case $reflector instanceof ReflectionClassConstant:
                    return Attribute::TARGET_CLASS_CONSTANT;

                case $reflector instanceof ReflectionProperty:
                    return Attribute::TARGET_PROPERTY;

                case $reflector instanceof ReflectionMethod:
                    return Attribute::TARGET_METHOD;

                case $reflector instanceof ReflectionFunction:
                    return $reflector->isClosure() ? Attribute::TARGET_METHOD : Attribute::TARGET_FUNCTION;

                case $reflector instanceof ReflectionParameter:
                    return Attribute::TARGET_PARAMETER;
            }
        })($this->reflector);
    }

    public function resolveExprValue(Node\Expr $expr)
    {
        $closest = function (Node $node, Closure $condition): ?Node {
            while ($node) {
                if ($condition($node)) {
                    return $node;
                }
                $node = $node->getAttribute('parent');
            }
            return null;
        };
        $concat = function (string $separator, string ...$strings): string {
            return implode($separator, array_filter($strings, 'strlen'));
        };
        $evaluator = new ConstExprEvaluator(function (Node\Expr $expr) use ($closest, $concat) {
            if ($expr instanceof Node\Scalar\MagicConst\Namespace_) {
                $namespace = $closest($expr, fn(Node $node) => $node instanceof Node\Stmt\Namespace_);
                return (string) ($namespace->name ?? '');
            }
            if ($expr instanceof Node\Scalar\MagicConst\Class_) {
                $class = $closest($expr, fn(Node $node) => $node instanceof Node\Stmt\ClassLike);
                return (string) ($class->namespacedName ?? $this->getAnonymousClassName());
            }
            if ($expr instanceof Node\Scalar\MagicConst\Trait_) {
                $trait = $closest($expr, fn(Node $node) => $node instanceof Node\Stmt\Trait_);
                return (string) ($trait->namespacedName ?? '');
            }
            if ($expr instanceof Node\Scalar\MagicConst\Method) {
                $func = $closest($expr, fn(Node $node) => $node instanceof Node\FunctionLike);
                if ($func instanceof Node\Expr\Closure || $func instanceof Node\Expr\ArrowFunction) {
                    $namespace = $closest($expr, fn(Node $node) => $node instanceof Node\Stmt\Namespace_);
                    return $concat('\\', $namespace->name ?? '', '{closure}');
                }
                if ($func instanceof Node\Stmt\ClassMethod) {
                    $class = $closest($func, fn(Node $node) => $node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_);
                    return $concat('::', $class->namespacedName ?? $class->name ?? $this->getAnonymousClassName(), $func->name ?? '');
                }
                return (string) ($func->namespacedName ?? $func->name ?? '');
            }
            if ($expr instanceof Node\Scalar\MagicConst\Function_) {
                $func = $closest($expr, fn(Node $node) => $node instanceof Node\FunctionLike);
                if ($func instanceof Node\Expr\Closure || $func instanceof Node\Expr\ArrowFunction) {
                    $namespace = $closest($expr, fn(Node $node) => $node instanceof Node\Stmt\Namespace_);
                    return $concat('\\', $namespace->name ?? '', '{closure}');
                }
                return (string) ($func->namespacedName ?? $func->name ?? '');
            }
            if ($expr instanceof Node\Scalar\MagicConst\Dir) {
                return dirname($this->getFileName());
            }
            if ($expr instanceof Node\Scalar\MagicConst\File) {
                return $this->getFileName();
            }
            if ($expr instanceof Node\Scalar\MagicConst\Line) {
                return $expr->getLine();
            }

            if ($expr instanceof Node\Expr\ConstFetch) {
                return constant((string) $expr->name);
            }
            if ($expr instanceof Node\Expr\ClassConstFetch) {
                if ($expr->name->toLowerString() === 'class') {
                    return (string) $expr->class;
                }
                return constant($expr->class . '::' . $expr->name);
            }

            throw new ConstExprEvaluationException("Expression of type {$expr->getType()} cannot be evaluated");
        });
        return $evaluator->evaluateDirectly($expr);
    }
}
