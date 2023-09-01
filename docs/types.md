:::{php:namespace} Atk4\Data
:::

# Data Types

ATK Data framework implements a consistent and extensible type system with the
following goals:

## Type specification

Mechanism to find corresponding class configuration based on selected type.

Specifying one of supported types will ensure that your field format is
recognized universally, can be stored, loaded, presented to user through UI
inside a Table or Form and can be exported through RestAPI:

```
$this->addField('is_vip', ['type' => 'boolean']);
```

We also allow use of custom Field implementation:

```
$this->addField('encrypted_password', new \Atk4\Data\Field\PasswordField());
```

A properly implemented type will still be able to offer some means to present
it in human-readable format, however in some cases, if you plan on using ATK UI,
you would have to create a custom decorators/FormField to properly read and
present your type value. See {php:attr}`Field::$ui`.

## Persistence mechanics and Serialization

All type values can be specified as primitives. For example `DateTime` object
class is associated with the `type=time` will be converted into string with
default format of "21:43:05".

Types that cannot be converted into primitive, there exist a process of "serialization",
which can use JSON or standard serialize() method to store object inside
incompatible database/persistence.

Serialization abilities allow us to get rid of many arbitrary types such as "array_json"
and simply use this:

```
$model->addField('selection', ['type' => 'json']);
```

## Field configuration

Fields can be further configured. For numeric fields it's possible to provide
precision. For instance, when user specifies `'type' => 'atk4_money'` it is represented
as `['Number', 'precision' => 2, 'prefix' => '€']`

Not only this allows us make a flexible and re-usable functionality for fields,
but also allows for an easy way to override:

```
$model->addField('salary', ['type' => 'atk4_money', 'precision' => 4']);
```

Although some configuration of the field may appear irrelevant (prefix/postfix)
to operations with data from inside PHP, those properties can be used by
ATK UI or data export routines to properly input or display values.

## Typecasting

ATK Data uses PHP native types and classes. For example, 'time' type is using
DateTime object.

When storing or displaying a type-casting takes place which will format the
value accordingly. Type-casting can be persistence-specific, for instance,
when storing "datetime" into SQL, the ISO format will be used, but when displayed
to the user a regional format is used instead.

## Supported Types

ATK Data supports the following types:

- string
- boolean
- integer
- float
- atk4_money
- date ({php:class}`\DateTime`)
- datetime ({php:class}`\DateTime`)
- time ({php:class}`\DateTime`)
