PHP Attribute Polyfill
====

## Description

This package provides attribute feature of php8 and later.

## Install

```json
{
    "require": {
        "ryunosuke/polyfill-attribute": "*"
    }
}
```

## Demo

```sh
# Below are the same results
php74 demo/main.php
php80 demo/main.php
```

## Usage

The `Provider` class provides a method that returns a ReflectionAttribute.
This works the same way in php7/8.

## Notice

- Performance is low
- Attributes are cached per identifier if a PSR16 is specified
- Abstract syntax tree is cached per filename
- There are minute differences in `__CLASS__` of anonymous classes
- Not support continuous line attribute (e.g. `#[Attribute] private $property`)
- Attribute::$target constraint is a runtime error (in php8 it is compile time)

## Release

`@api` method Versioning follows semantic versioning.

- https://semver.org
  - major: change specifications (BC break)
  - minor: add feature (no BC break)
  - patch: fix bug (no BC break)

### 2.0.0

- [feature] delete utility getter
  - use ryunosuke/utility-attribute (https://github.com/arima-ryunosuke/php-utility-attribute)
- [feature] deprecated No cache setting

### 1.2.0

- [feature] support BetterReflection attribute
- [feature] support both BetterReflection 4/5

### 1.1.0

- [feature][Provider] add cache per instance

### 1.0.0

- publish

## License

MIT
