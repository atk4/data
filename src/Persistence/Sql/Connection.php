<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Core\DiContainerTrait;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\Connection as DbalDriverConnection;
use Doctrine\DBAL\Driver\Mysqli\Connection as DbalMysqliConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Result as DbalResult;

/**
 * Class for establishing and maintaining connection with your database.
 */
abstract class Connection
{
    use DiContainerTrait;

    /** @var string Query classname */
    protected $query_class = Query::class;

    /** @var string Expression classname */
    protected $expression_class = Expression::class;

    /** @var DbalConnection */
    protected $connection;

    /** @var array<string, string> */
    protected static $connectionClassRegistry = [
        'pdo_sqlite' => Sqlite\Connection::class,
        'pdo_mysql' => Mysql\Connection::class,
        'mysqli' => Mysql\Connection::class,
        'pdo_pgsql' => Postgresql\Connection::class,
        'pdo_oci' => Oracle\Connection::class,
        'pdo_sqlsrv' => Mssql\Connection::class,
    ];

    /**
     * Specifying $properties to constructors will override default
     * property values of this class.
     *
     * @param array $properties
     */
    public function __construct($properties = [])
    {
        if (!is_array($properties)) {
            throw (new Exception('Invalid properties for "new Connection()". Did you mean to call Connection::connect()?'))
                ->addMoreInfo('properties', $properties);
        }

        $this->setDefaults($properties);
    }

    public function __destruct()
    {
        // needed for DBAL connection to be released immeditelly
        if ($this->connection !== null) {
            $this->connection->close();
        }
    }

    /**
     * Normalize DSN connection string or DBAL connection params described in:
     * https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html .
     *
     * Returns normalized DSN as array ['driver', 'host', 'user', 'password', 'dbname', 'charset', ...].
     *
     * @param array|string $dsn
     * @param string       $user     Optional username, this takes precedence over dsn string
     * @param string       $password Optional password, this takes precedence over dsn string
     *
     * @return array
     */
    public static function normalizeDsn($dsn, $user = null, $password = null)
    {
        // BC for 2.4 - 3.0 accepted DSN input
        if (is_string($dsn)) {
            $dsn = ['dsn' => $dsn];
        }
        if (isset($dsn['dsn'])) {
            if (str_contains($dsn['dsn'], '://')) {
                $parsed = array_filter(parse_url($dsn['dsn']));
                $dsn['dsn'] = str_replace('-', '_', $parsed['scheme']) . ':';
                unset($parsed['scheme']);
                foreach ($parsed as $k => $v) {
                    if ($k === 'pass') { // @phpstan-ignore-line phpstan bug
                        unset($parsed[$k]);
                        $k = 'password';
                    } elseif ($k === 'path') { // @phpstan-ignore-line phpstan bug
                        unset($parsed[$k]);
                        $k = 'dbname';
                        $v = preg_replace('~^/~', '', $v);
                    }
                    $parsed[$k] = $k . '=' . $v;
                }
                $dsn['dsn'] .= implode(';', $parsed);
            }

            $parts = explode(':', $dsn['dsn'], 2);
            $dsn = ['driver' => strtolower($parts[0])];
            if ($dsn['driver'] === 'sqlite') {
                if (trim($parts[1], ':') === 'memory') {
                    $dsn['memory'] = true;
                } else {
                    $dsn['path'] = trim($parts[1], ':');
                }
            } else {
                foreach (explode(';', $parts[1] ?? '') as $part) {
                    [$k, $v] = str_contains($part, '=') ? explode('=', $part, 2) : [$part, null];
                    $dsn[$k] = $v;
                }
                if (isset($dsn['host']) && str_contains($dsn['host'], ':')) {
                    [$dsn['host'], $port] = explode(':', $dsn['host'], 2);
                    $dsn['port'] = $port;
                }
            }
        }

        if ($user !== null) {
            $dsn['user'] = $user;
        }

        if ($password !== null) {
            $dsn['password'] = $password;
        }

        if (!str_starts_with($dsn['driver'], 'pdo_') && !in_array($dsn['driver'], ['mysqli'], true)) {
            $dsn['driver'] = 'pdo_' . $dsn['driver'];
        }

        return $dsn;
    }

    /**
     * Adds connection class to the registry for resolving in Connection::resolve method.
     *
     * Can be used as:
     *   Connection::registerConnection(MySQL\Connection::class, 'pdo_mysql')
     */
    public static function registerConnectionClass(string $connectionClass, string $driverName): void
    {
        self::$connectionClassRegistry[$driverName] = $connectionClass;
    }

