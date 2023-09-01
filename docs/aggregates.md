:::{php:namespace} Atk4\Data
:::

(Aggregates)=

# Model Aggregates

:::{php:class} Model\AggregateModel
:::

In order to create model aggregates the AggregateModel model needs to be used:

## Grouping

AggregateModel model can be used for grouping:

```
$aggregate = new AggregateModel($orders)->setGroupBy(['country_id']);
```

`$aggregate` above is a new object that is most appropriate for the model's persistence and which can be manipulated
in various ways to fine-tune aggregation. Below is one sample use:

```
$aggregate = new AggregateModel($orders);
$aggregate->addField('country');
$aggregate->setGroupBy(['country_id'], [
        'count' => ['expr' => 'count(*)', 'type' => 'integer'],
        'total_amount' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
    ],
);

// $aggregate will have following rows:
// ['country' => 'UK', 'count' => 20, 'total_amount' => 123.2];
// ..
```

Below is how opening balance can be built:

```
$ledger = new GeneralLedger($db);
$ledger->addCondition('date', '<', $from);

// we actually need grouping by nominal
$ledger->setGroupBy(['nominal_id'], [
    'opening_balance' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
]);
```
