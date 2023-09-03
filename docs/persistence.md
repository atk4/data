:::{php:namespace} Atk4\Data
:::

(Persistence)=

# Loading and Saving (Persistence)

:::{php:class} Model
:::

Model object represents your real-life business objects such as "Invoice" or "Client".
The rest of your application works with "Model" objects only and have no knowledge of
what database you are using and how data is stored in there. This decouples your app
from the data storage (Persistence). If in the future you will want to change database
server or structure of your database, you can do it without affecting your application.

Data Persistence frameworks (like ATK Data) provide the bridge between "Model" and the
actual database. There is balance between performance, simplicity and consistency. While
other persistence frameworks insist on strict isolation, ATK Data prefers practicality
and simplicity.

ATK Data couples Model and Persistence, they have some intimate knowledge of each-other
and work as a unit. Persistence object is created first and by the time Model is created,
you specify persistence to the model.

During the lifecycle of the Model it can work with various records, save, load, unload data
etc, but it will always remain linked with that same persistence.

## Associating with Persistence

Create your persistence object first:

```
$db = \Atk4\Data\Persistence::connect($dsn);
```

There are two ways to link your model up with the persistence:

```
$m = new Model_Invoice($db);

$m = new Model_Invoice();
$m->setPersistence($db);
```

:::{php:method} load
Load active record from the DataSet:

```
$m = $m->load(10);
echo $m->get('name');
```

If record not found, will throw exception.
:::

:::{php:method} save($data = [])
Store active record back into DataSet. If record wasn't loaded, store it as
a new record:

```
$m = $m->load(10);
$m->set('name', 'John');
$m->save();
```

You can pass argument to save() to set() and save():

```
$m->unload();
$m->save(['name' => 'John']);
```
:::

:::{php:method} tryLoad
Same as load() but will return null if record is not found:

```
$m = $m->tryLoad(10);
```
:::

:::{php:method} unload
Remove active record and restore model to default state:

```
$m = $m->load(10);
$m->unload();

$m->set('name', 'New User');
$m->save(); // creates new user
```
:::

:::{php:method} delete($id = null)
Remove current record from DataSet. You can optionally pass ID if you wish
to delete a different record. If you pass ID of a currently loaded record,
it will be unloaded.
:::

### Inserting Record with a specific ID

When you add a new record with save(), insert() or import, you can specify ID
explicitly:

```
$m->set('id', 123);
$m->save();

// or $m->insert(['Record with ID=123', 'id' => 123']);
```

However if you change the ID for record that was loaded, then your database
record will also have its ID changed. Here is example:

```
$m = $m->load(123);
$m->setId(321);
$m->save();
```

After this your database won't have a record with ID 123 anymore.

## Type Converting

PHP operates with a handful of scalar types such as integer, string, booleans
etc. There are more advanced types such as DateTime. Finally user may introduce
more useful types.

Agile Data ensures that regardless of the selected database, types are converted
correctly for saving and restored as they were when loading:

```
$m->addField('is_admin', ['type' => 'boolean']);
$m->set('is_admin', false);
$m->save();

// SQL database will actually store `0`

$m = $m->load();

$m->get('is_admin'); // converted back to `false`
```

Behind a two simple lines might be a long path for the value. The various
components are essential and as developer you must understand the full sequence:

```
$m->set('is_admin', false);
$m->save();
```

### Strict Types an Normalization

PHP does not have strict types for variables, however if you specify type for
your model fields, the type will be enforced.

Calling "set()" or using array-access to set the value will start by casting
the value to an appropriate data-type. If it is impossible to cast the value,
then exception will be generated:

```
$m->set('is_admin', '1'); // OK, but stores as `true`

$m->set('is_admin', 123); // throws exception.
```

It's not only the 'type' property, but 'enum' can also imply restrictions:

```
$m->addField('access_type', ['enum' => ['readOnly', 'full']]);

$m->set('access_type', 'full'); // OK
$m->set('access_type', 'half-full'); // Exception
```

There are also non-trivial types in Agile Data:

```
$m->addField('salary', ['type' => 'atk4_money']);
$m->set('salary', 20); // converts to '20.00 EUR'

$m->addField('date', ['type' => 'date']);
$m->set('date', time()); // converts to DateTime class
```

