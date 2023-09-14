:::{php:namespace} Atk4\Data
:::

(Typecasting)=

# Typecasting

Typecasting is evoked when you are attempting to save or load the record.
Unlike strict types and normalization, typecasting is a persistence-specific
operation. Here is the sequence and sample:

```
$m->addField('birthday', ['type' => 'date']);
// type has a number of pre-defined values. Using 'date'
// instructs AD that we will be using it for staring dates
// through 'DateTime' class.

$m->set('birthday', 'Jan 1 1960');
// If non-compatible value is provided, it will be converted
// into a proper date through Normalization process. After
// this line value of 'birthday' field will be DateTime.

$m->save();
// At this point typecasting converts the "DateTime" value
// into UTC date-time representation for SQL or "MongoDate"
// type if you're persisting with MongoDB. This does not affect
// value of a model field.
```

Typecasting is necessary to save the values inside the database and restore
them back just as they were before.

The purpose of a flexible typecasting system is to allow you to store your date
in a compatible format or even fine-tune it to match your database settings
(e.g. timezone) without affecting your domain code.

You must remember that type-casting is a two-way operation. If you are
introducing your own types, you will need to make sure they can be saved and
loaded correctly.

Some types such as `boolean` may support additional options like:

```
$m->addField('is_married', [
    'type' => 'boolean',
    'enum' => ['No', 'Yes'],
]);

$m->set('is_married', 'Yes'); // normalizes into true
$m->set('is_married', true); // better way because no need to normalize

$m->save(); // stores as "Yes" because of type-casting
```

## Value types

Any type can have a value of `null`:

```
$m->set('is_married', null);
if (!$m->get('is_married')) {
    // either null or false
}
```

If value is passed which is not compatible with field type, Agile Data will try
to normalize value:

```
$m->addField('age', ['type' => 'integer']);
$m->addField('name', ['type' => 'string']);

$m->set('age', '49.8');
$m->set('name', '    John');

echo $m->get('age'); // 49 - normalization cast value to integer
echo $m->get('name'); // 'John' - normalization trims value
```

### Undefined type

If you do not set type for a field, Agile Data will not normalize and type-cast
its value.

Because of the loose PHP types, you can encounter situations where undefined
type is changed from `'4'` to `4`. This change is still considered "dirty".

If you use numeric value with a type-less field, the response from SQL does
not distinguish between integers and strings, so your value will be stored as
"string" inside the model.

The same can be said about forms, which submit all their data through POST
request that has no types, so undefined type fields should work relatively
good with the standard setup of Agile Data + Agile Toolkit + SQL.

### Type of IDs

Many databases will allow you to use different types for ID fields.
In SQL the 'id' column will usually be "integer", but sometimes it can be of
a different type.

The same applies for references ($m->hasOne()).

### Supported types

- 'string' - for storing short strings, such as name of a person. Normalize will trim the value.
- 'text' - for storing long strings, suchas notes or description. Normalize will trim the value.
- 'boolean' - normalize will cast value to boolean.
- 'integer' - normalize will cast value to integer.
- 'atk4_money' - normalize will round value with 4 digits after dot.
- 'float' - normalize will cast value to float.
- 'date' - normalize will convert value to DateTime object.
- 'datetime' - normalize will convert value to DateTime object.
- 'time' - normalize will convert value to DateTime object.
- 'json' - no normalization by default
- 'object' - no normalization by default

### Types and UI

UI framework such as Agile Toolkit will typically rely on field type information
to properly present data for views (forms and tables) without you having to
explicitly specify the `ui` property.

## Serialization

Some types cannot be stored natively. For example, generic objects and arrays
have no native type in SQL database. This is where serialization feature is used.

Field may use serialization to further encode field value for the storage purpose:

```
$this->addField('private_key', [
    'type' => 'object',
    'system' => true,
]);
```

### Array and Object types

Some types may require serialization for some persistencies, for instance types
'json' and 'object' cannot be stored in SQL natively. `json` type can be used
to store these in JSON.

This is handy when mapping JSON data into native PHP structures.
