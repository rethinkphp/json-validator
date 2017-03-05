# Json Validator

A Json Validator that designed to be elegant and easy to use. 

## Motivation

Json Validation is a common task in automated API testing, Json-Schema is complex and not easy to use, so i
created this library to simplify the json validation process and made json validation more elegant and fun.

## Features

* Json Schema validation, useful for automated API testing
* Custom type support, it is possible to define your custom types and reuse it everywhere
* Nullable type support
* More is coming...


## Installation

You can install the latest version of json validator with the following command:

```bash
composer require  rethink/json-validator:dev-master 
```

## Documentation

### Types

By default, Json Validator shipped with seven kinds of built-in types:

- integer
- double
- boolean
- string
- number
- array
- object

Besides the built-in types, it is possible to define your custom type via `defineType()` method.

The following code snippets shows how we can define custom types through array or callable.

#### 1. Define a composite type 

```php
$validator->defineType('User', [
    'name' => 'string',
    'gender' => 'string',
    'age' => '?integer',
]);
```

This example defines a custom type named `User`, which have three properties. name and gender require be a
string, age requires be an integer but allows to be nullable.

#### 2. Define a list type

```php
$validator->defineType('UserCollection', ['User']);
```

This defines `UserCollection` to be an array of `User`. In order to define a list type, the definition of the type much 
contains only one element.


#### 3. Define a type in callable

```php
$validator->defineType('timestamp', function ($value) {
    if ((!is_string($value) && !is_numeric($value)) || strtotime($value) === false) {
        return false;
    }

    $date = date_parse($value);

    return checkdate($date['month'], $date['day'], $date['year']);
});
```

It is also possible to define a type using a callable, which is useful to perform some validation on the data. Such as 
the example above defined a timestamp type, that requires the data to be a valid datetime.

### Validate a Type

We can validate a type by the following two steps:

#### 1. Create a Validator instance

```php
use rethinkphp\jsv\Validator;

$validator = new Validator();
// $validator->defineType(...)  Add your custom type if necessary
```

#### 2. Preform the validation

```php
$matched = $validator->matches($data, 'User');
if ($matched) {
    // Validation passed
} else {
    $errors = $validator->getErrors();
}
```

This example will check whether the given `$data` matches the type `User`, if validation fails, we can get the error 
messages  through `getErrors()` method.


### Strict Mode

In some situations, we may want an object matches our type strictly, we can utilizing `strict mode` to achieve this,
the following is the example:

```php
$data = [
    'name' => 'Bob',
    'gender' => 'Male',
    'age' => 19,
    'phone' => null, // This property is unnecessary
];
$matched = $validator->matches($data, 'User', true); // strict mode is turned on
var_dump($matched); // false is returned
```


## Related Projects

* [Blink Framework](https://github.com/bixuehujin/blink) - A high performance web framework and application server in PHP. Edit



