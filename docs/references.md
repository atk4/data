:::{php:namespace} Atk4\Data
:::

(References)=

# References

:::{php:class} Model
:::

:::{php:method} ref($link, $details = [])
:::

Models can relate one to another. The logic of traversing references, however,
is slightly different to the traditional ORM implementation, because in Agile
Data traversing also imposes {ref}`conditions`

There are two basic types of references: hasOne() and hasMany(), but it's also
possible to add other reference types. The basic ones are really easy to
use:

```
$m = new Model_User($db, 'user');
$m->hasMany('Orders', ['model' => [Model_Order::class]]);
$m = $m->load(13);

$ordersForUser13 = $m->ref('Orders');
```

As mentioned - $ordersForUser13 will have it's DataSet automatically adjusted
so that you could only access orders for the user with ID=13. The following is
also possible:

```
$m = new Model_User($db, 'user');
$m->hasMany('Orders', ['model' => [Model_Order::class]]);
$m->addCondition('is_vip', true);

$ordersForVips = $m->ref('Orders');
$ordersForVips = $ordersForVips->loadAny();
```

Condition on the base model will be carried over to the orders and you will
only be able to access orders that belong to VIP users. The query for loading
order will look like this:

```sql
select * from order where user_id in (
    select id from user where is_vip = 1
) limit 1
```

Argument $defaults will be passed to the new model that will be used to create
referenced model. This will not work if you have specified reference as existing
model that has a persistence set. (See Reference::getModel())

## Persistence

Agile Data supports traversal between persistencies. The code above does not
explicitly assign database to Model_Order. But what if destination model does
not reside inside the same database?

You can specify it like this:

```
$m = new Model_User($dbArrayCache, 'user');
$m->hasMany('Orders', ['model' => [Model_Order::class, $dbSql]]);
$m->addCondition('is_vip', true);

$ordersForVips = $m->ref('Orders');
```

Now that a different databases are used, the queries can no longer be
joined so Agile Data will carry over list of IDs instead:

```
$ids = select id from user where is_vip = 1
select * from order where user_id in ($ids)
```

Since we are using `$dbArrayCache`, then field values will actually
be retrieved from memory.

:::{note}
This is not implemented as of 1.1.0, see https://github.com/atk4/data/issues/158
:::

## Safety and Performance

When using ref() on hasMany reference, it will always return a fresh clone of
the model. You can perform actions on the clone and next time you execute ref()
you will get a fresh copy.

If you are worried about performance you can keep 2 models in memory:

```
$order = new Order($db);
$client = $order->refModel('client_id');

foreach ($order as $o) {
    $c = $client->load($o->get('client_id'));
}
```

:::{warning}
This code is seriously flawed and is called "N+1 Problem".
Agile Data discourages you from using this and instead offers you many
other tools: field importing, model joins, field actions and refLink().
:::

## hasMany Reference

:::{php:method} hasMany($link, ['model' => $model])
:::

There are several ways how to link models with hasMany:

```
$m->hasMany('Orders', ['model' => [Model_Order::class]]); // using seed

$m->hasMany('Order', ['model' => function (Model $m, $r) { // using callback
    return new Model_Order();
}]);
```

### Dealing with many-to-many references

It is possible to perform reference through an 3rd party table:

```
$i = new Model_Invoice();
$p = new Model_Payment();

// table invoice_payment has 'invoice_id', 'payment_id' and 'amount_allocated'

$p
    ->join('invoice_payment.payment_id')
    ->addFields(['amount_allocated', 'invoice_id']);

$i->hasMany('Payments', ['model' => $p]);
```

Now you can fetch all the payments associated with the invoice through:

```
$paymentsForInvoice1 = $i->load(1)->ref('Payments');
```

### Dealing with NON-ID fields

Sometimes you have to use non-ID references. For example, we might have two models
describing list of currencies and for each currency we might have historic rates
available. Both models will relate through `currency.code = exchange.currency_code`:

```
$c = new Model_Currency();
$e = new Model_ExchangeRate();

$c->hasMany('Exchanges', ['model' => $e, 'theirField' => 'currency_code', 'ourField' => 'code']);

$c->addCondition('is_convertible', true);
$e = $c->ref('Exchanges');
```

This will produce the following query:

```sql
select * from exchange
where currency_code in
    (select code form currency where is_convertible = 1)
```

### Concatenating Fields

You may want to display want to list your related entities by concatenating. For example:

```
$user->hasMany('Tags', ['model' => [Tag::class]])
    ->addField('tags', ['concat' => ',', 'field' => 'name']);
```

This will create a new field for your user, `tags` which will contain all comma-separated
tag names.

### Add Aggregate Fields

Reference hasMany makes it a little simpler for you to define an aggregate fields:

```
$u = new Model_User($dbArrayCache, 'user');

$u->hasMany('Orders', ['model' => [Model_Order::class]])
    ->addField('amount', ['aggregate' => 'sum']);
```

It's important to define aggregation functions here. This will add another field
inside `$m` that will correspond to the sum of all the orders. Here is another
example:

```
$u->hasMany('PaidOrders', (new Model_Order())->addCondition('is_paid', true))
    ->addField('paid_amount', ['aggregate' => 'sum', 'field' => 'amount']);
```

You can also define multiple fields, although you must remember that this will
keep making your query bigger and bigger:

```
$invoice->hasMany('Invoice_Line', ['model' => [Model_Invoice_Line::class]])
    ->addFields([
        ['total_vat', 'aggregate' => 'sum'],
        ['total_net', 'aggregate' => 'sum'],
        ['total_gross', 'aggregate' => 'sum'],
    ]);
```

Imported fields will preserve format of the field they reference. In the example,
if 'Invoice_line' field total_vat has type `money` then it will also be used
for a sum.

You can also specify a type yourself:

```
->addField('paid_amount', ['aggregate' => 'sum', 'field' => 'amount', 'type' => 'atk4_money']);
```

Aggregate fields are always declared read-only, and if you try to
change them (`$m->set('paid_amount', 123);`), you will receive exception.

### Available Aggregation Functions

The mathematical aggregate `sum` will automatically
default to 0 if no respective rows were provided. The default SQL behaviour is to
return NULL, but this does go well with the cascading formulas:

```
coalesce(sum([field]), 0);
```

For other functions, such as `min`, `max`, `avg` and non mathematical aggregates such
as `group_concat` no zero-coalesce will be used. Expect that result could be zero or
null.

When you specify `'aggregate' => 'count'` field defaults to `*`.

### Aggregate Expressions

Sometimes you want to use a more complex formula, and you may do so by specifying
expression into 'aggregate':

```
->addField('len', ['expr' => 'sum(length([name]))']),
```

You can reference fields by using square brackets here. Also you may pass `args`
containing your optional arguments:

```
->addField('len', [
        'expr' => 'sum(if([date] = [exp_date], 1, 0))',
        'args' => ['exp_date' => '2003-03-04],
    ]),
```

Alternatively you may also specify either 'aggregate':

```
$book->hasMany('Pages', ['model' => [Page::class]])
    ->addField('page_list', [
        'aggregate' => $book->refModel('Pages')->expr('group_concat([number], [])', ['-']),
    ]);
```

or 'field':

```
->addField('paid_amount', ['aggregate' => 'count', 'field' => new \Atk4\Data\Persistence\Sql\Expression('*')]);
```

:::{note}
as of 1.3.4 count's field defaults to `*` - no need to specify explicitly.
:::

## hasMany / refLink / refModel

:::{php:method} refLink($link)
:::

Normally ref() will return a usable model back to you, however if you use refLink then
the conditioning will be done differently. refLink is useful when defining
sub-queries:

```
$m = new Model_User($dbArrayCache, 'user');
$m->hasMany('Orders', ['model' => [Model_Order::class]]);
$m->addCondition('is_vip', true);

$sum = $m->refLink('Orders')->action('fx0', ['sum', 'amount']);
$m->addExpression('sum_amount')->set($sum);
```

