:::{php:namespace} Atk4\Data
:::

# Fetching results

:::{php:class} Model
:::

Model linked to a persistence is your "window" into DataSet and you get several
ways which allow you to fetch the data.

## Iterate through model data

:::{php:method} getIterator()
:::

Create your persistence object first then iterate it:

```
$db = \Atk4\Data\Persistence::connect($dsn);
$m = new Model_Client($db);

foreach ($m as $id => $entity) {
    echo $id . ': ' . $entity->get('name') . "\n";
}
```

:::{note}
changing query parameter during iteration will has no effect until you
finish iterating.
:::

### Raw Data Fetching

:::{php:method} getRawIterator()
:::

If you do not care about the hooks and simply wish to get the data, you can fetch
it:

```
foreach ($m->getRawIterator() as $row) {
    var_dump($row); // array
}
```

The $row will also contain value for "id" and it's up to you to find it yourself
if you need it.

:::{php:method} export()
:::

Will fetch and output array of hashes which will represent entirety of data-set.
Similarly to other methods, this will have the data mapped into your fields for
you and server-side expressions executed that are embedded in the query.

By default - `onlyFields` will be presented as well as system fields.

### Fetching data through action

You can invoke and iterate action (particularly SQL) to fetch the data:

```
foreach ($m->action('select') as $row) {
    var_dump($row); // array
}
```

This has the identical behavior to $m->getRawIterator();

## Comparison of various ways of fetching

- getIterator - action(select), [ fetches row, set ID/Data, call afterLoad hook,
  yields model ], unloads data
- getRawIterator - action(select), [ fetches row, yields row ]
- export - action(select), fetches all rows, returns all rows