    /**
     * Resolves the connection class to use based on driver type.
     */
    public static function resolveConnectionClass(string $driverName): string
    {
        if (!isset(self::$connectionClassRegistry[$driverName])) {
            throw (new Exception('Driver schema is not registered'))
                ->addMoreInfo('driver_schema', $driverName);
        }

        return self::$connectionClassRegistry[$driverName];
    }

    /**
     * Connect to database and return connection class.
     *
     * @param string|array|DbalConnection|DbalDriverConnection $dsn
     * @param string|null                                      $user
     * @param string|null                                      $password
     * @param array                                            $args
     *
     * @return Connection
     */
    public static function connect($dsn, $user = null, $password = null, $args = [])
    {
        if ($dsn instanceof DbalConnection) {
            $driverName = self::getDriverNameFromDbalDriverConnection($dsn->getWrappedConnection());
            $connectionClass = self::resolveConnectionClass($driverName);
            $dbalConnection = $dsn;
        } elseif ($dsn instanceof DbalDriverConnection) {
            $driverName = self::getDriverNameFromDbalDriverConnection($dsn);
            $connectionClass = self::resolveConnectionClass($driverName);
            $dbalConnection = $connectionClass::connectFromDbalDriverConnection($dsn);
        } else {
            $dsn = static::normalizeDsn($dsn, $user, $password);
            $connectionClass = self::resolveConnectionClass($dsn['driver']);
            $dbalDriverConnection = $connectionClass::connectFromDsn($dsn);
            $dbalConnection = $connectionClass::connectFromDbalDriverConnection($dbalDriverConnection);
        }

        return new $connectionClass(array_merge([
            'connection' => $dbalConnection,
        ], $args));
    }

    final public static function isComposerDbal2x(): bool
    {
        return !class_exists(DbalResult::class);
    }

    /**
     * @param DbalDriverConnection|DbalConnection $connection
     *
     * @return \PDO|\mysqli
     */
    private static function getDriverFromDbalDriverConnection($connection): object
    {
        // TODO replace this method with Connection::getNativeConnection() once only DBAL 3.3+ is supported
        // https://github.com/doctrine/dbal/pull/5037

        if (self::isComposerDbal2x()) {
            if ($connection instanceof \PDO || $connection instanceof \mysqli) {
                return $connection;
            }
        }

        $wrappedConnection = $connection instanceof DbalMysqliConnection
            ? $connection->getWrappedResourceHandle()
            : $connection->getWrappedConnection(); // @phpstan-ignore-line

        if ($wrappedConnection instanceof \PDO || $wrappedConnection instanceof \mysqli) {
            return $wrappedConnection;
        }

        return self::getDriverFromDbalDriverConnection($wrappedConnection);
    }

    private static function getDriverNameFromDbalDriverConnection(DbalDriverConnection $connection): string
    {
        $driver = self::getDriverFromDbalDriverConnection($connection);

        if ($driver instanceof \PDO) {
            return 'pdo_' . $driver->getAttribute(\PDO::ATTR_DRIVER_NAME);
        } elseif ($driver instanceof \mysqli) {
            return 'mysqli';
        }

        return null; // @phpstan-ignore-line
    }

    protected static function createDbalEventManager(): EventManager
    {
        return new EventManager();
    }

    protected static function connectFromDsn(array $dsn): DbalDriverConnection
    {
        $dsn = static::normalizeDsn($dsn);
        if ($dsn['driver'] === 'pdo_mysql' || $dsn['driver'] === 'mysqli') {
            $dsn['charset'] = 'utf8mb4';
        } elseif ($dsn['driver'] === 'pdo_oci') {
            $dsn['charset'] = 'AL32UTF8';
        }

        $dbalConnection = DriverManager::getConnection(
            $dsn,
            null,
            (static::class)::createDbalEventManager()
        );

        return $dbalConnection->getWrappedConnection();
    }