Finally, you may create your own custom field types that follow a more
complex logic:

```
$m->add(new Field_Currency(), 'balance');
$m->set('balance', 12_200.0);

// May transparently work with 2 columns: 'balance_amount' and
// 'balance_currency_id' for example.
```

Loaded/saved data are always normalized unless the field value normalization
is intercepted a hook.

Final field flag that is worth mentioning is called {php:attr}`Field::$readOnly`
and if set, then value of a field may not be modified directly:

```
$m->addField('ref_no', ['readOnly' => true]);
$m = $m->load(123);

$m->get('ref_no'); // perfect for reading field that is populated by trigger.

$m->set('ref_no', 'foo'); // exception
```

Note that `readOnly` can still have a default value:

```
$m->addField('created', [
    'readOnly' => true,
    'type' => 'datetime',
    'default' => new DateTime(),
]);

$m->save(); // stores creation time just fine and also will loade it.
```

:::{note}
If you have been following our "Domain" vs "Persistence" then you can
probably see that all of the above functionality described in this section
apply only to the "Domain" model.
:::

### Typecasting

For full documentation on type-casting see {ref}`typecasting`

### Validation

Validation in application always depends on business logic.
For example, if you want `age` field to be above `14` for the user registration
you may have to ask yourself some questions:

- Can user store `12` inside a age field?
- If yes, Can user persist age with value of `12`?
- If yes, Can user complete registration with age of `12`?

If 12 cannot be stored at all, then exception would be generated during set(),
before you even get a chance to look at other fields.

If storing of `12` in the model field is OK validation can be called from
beforeSave() hook. This might be a better way if your validation rules depends
on multiple field conditions which you need to be able to access.

Finally you may allow persistence to store `12` value, but validate before
a user-defined operation. `completeRegistration` method could perform the
validation. In this case you can create a confirmation page, that actually
stores your in-complete registration inside the database.

You may also make a decision to store registration-in-progress inside
a session, so your validation should be aware of this logic.

Agile Data relies on 3rd party validation libraries, and you should be able
to find more information on how to integrate them.

### Multi-column fields

Lets talk more about this currency field:

```
$m->add(new Field_Currency(), 'balance');
$m->set('balance', 12_200.0);
```

It may be designed to split up the value by using two fields in the database:
`balance_amount` and `balance_currency_id`.
Both values must be loaded otherwise it will be impossible to re-construct
the value.

On other hand, we would prefer to hide those two columns for the rest
of application.

Finally, even though we are storing "id" for the currency we want to make use
of References.

Your init() method for a Field_Currency might look like this:

```
protected function init(): void
{
    parent::init();

    $this->neverPersist = true;

    $f = $this->shortName; // balance

    $this->getOwner()->addField(
        $f . '_amount',
        ['type' => 'atk4_money', 'system' => true]
    );

    $this->getOwner()->hasOne(
        $f . '_currency_id',
        [
            $this->currency_model ?? new Currency(),
            'system' => true,
        ]
    );
}
```

There are more work to be done until Field_Currency could be a valid field, but
I wanted to draw your attention to the use of field flags:

- system flag is used to hide `balance_amount` and `balance_currency_id` in UI.
- neverPersist flag is used because there are no `balance` column in persistence.

### Dates and Time

:::{todo}
this section might need cleanup
:::

There are 3 datetime formats supported:

- date: Converts into YYYY-MM-DD using UTC timezone for SQL. Defaults
  to DateTime() class in PHP, but supports string input (parsed as date
  in a current timezone) or unix timestamp.
- time: converts into HH:MM:SS using UTC timezone for storing in SQL.
  Defaults to DateTime() class in PHP, but supports string input
  (parsed as date in current timezone) or unix timestamp. Will discard
  date from timestamp.
- datetime: stores both date and time. Uses UTC in DB. Defaults to
  DateTime() class in PHP. Supports string input parsed by strtotime()
  or unix timestamp.

### Customizations

Process which converts field values in native PHP format to/from
database-specific formats is called {ref}`typecasting`. Persistence driver
implements a necessary type-casting through the following two methods:

:::{php:method} typecastLoadRow($model, $row)
Convert persistence-specific row of data to PHP-friendly row of data.
:::

:::{php:method} typecastSaveRow($model, $row)
Convert native PHP-native row of data into persistence-specific.
:::

