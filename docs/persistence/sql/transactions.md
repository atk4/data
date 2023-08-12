:::{php:namespace} Atk4\Data\Persistence\Sql
:::

# Transactions

When you work with the DSQL, you can work with transactions. There are 2
enhancements to the standard functionality of transactions in DSQL:

1. You can start nested transactions.
2. You can use {php:meth}`Connection::atomic()` which has a nicer syntax.

It is recommended to always use atomic() in your code.

:::{php:class} Connection
:::

:::{php:method} atomic($callback)
Execute callback within the SQL transaction. If callback encounters an
exception, whole transaction will be automatically rolled back:

```
$c->atomic(function () use ($c) {
    $c->dsql('user')->set('balance = balance + 10')->where('id', 10)->mode('update')->executeStatement();
    $c->dsql('user')->set('balance = balance - 10')->where('id', 14)->mode('update')->executeStatement();
});
```

atomic() can be nested.
The successful completion of a top-most method will commit everything.
Rollback of a top-most method will roll back everything.
:::

:::{php:method} beginTransaction
Start new transaction. If already started, will do nothing but will increase
transaction depth.
:::

:::{php:method} commit
Will commit transaction, however if {php:meth}`Connection::beginTransaction`
was executed more than once, will only decrease transaction depth.
:::

:::{php:method} inTransaction
Returns true if transaction is currently active. There is no need for you to
ever use this method.
:::

:::{php:method} rollBack
Roll-back the transaction, however if {php:meth}`Connection::beginTransaction`
was executed more than once, will only decrease transaction depth.
:::

:::{warning}
If you roll-back internal transaction and commit external
transaction, then result might be unpredictable.
Please discuss this https://github.com/atk4/dsql/issues/89
:::