    protected static function connectFromDbalDriverConnection(DbalDriverConnection $dbalDriverConnection): DbalConnection
    {
        $dbalConnection = DriverManager::getConnection([
            'driver' => self::getDriverNameFromDbalDriverConnection($dbalDriverConnection),
        ], null, (static::class)::createDbalEventManager());
        \Closure::bind(function () use ($dbalConnection, $dbalDriverConnection): void {
            $dbalConnection->_conn = $dbalDriverConnection;
        }, null, \Doctrine\DBAL\Connection::class)();

        if ($dbalConnection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            \Closure::bind(function () use ($dbalConnection) {
                $dbalConnection->platform = new class() extends \Doctrine\DBAL\Platforms\PostgreSQL94Platform {
                    use Postgresql\PlatformTrait;
                };
            }, null, DbalConnection::class)();
        }

        if ($dbalConnection->getDatabasePlatform() instanceof SQLServerPlatform) {
            \Closure::bind(function () use ($dbalConnection) {
                $dbalConnection->platform = new class() extends \Doctrine\DBAL\Platforms\SQLServer2012Platform {
                    use Mssql\PlatformTrait;
                };
            }, null, DbalConnection::class)();
        }

        if ($dbalConnection->getDatabasePlatform() instanceof OraclePlatform) {
            \Closure::bind(function () use ($dbalConnection) {
                $dbalConnection->platform = new class() extends OraclePlatform {
                    use Oracle\PlatformTrait;
                };
            }, null, DbalConnection::class)();
        }

        return $dbalConnection;
    }

    /**
     * Returns new Query object with connection already set.
     *
     * @param string|array $properties
     */
    public function dsql($properties = []): Query
    {
        $c = $this->query_class;
        $q = new $c($properties);
        $q->connection = $this;

        return $q;
    }

    /**
     * Returns Expression object with connection already set.
     *
     * @param string|array $properties
     * @param array        $arguments
     */
    public function expr($properties = [], $arguments = null): Expression
    {
        $c = $this->expression_class;
        $e = new $c($properties, $arguments);
        $e->connection = $this;

        return $e;
    }

    /**
     * @return DbalConnection
     */
    public function connection()
    {
        return $this->connection;
    }

    /**
     * Execute Expression by using this connection.
     *
     * @return DbalResult|\PDOStatement PDOStatement iff for DBAL 2.x
     */
    public function execute(Expression $expr): object
    {
        if ($this->connection === null) {
            throw new Exception('Queries cannot be executed through this connection');
        }

        return $expr->execute($this->connection);
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @param mixed ...$args
     *
     * @return mixed
     */
    public function atomic(\Closure $fx, ...$args)
    {
        $this->beginTransaction();
        try {
            $res = $fx(...$args);
            $this->commit();

            return $res;
        } catch (\Exception $e) {
            $this->rollBack();

            throw $e;
        }
    }

    /**
     * Starts new transaction.
     *
     * Database driver supports statements for starting and committing
     * transactions. Unfortunately most of them don't allow to nest
     * transactions and commit gradually.
     * With this method you have some implementation of nested transactions.
     *
     * When you call it for the first time it will begin transaction. If you
     * call it more times, it will do nothing but will increase depth counter.
     * You will need to call commit() for each execution of beginTransactions()
     * and only the last commit will perform actual commit in database.
     *
     * So, if you have been working with the database and got un-handled
     * exception in the middle of your code, everything will be rolled back.
     */
    public function beginTransaction(): void
    {
        try {
            $this->connection->beginTransaction();
        } catch (\Doctrine\DBAL\ConnectionException $e) {
            throw new Exception('Begin transaction failed', 0, $e);
        }
    }

    /**
     * Will return true if currently running inside a transaction.
     * This is useful if you are logging anything into a database. If you are
     * inside a transaction, don't log or it may be rolled back.
     * Perhaps use a hook for this?
     */
    public function inTransaction(): bool
    {
        return $this->connection->isTransactionActive();
    }

    /**
     * Commits transaction.
     *
     * Each occurrence of beginTransaction() must be matched with commit().
     * Only when same amount of commits are executed, the actual commit will be
     * issued to the database.
     */
    public function commit(): void
    {
        try {
            $this->connection->commit();
        } catch (\Doctrine\DBAL\ConnectionException $e) {
            throw new Exception('Commit failed', 0, $e);
        }
    }

    /**
     * Rollbacks queries since beginTransaction and resets transaction depth.
     */
    public function rollBack(): void
    {
        try {
            $this->connection->rollBack();
        } catch (\Doctrine\DBAL\ConnectionException $e) {
            throw new Exception('Rollback failed', 0, $e);
        }
    }

    /**
     * Return last inserted ID value.
     *
     * Drivers like PostgreSQL need to receive sequence name to get ID because PDO doesn't support this method.
     */
    public function lastInsertId(string $sequence = null): string
    {
        $res = $this->connection()->lastInsertId($sequence);

        return is_int($res) ? (string) $res : $res;
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }
}