Row persisting may rely on additional methods, such as:

:::{php:method} typecastLoadField(Field $field, $value)
Convert persistence-specific row of data to PHP-friendly row of data.
:::

:::{php:method} typecastSaveField(Field $field, $value)
Convert native PHP-native row of data into persistence-specific.
:::

## Duplicating and Replacing Records

In normal operation, once you store a record inside your database, your
interaction will always update this existing record. Sometimes you want
to perform operations that may affect other records.

### Create copy of existing record

:::{php:method} duplicate($id = null)
Normally, active record stores "id", but when you call duplicate() it
forgets current ID and as result it will be inserted as new record when you
execute `save()` next time.

If you pass the `$id` parameter, then the new record will be saved under
a new ID:

```
// Assume DB with only one record with ID = 123

// Load and duplicate that record
$m->load(123)->duplicate()->save();

// Now you have 2 records:
// one with ID = 123 and another with ID = {next db generated id}
echo $m->executeCountQuery();
```
:::

### Duplicate then save under a new ID

Assuming you have 2 different records in your database: 123 and 124, how can you
take values of 123 and write it on top of 124?

Here is how:

```
$m->load(123)->duplicate()->setId(124)->save();
```

Now the record 124 will be replaced with the data taken from record 123.
For SQL that means calling 'replace into x'.

:::{warning}
There is no special treatment for joins() when duplicating records, so your
new record will end up referencing the same joined record. If the join is
reverse then your new record may not load.

This will be properly addressed in a future version of Agile Data.
:::

## Working with Multiple DataSets

When you load a model, conditions are applied that make it impossible for you
to load record from outside of a data-set. In some cases you do want to store
the model outside of a data-set. This section focuses on various use-cases like
that.

### Cloning versus New Instance

When you clone a model, the new copy will inherit pretty much all the conditions
and any in-line modifications that you have applied on the original model.
If you decide to create new instance, it will provide a `vanilla` copy of model
without any in-line modifications.

### Looking for duplicates

We have a model 'Order' with a field 'ref', which must be unique within
the context of a client. However, orders are also stored in a 'Basket'.
Consider the following code:

```
$basket->ref('Order')->insert(['ref' => 123]);
```

You need to verify that the specific client wouldn't have another order with
this ref, how do you do it?

Start by creating a beforeSave handler for Order:

```
$this->onHookShort(Model::HOOK_BEFORE_SAVE, function () {
    if ($this->isDirty('ref')) {
        $m = (new static())
            ->addCondition('client_id', $this->get('client_id')) // same client
            ->addCondition($this->idField, '!=', $this->getId()) // has another order
            ->tryLoadBy('ref', $this->get('ref')) // with same ref
        if ($m !== null) {
            throw (new Exception('Order with ref already exists for this client'))
                ->addMoreInfo('client', $this->get('client_id'))
                ->addMoreInfo('ref', $this->get('ref'))
        }
    }
});
```

### Archiving Records

In this use case you are having a model 'Order', but you have introduced the
option to archive your orders. The method `archive()` is supposed to mark order
as archived and return that order back. Here is the usage pattern:

```
$o->addCondition('is_archived', false); // to restrict loading of archived orders
$o = $o->load(123);
$archive = $o->archive();
$archive->set('note', $archive->get('note') . "\nArchived on $date.");
$archive->save();
```

With Agile Data API building it's quite common to create a method that does not
actually persist the model.

The problem occurs if you have added some conditions on the $o model. It's
quite common to use $o inside a UI element and exclude Archived records. Because
of that, saving record as archived may cause exception as it is now outside
of the result-set.

There are two approaches to deal with this problem. The first involves disabling
after-save reloading:

```
public function archive()
{
    $this->reloadAfterSave = false;
    $this->set('is_archived', true);

    return $this;
}
```

After-save reloading would fail due to `is_archived = false` condition so
disabling reload is a hack to get your record into the database safely.

The other, more appropriate option is to re-use a vanilla Order record:

```
public function archive()
{
    $this->save(); // just to be sure, no dirty stuff is left over

    $archive = new static();
    $archive = $archive->load($this->getId());
    $archive->set('is_archived', true);

    $this->unload(); // active record is no longer accessible

    return $archive;
}
```

