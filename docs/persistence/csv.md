:::{php:namespace} Atk4\Data
:::

(Persistence_Csv)=

# Loading and Saving CSV Files

:::{php:class} Persistence\Csv
:::

Agile Data can operate with CSV files for data loading, or saving. The capabilities
of `Persistence\Csv` are limited to the following actions:

- open any CSV file, use column mapping
- identify which column is corresponding for respective field
- support custom mapper, e.g. if date is stored in a weird way
- support for CSV files that have extra lines on top/bottom/etc
- listing/iterating
- adding a new record

## Setting Up

When creating new persistence you must provide a valid URL for
the file that might be stored either on a local system or
use a remote file scheme (ftp://...). The file will not be
actually opened unless you perform load/save operation:

```
$p = new Persistence\Csv('myfile.csv');

$u = new Model_User($p);
$u = $u->tryLoadAny(); // actually opens file and finds first record
```

## Exporting and Importing data from CSV

You can take a model that is loaded from other persistence and save
it into CSV like this. The next example demonstrates a basic functionality
of SQL database export to CSV file:

```
$db = new Persistence\Sql($connection);
$csv = new Persistence\Csv('dump.csv');

$m = new Model_User($db);

foreach (new Model_User($db) as $m) {
    $m->withPersistence($csv)->save();
}
```

Theoretically you can do few things to tweak this process. You can specify
which fields you would like to see in the CSV:

```
foreach (new Model_User($db) as $m) {
    $m->withPersistence($csv)
        ->setOnlyFields(['id', 'name', 'password'])
        ->save();
}
```

Additionally if you want to use a different column titles, you can:

```
foreach (new Model_User($db) as $m) {
    $mCsv = $m->withPersistence($csv);
    $mCsv->setOnlyFields(['id', 'name', 'password'])
    $mCsv->getField('name')->actual = 'First Name';
    $mCsv->save();
}
```

Like with any other persistence you can use typecasting if you want data to be
stored in any particular format.

The examples above also create object on each iteration, that may appear as
a performance inefficiency. This can be solved by re-using Csv model through
iterations:

```
$m = new Model_User($db);
$mCsv = $m->withPersistence($csv);
$mCsv->setOnlyFields(['id', 'name', 'password'])
$mCsv->getField('name')->actual = 'First Name';

foreach ($m as $mCsv) {
    $mCsv->save();
}
```

This code can be further simplified if you use import() method:

```
$m = new Model_User($db);
$mCsv = $m->withPersistence($csv);
$mCsv->setOnlyFields(['id', 'name', 'password'])
$mCsv->getField('name')->actual = 'First Name';
$mCsv->import($m);
```

Naturally you can also move data in the other direction:

```
$m = new Model_User($db);
$mCsv = $m->withPersistence($csv);
$mCsv->setOnlyFields(['id', 'name', 'password'])
$mCsv->getField('name')->actual = 'First Name';

$m->import($mCsv);
```

Only the last line changes and the data will now flow in the other direction.
