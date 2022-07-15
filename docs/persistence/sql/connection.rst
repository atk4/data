
.. _connect:

==========
Connection
==========

DSQL supports various database vendors natively but also supports 3rd party
extensions.
For current status on database support see: :ref:`databases`.


.. php:class:: Connection

Connection class is handy to have if you plan on building and executing
queries in your application. It's more appropriate to store
connection in a global variable or global class::

    $app->db = Atk4\Data\Persistence\Sql\Connection::connect($dsn, $user, $pass, $args);


.. php:staticmethod:: connect($dsn, $user = null, $password = null, $args = [])

    Determine which Connection class should be used for specified $dsn,
    establish connection to DB by creating new object of this connection class and return.

    :param string $dsn: DSN, see https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
    :param string $user: username
    :param string $password: password
    :param array  $args: Other default properties for connection class.
    :returns: new Connection


This should allow you to access this class from anywhere and generate either
new Query or Expression class::

    $query = $app->db->dsql();

    // or

    $expr = $app->db->expr('show tables');


.. php:method:: dsql($args)

    Creates new Query class and sets :php:attr:`Query::connection`.

    :param array  $args: Other default properties for connection class.
    :returns: new Query

.. php:method:: expr($template, $args)

    Creates new Expression class and sets :php:attr:`Expression::connection`.

    :param string  $args: Other default properties for connection class.
    :param array  $args: Other default properties for connection class.
    :returns: new Expression


Here is how you can use all of this together::

    $dsn = 'mysql:host=localhost;port=3307;dbname=testdb';

    $connection = Atk4\Data\Persistence\Sql\Connection::connect($dsn, 'root', 'root');

    echo "Time now is : ". $connection->expr('select now()');

:php:meth:`connect` will determine appropriate class that can be used for this
DSN string. This can be a PDO class or it may try to use a 3rd party connection
class.

Connection class is also responsible for executing queries. This is only used
if you connect to vendor that does not use PDO.

.. php:method:: execute(Expression $expr): \Doctrine\DBAL\Result

    Creates new Expression class and sets :php:attr:`Expression::connection`.

    :param Expression  $expr: Expression (or query) to execute
    :returns: `Doctrine\DBAL\Result`

.. php:method:: registerConnectionClass($connectionClass, $connectionType)

    Adds connection class to the registry for resolving in Connection::resolveConnectionClass method.

    :param string $connectionType Alias of the connection
    :param string $connectionClass The connection class to be used for the diver type

Developers can register custom classes to handle driver types using the `Connecion::registerConnectionClass` method::

   Connection::registerConnectionClass(Custom\MySQL\Connection::class, 'pdo_mysql');

.. php:method:: connectDbalConnection(array $dsn)

   The method should establish connection with DB and return the underlying connection object used by
   the `Connection` class. By default PDO is used but the method can be overriden to return custom object to be
   used for connection to DB.