The refLink would define a condition on a query like this:

```sql
select * from `order` where user_id = `user`.id
```

And it will not be viable on its own, however if you use it inside a sub-query,
then it now makes sense for generating expression:

```sql
select
    (select sum(amount) from `order` where user_id = `user`.id) sum_amount
from user
where is_vip = 1
```

:::{php:method} refModel($link)
:::

There are many situations when you need to get referenced model instead of
reference itself. In such case refModel() comes in as handy shortcut of doing
`$model->refLink($link)->getModel()`.

## hasOne reference

:::{php:method} hasOne($link, ['model' => $model])
$model can be an array containing options: [$model, ...]
:::

This reference allows you to attach a related model to a foreign key:

```
$o = new Model_Order($db, 'order');
$u = new Model_User($db, 'user');

$o->hasOne('user_id', ['model' => $u]);
```

This reference is similar to hasMany, but it does behave slightly different.
Also this reference will define a system new field `user_id` if you haven't
done so already.

### Traversing loaded model

If your `$o` model is loaded, then traversing into user will also load the user,
because we specifically know the ID of that user. No conditions will be set:

```
echo $o->load(3)->ref('user_id')['name']; // will show name of the user, of order #3
```

### Traversing DataSet

If your model is not loaded then using ref() will traverse by conditioning
DataSet of the user model:

```
$o->unload(); // just to be sure!
$o->addCondition('status', 'failed');
$u = $o->ref('user_id');

$u = $u->loadAny(); // will load some user who has at least one failed order
```

The important point here is that no additional queries are generated in the
process and the loadAny() will look like this:

```sql
select * from user where id in
    (select user_id from order where status = 'failed')
```

By passing options to hasOne() you can also differentiate field name:

```
$o->addField('user_id', ['type' => 'integer']);
$o->hasOne('User', ['model' => $u, 'ourField' => 'user_id']);

$o->load(1)->ref('User')['name'];
```

You can also use `theirField` if you need non-id matching (see example above
for hasMany()).

### Importing Fields

You can import some fields from related model. For example if you have list
of invoices, and each invoice contains "currency_id", but in order to get the
currency name you need another table, you can use this syntax to easily import
the field:

```
$i = new Model_Invoice($db)
$c = new Model_Currency($db);

$i->hasOne('currency_id', ['model' => $c])
    ->addField('currency_name', 'name');
```

This code also resolves problem with a duplicate 'name' field. Since you might have
a 'name' field inside 'Invoice' already, you can name the field 'currency_name'
which will reference 'name' field inside Currency. You can also import multiple
fields but keep in mind that this may make your query much longer.
The argument is associative array and if key is specified, then the field will
be renamed, just as we did above:

```
$u = new Model_User($db)
$a = new Model_Address($db);

$u->hasOne('address_id', ['model' => $a])
    ->addFields([
        'address_1',
        'address_2',
        'address_3',
        'address_notes' => ['notes', 'type' => 'text'],
    ]);
```

Above, all `address_` fields are copied with the same name, however field
'notes' from Address model will be called 'address_notes' inside user model.

:::{important}
When importing fields, they will preserve type, e.g. if you are importing
'date' then the type of your imported field will also be date. Imported
fields are also marked as "read-only" and attempt to change them will result
in exception.
:::

### Importing hasOne Title

When you are using hasOne() in most cases the referenced object will be addressed
through "ID" but will have a human-readable field as well. In the example above
`Model_Currency` has a title field called `name`. Agile Data provides you an
easier way how to define currency title:

```
$i = new Invoice($db)

$i->hasOne('currency_id', ['model' => [Currency::class]])
    ->addTitle();
```

This would create 'currency' field containing name of the currency:

```
$i = $i->load(20);

echo 'Currency for invoice 20 is ' . $i->get('currency'); // EUR
```

Unlike addField() which creates fields read-only, title field can in fact be
modified:

```
$i->set('currency', 'GBP');
$i->save();

// will update $i->get('currency_id') to the corresponding ID for currency with name GBP.
```

This behavior is awesome when you are importing large amounts of data, because
the lookup for the currency_id is entirely done in a database.

By default name of the field will be calculated by removing "_id" from the end
of hasOne field, but to override this, you can specify name of the title field
explicitly:

```
$i->hasOne('currency_id', ['model' => [Currency::class]])
    ->addTitle(['field' => 'currency_name']);
```

## User-defined Reference

:::{php:method} addReference($link, $callback)
:::

Sometimes you would want to have a different type of relation between models,
so with `addReference` you can define whatever reference you want:

```
$m->addReference('Archive', ['model' => function (Model $m) {
    return new $m(null, ['table' => $m->table . '_archive']);
}]);
```

The above example will work for a table structure where a main table `user` is
shadowed by a archive table `user_archive`. Structure of both tables are same,
and if you wish to look into an archive of a User you would do:

```
$user->ref('Archive');
```

Note that you can create one-to-many or many-to-one relations, by using your
custom logic.
No condition will be applied by default so it's all up to you:

```
$m->addReference('Archive', ['model' => function (Model $m) {
    $archive = new $m(null, ['table' => $m->table . '_archive']);

    $m->addField('original_id', ['type' => 'integer']);

    if ($m->isLoaded())) {
        $archive->addCondition('original_id', $m->getId());
        // only show record of currently loaded record
    }
}]);
```

## Reference Discovery

You can call {php:meth}`Model::getReferences()` to fetch all the references of a model:

```
$references = $model->getReferences();
$reference = $references['owner_id'];
```

or if you know the reference you'd like to fetch, you can use {php:meth}`Model::getReference()`:

```
$reference = $model->getReference('owner_id');
```

While {php:meth}`Model::ref()` returns a related model, {php:meth}`Model::getReference()`
gives you the reference object itself so that you could perform some changes on it,
such as import more fields with {php:meth}`Model::addField()`.

Or you can use {php:meth}`Model::refModel()` which will simply return referenced
model and you can do fancy things with it.

```
$refModel = $model->refModel('owner_id');
```

You can also use {php:meth}`Model::hasReference()` to check if particular reference
exists in model:

```
if ($model->hasReference('owner_id')) {
    $reference = $model->getReference('owner_id');
}
```

## Deep traversal

When operating with data-sets you can define references that use deep traversal:

```
echo $o->load(1)->ref('user_id')->ref('address_id')['address_1'];
```

The above example will actually perform 3 load operations, because as I have
explained above, {php:meth}`Model::ref()` loads related model when called on
a loaded model. To perform a single query instead, you can use:

```
echo $o->addCondition('id', 1)->ref('user_id')->ref('address_id')->loadAny()['address_1'];
```

Here `addCondition('id', 1)` will only set a condition without actually loading the record
and traversal will encapsulate sub-queries resulting in a query like this:

```sql
select * from address where id in
    (select address_id from user where id in
        (select user_id from order where id = 1 ))
```

## Reference Aliases

When related entity relies on the same table it is possible to run into problem
when SQL is confused about which table to use.

```sql
select name, (select name from item where item.parent_id = item.id) parent_name from item
```

To avoid this problem Agile Data will automatically alias tables in sub-queries.
Here is how it works:

```
$item->hasMany('parent_item_id', ['model' => [Model_Item::class]])
    ->addField('parent', 'name');
```

When generating expression for 'parent', the sub-query will use alias `pi`
consisting of first letters in 'parent_item_id'. (except _id). You can actually
specify a custom table alias if you want:

```
$item->hasMany('parent_item_id', ['model' => [Model_Item::class], 'tableAlias' => 'mypi'])
    ->addField('parent', 'name');
```

Additionally you can pass tableAlias as second argument into {php:meth}`Model::ref()`
or {php:meth}`Model::refLink()`. This can help you in creating a recursive models
that relate to itself. Here is example:

```
class Model_Item3 extends \Atk4\Data\Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();

        $m = new Model_Item3();

        $this->addField('name');
        $this->addField('age');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', ['model' => $m, 'tableAlias' => 'parent'])
            ->addTitle();

        $this->hasMany('Child', ['model' => $m, 'theirField' => 'parent_item_id', 'tableAlias' => 'child'])
            ->addField('child_age', ['aggregate' => 'sum', 'field' => 'age']);
    }
}
```

Loading model like that can produce a pretty sophisticated query:

```sql
select
    `pp`.`id`, `pp`.`name`, `pp`.`age`, `pp_i`.`parent_item_id`,
    (select `parent`.`name`
    from `item` `parent`
    left join `item2` as `parent_i` on `parent_i`.`item_id` = `parent`.`id`
    where `parent`.`id` = `pp_i`.`parent_item_id`
    ) `parent_item`,
    (select sum(`child`.`age`) from `item` `child`
    left join `item2` as `child_i` on `child_i`.`item_id` = `child`.`id`
    where `child_i`.`parent_item_id` = `pp`.`id`
    ) `child_age`, `pp`.`id` `_i`
from `item` `pp`left join `item2` as `pp_i` on `pp_i`.`item_id` = `pp`.`id`
```

## Various ways to specify options

When calling `hasOne()->addFields()` there are various ways to pass options:

- `addFields(['name', 'dob'])` - no options are passed, use defaults. Note that
  reference will not fetch the type of foreign field due to performance consideration.
- `addFields(['first_name' => 'name'])` - this indicates aliasing. Field `name`
  will be added as `first_name`.
- `addFields([['dob', 'type' => 'date']])` - wrap inside array to pass options to
  field
- `addFields(['the_date' => ['dob', 'type' => 'date']])` - combination of aliasing
  and options
- `addFields(['dob', 'dod'], ['type' => 'date'])` - passing defaults for multiple
  fields

## References with New Records

Agile Data takes extra care to help you link your new records with new related
entities.
Consider the following two models:

```
class Model_User extends \Atk4\Data\Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');

        $this->hasOne('contact_id', ['model' => [Model_Contact::class]]);
    }
}

class Model_Contact extends \Atk4\Data\Model
{
    public $table = 'contact';

    protected function init(): void
    {
        parent::init();

        $this->addField('address');
    }
}
```

This is a classic one to one reference, but let's look what happens when you are
working with a new model:

```
$m = new Model_User($db);

$m->set('name', 'John');
$m->save();
```

In this scenario, a new record will be added into 'user' with 'contact_id' equal
to null. The next example will traverse into the contact to set it up:

```
$m = new Model_User($db);

$m->set('name', 'John');
$m->ref('address_id')->save(['address' => 'street']);
$m->save();
```

When entity which you have referenced through ref() is saved, it will automatically
populate $m->get('contact_id') field and the final $m->save() will also store the reference.

ID setting is implemented through a basic hook. Related model will have afterSave
hook, which will update address_id field of the $m.

## Reference Classes

References are implemented through several classes:

:::{php:class} Reference\HasOne
Defines generic reference, that is typically created by {php:meth}`Model::addReference`
:::

:::{php:attr} tableAlias
Alias for related table. Because multiple references can point to the same
table, ability to have unique alias is pretty good.

You don't have to change this property, it is generated automatically.
:::

:::{php:attr} link
What should we pass into owner->ref() to get through to this reference.
Each reference has a unique identifier, although it's stored
in Model's elements as '#ref-xx'.
:::

:::{php:attr} model
May store reference to related model, depending on implementation.
:::

:::{php:attr} ourField
This is an optional property which can be used by your implementation
to store field-level relationship based on a common field matching.
:::

:::{php:attr} their_filed
This is an optional property which can be used by your implementation
to store field-level relationship based on a common field matching.
:::

:::{php:method} getModel
Returns referenced model without conditions.
:::

:::{php:method} ref
Returns referenced model WITH conditions. (if possible)
:::