## Working with Multiple Persistencies

Normally when you load the model and save it later, it ends up in the same
database from which you have loaded it. There are cases, however, when you
want to store the record inside a different database. As we are looking into
use-cases, you should keep in mind that with Agile Data Persistence can be
pretty much anything including 'RestAPI', 'File', 'Memcache' or 'MongoDB'.

:::{important}
Instance of a model can be associated with a single persistence only. Once
it is associated, it stays like that. To store a model data into a different
persistence, a new instance of your model will be created and then associated
with a new persistence.
:::

:::{php:method} withPersistence($persistence)
:::

### Creating Cache with Memcache

Assuming that loading of a specific items from the database is expensive, you can
opt to store them in a MemCache. Caching is not part of core functionality of
Agile Data, so you will have to create logic yourself, which is actually quite
simple.

You can use several designs. I will create a method inside my application class
to load records from two persistencies that are stored inside properties of my
application:

```
public function loadQuick(Model $class, $id)
{
    // first, try to load it from MemCache
    $m = (clone $class)->setPersistence($this->mdb)->tryLoad($id);

    if ($m === null) {
        // fall-back to load from SQL
        $m = $this->sql->add(clone $class)->load($id);

        // store into MemCache too
        $m = $m->withPersistence($this->mdb)->save();
    }

    $m->onHook(Model::HOOK_BEFORE_SAVE, function (Model $m) {
        $m->withPersistence($this->sql)->save();
    });

    $m->onHook(Model::HOOK_BEFORE_DELETE, function (Model $m) {
        $m->withPersistence($this->sql)->delete();
    });

    return $m;
}
```

The above logic provides a simple caching framework for all of your models.
To use it with any model:

```
$m = $app->loadQuick(new Order(), 123);

$m->set('completed', true);
$m->save();
```

To look in more details into the actual method, I have broken it down into chunks:

```
// first, try to load it from MemCache:
$m = (clone $class)->setPersistence($this->mdb)->tryLoad($id);
```

The $class will be an uninitialized instance of a model (although you can also
use a string). It will first be associated with the MemCache DB persistence and
we will attempt to load a corresponding ID. Next, if no record is found in the
cache:

```
if ($m === null) {
    // fall-back to load from SQL
    $m = $this->sql->add(clone $class)->load($id);

    // store into MemCache too
    $m = $m->withPersistence($this->mdb)->save();
}
```

Load the record from the SQL database and store it into $m. Next, save $m into
the MemCache persistence by replacing (or creating new) record. The `$m` at the
end will be associated with the MemCache persistence for consistency with cached
records.
The last two hooks are in order to replicate any changes into the SQL database
also:

```
$m->onHook(Model::HOOK_BEFORE_SAVE, function (Model $m) {
    $m->withPersistence($this->sql)->save();
});

$m->onHook(Model::HOOK_BEFORE_DELETE, function (Model $m) {
    $m->withPersistence($this->sql)->delete();
});
```

I have too note that withPersistence() transfers the dirty flags into a new
model, so SQL record will be updated with the record that you have modified only.

If saving into SQL is successful the memcache persistence will be also updated.

### Using Read / Write Replicas

In some cases your application have to deal with read and write replicas of
the same database. In this case all the operations would be done on the read
replica, except for certain changes.

In theory you can use hooks (that have option to cancel default action) to
create a comprehensive system-wide solution, I'll illustrate how this can be
done with a single record:

```
$m = new Order($readReplica);

$m->set('completed', true);

$m->withPersistence($writeReplica)->save();
$dirtyRef = &$m->getDirtyRef();
$dirtyRef = [];

// Possibly the update is delayed
// $m->reload();
```

By changing 'completed' field value, it creates a dirty field inside `$m`,
which will be saved inside a `$writeReplica`. Although the proper approach
would be to reload the `$m`, if there is chance that your update to a write
replica may not propagate to read replica, you can simply reset the dirty flags.

If you need further optimization, make sure `reloadAfterSave` is disabled
for the write replica:

```
$m->withPersistence($writeReplica)->setDefaults(['reloadAfterSave' => false])->save();
```

or use:

```
$m->withPersistence($writeReplica)->saveAndUnload();
```

### Archive Copies into different persistence

