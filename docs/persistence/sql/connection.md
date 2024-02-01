:::{php:namespace} Atk4\Data\Persistence\Sql
:::

(connect)=

# Connection

DSQL supports various database vendors natively but also supports 3rd party
extensions.
For current status on database support see: {ref}`databases`.

:::{php:class} Connection
:::

Connection class is handy to have if you plan on building and executing
queries in your application. It's more appropriate to store
connection in a global variable or global class:

```
$app->db = Atk4\Data\Persistence\Sql\Connection::connect($dsn, $user, $pass, $defaults);
```

:::{php:method} static connect($dsn, $user = null, $password = null, $defaults = [])
Determine which Connection class should be used for specified $dsn,
establish connection to DB by creating new object of this connection class and return.

```{eval-rst}
:param string $dsn: DSN, see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
:param string $user: username
:param string $password: password
:param array $defaults: Other default properties for connection class.
:returns: new Connection
```
:::

This should allow you to access this class from anywhere and generate either
new Query or Expression class:

```
$query = $app->db->dsql();

// or

$expr = $app->db->expr('show tables');
```

:::{php:method} expr($template, $arguments)
Creates new Expression class and sets {php:attr}`Expression::$connection`.

```{eval-rst}
:param array $arguments: Other default properties for connection class.
:returns: new Expression
```
:::

:::{php:method} dsql($defaults)
Creates new Query class and sets {php:attr}`Query::$connection`.

```{eval-rst}
:param array $defaults: Other default properties for connection class.
:returns: new Query
```
:::

Here is how you can use all of this together:

```
$dsn = 'mysql:host=localhost;port=3307;dbname=testdb';

$connection = Atk4\Data\Persistence\Sql\Connection::connect($dsn, 'root', 'root');

echo 'Time now is: ' . $connection->expr('select now()');
```

{php:meth}`connect` will determine appropriate class that can be used for this
DSN string. This can be a PDO class or it may try to use a 3rd party connection
class.

Connection class is also responsible for executing queries. This is only used
if you connect to vendor that does not use PDO.

:::{php:method} execute(Expression $expr): \Doctrine\DBAL\Result
Creates new Expression class and sets {php:attr}`Expression::$connection`.

```{eval-rst}
:param Expression $expr: Expression (or query) to execute
:returns: `Doctrine\\DBAL\\Result`
```
:::

:::{php:method} registerConnectionClass($connectionClass, $connectionType)
Adds connection class to the registry for resolving in Connection::resolveConnectionClass method.

```{eval-rst}
:param string $connectionClass: The connection class to be used for the diver type
:param string $connectionType: Alias of the connection
```
:::

Developers can register custom classes to handle driver types using the `Connection::registerConnectionClass` method:

```
Connection::registerConnectionClass(Custom\MySQL\Connection::class, 'pdo_mysql');
```

:::{php:method} connectDbalConnection(array $dsn)
The method should establish connection with DB and return the underlying connection object used by
the `Connection` class. By default PDO is used but the method can be overridden to return custom object to be
used for connection to DB.
:::