If you wish that every time you save your model the copy is also stored inside
some other database (for archive purposes) you can implement it like this:

```
$m->onHook(Model::HOOK_BEFORE_SAVE, function (Model $m) {
    $arc = $this->withPersistence($m->getApp()->archive_db);

    // add some audit fields
    $arc->addField('original_id', ['type' => 'integer'])->set($this->getId());
    $arc->addField('saved_by')->set($this->getApp()->user);

    $arc->saveAndUnload();
});
```

### Store a specific record

If you are using authentication mechanism to log a user in and you wish to
store his details into Session, so that you don't have to reload every time,
you can implement it like this:

```
if (!isset($_SESSION['ad'])) {
    $_SESSION['ad'] = []; // initialize
}

$sess = new \Atk4\Data\Persistence\Array_($_SESSION['ad']);
$loggedUser = new User($sess);
$loggedUser = $loggedUser->load('active_user');
```

This would load the user data from Array located inside a local session. There
is no point storing multiple users, so I'm using id='active_user' for the only
user record that I'm going to store there.

How to add record inside session, e.g. log the user in? Here is the code:

```
$u = new User($db);
$u = $u->load(123);

$u->withPersistence($sess)->save();
```

(Action)=

## Actions

Action is a multi-row operation that will affect all the records inside DataSet.
Actions will not affect records outside of DataSet (records that do not match
conditions)

:::{php:method} action($action, $args = [])
Prepares a special object representing "action" of a persistence layer based
around your current model.
:::

### Action Types

Actions can be grouped by their result. Some action will be executed and will
not produce any results. Others will respond with either one value or multiple
rows of data.

- no results
- single value
- single row
- single column
- array of hashes

Action can be executed at any time and that will return an expected result:

```
$m = Model_Invoice();
$val = (int) $m->action('count')->getOne(); // same as $val = $m->executeCountQuery()
```

Most actions are sufficiently smart to understand what type of result you are
expecting, so you can have the following code:

```
$m = Model_Invoice();
$val = $m->action('count')();
```

When used inside the same Persistence, sometimes actions can be used without
executing:

```
$m = Model_Product($db);
$m->addCondition('name', $productName);
$action = $m->action('getOne', ['id']);

$m = Model_Invoice($db);
$m->insert(['qty' => 20, 'product_id' => $action]);
```

Insert operation will check if you are using same persistence.
If the persistence object is different, it will execute action and will use
result instead.

Being able to embed actions inside next query allows Agile Data to reduce number
of queries issued.

The default action type can be set when executing action, for example:

```
$a = $m->action('field', 'user', 'getOne');

echo $a(); // same as $a->getOne();
```

### SQL Actions

Currently only read-only actions are supported by `Persistence\Sql`:

- select - produces query that returns DataSet (array of hashes)

There are ability to execute aggregation functions:

```
echo $m->action('fx', ['max', 'salary'])->getOne();
```

and finally you can also use count:

```
echo $m->executeCountQuery(); // same as echo $m->action('count')->getOne()
```

### SQL Actions on Linked Records

In conjunction with Model::refLink() you can produce expressions for creating
sub-selects. The functionality is nicely wrapped inside HasMany::addField():

```
$client->hasMany('Invoice')
    ->addField('total_gross', ['aggregate' => 'sum', 'field' => 'gross']);
```

This operation is actually consisting of 3 following operations:

1. Related model is created and linked up using refLink that essentially places
   a condition between $client and $invoice assuming they will appear inside
   same query.
2. Action is created from $invoice using 'fx' and requested method / field.
3. Expression is created with name 'total_gross' that uses Action.

Here is a way how to intervene with the process:

```
$client->hasMany('Invoice');
$client->addExpression('last_sale', ['expr' => function (Model $m) {
    return $m->refLink('Invoice')
        ->setOrder('date desc')
        ->setLimit(1)
        ->action('field', ['total_gross'], 'getOne');
}, 'type' => 'float']);
```

The code above uses refLink and also creates expression, but it tweaks
the action used.

### Action Matrix

SQL actions apply the following:

- insert: init, mode
- update: init, mode, conditions, limit, order, hook
- delete: init, mode, conditions
- select: init, fields, conditions, limit, order, hook
- count: init, field, conditions, hook,
- field: init, field, conditions
- fx: init, field, conditions
