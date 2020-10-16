<?php

declare(strict_types=1);

namespace atk4\schema;

use atk4\core\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\Data\Model;
use atk4\data\Persistence;
use atk4\data\Reference\HasOne;
use atk4\dsql\Connection;
use atk4\dsql\Expression;
use Doctrine\DBAL\Platforms;

abstract class Migration extends Expression
{
    public const REF_TYPE_NONE = 0;
    public const REF_TYPE_LINK = 1;
    public const REF_TYPE_PRIMARY = 2;

    /** @var string Expression mode. See. */
    public $mode = 'create';

    /** @var array Expression templates */
    protected $templates = [
        'create' => 'create table {table} ([field])',
        'drop' => 'drop table if exists {table}',
        'alter' => 'alter table {table} [statements]',
        'rename' => 'rename table {old_table} to {table}',
    ];

    /** @var Connection Database connection */
    public $connection;

    /**
     * Field, table and alias name escaping symbol.
     * By SQL Standard it's double quote, but MySQL uses backtick.
     *
     * @var string
     */
    protected $escape_char = '"';

    /** @var string Expression to create primary key */
    public $primary_key_expr = 'primary key autoincrement';

    /** @var array Conversion mapping from Agile Data types to persistence types */
    protected $defaultMapToPersistence = [
        ['varchar', 255], // default
        'boolean' => ['tinyint', 1],
        'integer' => ['bigint'],
        'money' => ['decimal', 12, 2],
        'float' => ['decimal', 16, 6],
        'date' => ['date'],
        'datetime' => ['datetime'],
        'time' => ['varchar', 8],
        'text' => ['text'],
        'array' => ['text'],
        'object' => ['text'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToPersistence = [];

    /** @var array Conversion mapping from persistence types to Agile Data types */
    protected $defaultMapToAgile = [
        [null], // default
        'tinyint' => ['boolean'],
        'int' => ['integer'],
        'integer' => ['integer'],
        'bigint' => ['integer'],
        'decimal' => ['float'],
        'numeric' => ['float'],
        'date' => ['date'],
        'datetime' => ['datetime'],
        'timestamp' => ['datetime'],
        'text' => ['text'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [];

    /**
     * Stores migrator class to use based on Platform class.
     *
     * Visibility is intentionally set to private.
     * If generic class Migration::of($source) is called, the migrator class will be resolved based on connection's Platform class of $source.
     * When specific migrator class e.g Migration\Mysql::of($source) is called, connection's Platform class will not be resolved (the $registry property is NOT visible).
     * Mysql migrator class will be used explicitly.
     *
     * @var array
     *
     * */
    private static $registry = [
        Platforms\SqlitePlatform::class => Migration\Sqlite::class,
        Platforms\MySqlPlatform::class => Migration\Mysql::class,
        Platforms\PostgreSqlPlatform::class => Migration\Postgresql::class,
        Platforms\OraclePlatform::class => Migration\Oracle::class,
        Platforms\SQLServerPlatform::class => Migration\Mssql::class,
    ];

    /**
     * @deprecated use Migration::of instead
     */
    public static function getMigration($source, $params = []): self
    {
        return self::of($source, $params);
    }

    /**
     * Factory method to get correct Migration subclass object depending on connection given.
     *
     * @param Connection|Persistence|Model $source
     * @param array                        $params
     *
     * @return Migration Subclass
     */
    public static function of($source, $params = []): self
    {
        $connection = static::getConnection($source);

        $migratorClass = null;
        foreach (self::$registry as $platformClass => $class) {
            if ($connection->getDatabasePlatform() instanceof $platformClass) {
                $migratorClass = $class;
            }
        }

        // if used within a subclass Migration method will create migrator of that class
        // if $migrator class is the generic class Migration then migrator was not resolved correctly
        if ($migratorClass === null) {
            throw (new Exception('Not sure which migration class to use for your DSN'))
                ->addMoreInfo('platform', $connection->getDatabasePlatform())
                ->addMoreInfo('source', $source);
        }

        return new $migratorClass($source, $params);
    }

    /**
     * Adds migrator class to the registry for resolving in Migration::of method.
     *
     * Can be used as:
     *
     * Migration::register(new Platforms\MySQLPlatform(), CustomMigration\Mysql), or
     * CustomMigration\Mysql::register('mysql')
     *
     * CustomMigration\Mysql must be descendant of Migration class.
     */
    public static function register(string $platformClass, string $migratorClass = null)
    {
        // forward to generic Migration::register if called with a descendant class e.g Migration\Mysql::register
        if (static::class !== self::class) {
            return (self::class)::register($platformClass, $migratorClass ?: static::class);
        } elseif (!$migratorClass) {
            throw (new Exception('Cannot register generic Migration class'))
                ->addMoreInfo('platform_class', $platformClass);
        }

        if (!is_subclass_of($migratorClass, self::class)) {
            throw (new Exception('Migrator must be descendant to generic Migration class'))
                ->addMoreInfo('migrator_class', $migratorClass);
        }

        self::$registry[$platformClass] = $migratorClass;
    }

    /**
     * Static method to extract Connection from Connection, Persistence or Model.
     *
     * @param Connection|Persistence|Model $source
     */
    public static function getConnection($source): Connection
    {
        if ($source instanceof Connection) {
            return $source;
        } elseif ($source instanceof Persistence\Sql) {
            return $source->connection;
        } elseif (
            $source instanceof Model
            && $source->persistence
            && ($source->persistence instanceof Persistence\Sql)
        ) {
            return $source->persistence->connection;
        }

        throw (new Exception('Source is specified incorrectly. Must be Connection, Persistence or initialized Model'))
            ->addMoreInfo('source', $source);
    }

    /**
     * Create new migration.
     *
     * @param Connection|Persistence|Model $source
     * @param array                        $params
     */
    public function __construct($source, $params = [])
    {
        parent::__construct($params);

        $this->setSource($source);
    }

    /**
     * Sets source of migration.
     *
     * @param Connection|Persistence|Model $source
     */
    public function setSource($source)
    {
        $this->connection = static::getConnection($source);

        if (
            $source instanceof Model
            && $source->persistence
            && ($source->persistence instanceof Persistence\Sql)
        ) {
            $this->setModel($source);
        }
    }

    /**
     * Sets model.
     */
    public function setModel(Model $m): Model
    {
        $this->table($m->table);

        foreach ($m->getFields() as $field) {
            // ignore not persisted model fields
            if ($field->never_persist) {
                continue;
            }

            if ($field instanceof FieldSqlExpression) {
                continue;
            }

            if ($field->short_name === $m->id_field) {
                $ref_type = self::REF_TYPE_PRIMARY;
                $persist_field = $field;
            } else {
                $ref_field = $this->getReferenceField($field);
                $ref_type = $ref_field !== null ? self::REF_TYPE_LINK : $ref_type = self::REF_TYPE_NONE;
                $persist_field = $ref_field ?? $field;
            }

            $options = [
                'type' => $ref_type !== self::REF_TYPE_NONE && empty($persist_field->type) ? 'integer' : $persist_field->type,
                'ref_type' => $ref_type,
                'mandatory' => ($field->mandatory || $field->required) && ($persist_field->mandatory || $persist_field->required),
                // todo add more options here
            ];

            $this->field($field->actual ?: $field->short_name, $options);
        }

        return $m;
    }

    protected function getReferenceField(Field $field): ?Field
    {
        // if the field is a hasOne relation
        // Don't have the right FieldType
        // FieldType is stored in the reference field
        if ($field->reference instanceof HasOne) {
            // @TODO if this can be done better?

            // i don't want to :
            // - change the isolation of relation link
            // - expose the protected property ->their_field
            // i need the type of the field to be used in this table
            $reflection = new \ReflectionClass($field->reference);
            $property = $reflection->getProperty('their_field');
            $property->setAccessible(true);

            /** @var string $reference_their_field get Reflection protected property Reference->their_field */
            $reference_their_field = $property->getValue($field->reference);

            /** @var string $reference_field reference field name */
            $reference_field = $reference_their_field ?? $field->reference->getOwner()->id_field;

            /** @var string $reference_model_class reference class fqcn */
            $reference_model_class = $field->reference->model;

            // @TODO fix, but without the dummy persistence, the following is shown:
            // Uncaught atk4\core\Exception: Element is not found in collection
            // for ID column
            $dummyPersistence = new Persistence\Sql($this->connection);

            return (new $reference_model_class($dummyPersistence))->getField($reference_field);
        }

        return null;
    }

    /**
     * Set SQL expression template.
     *
     * @param string $mode Template name
     *
     * @return $this
     */
    public function mode(string $mode): self
    {
        if (!isset($this->templates[$mode])) {
            throw (new Exception('Structure builder does not have this mode'))
                ->addMoreInfo('mode', $mode);
        }

        $this->mode = $mode;
        $this->template = $this->templates[$mode];

        return $this;
    }

    /**
     * Create new table.
     *
     * @return $this
     */
    public function create(): self
    {
        $this->mode('create')->execute();

        return $this;
    }

    /**
     * Drop table.
     *
     * @return $this
     */
    public function drop(): self
    {
        $this->mode('drop')->execute();

        return $this;
    }

    /**
     * Alter table.
     *
     * @return $this
     */
    public function alter(): self
    {
        $this->mode('alter')->execute();

        return $this;
    }

    /**
     * Rename table.
     *
     * @return $this
     */
    public function rename(): self
    {
        $this->mode('rename')->execute();

        return $this;
    }

    /**
     * @deprecated use Migration::run instead
     */
    public function migrate(): string
    {
        return $this->run();
    }

    /**
     * Will read current schema and consult current 'field' arguments, to see if they are matched.
     * If table does not exist, will invoke ->create. If table does exist, then it will execute
     * methods ->newField(), ->dropField() or ->alterField() as needed, then call ->alter().
     *
     * @return string Returns short textual info for logging purposes
     */
    public function run(): string
    {
        $changes = $added = $altered = $dropped = 0;

        // We use this to read fields from SQL
        $migration2 = new static($this->connection);

        if (!$migration2->importTable($this['table'])) {
            // should probably use custom exception class here
            $this->create();

            return 'created new table';
        }

        $old = $migration2->_getFields();
        $new = $this->_getFields();

        // add new fields or update existing ones
        foreach ($new as $field => $options) {
            // never update ID field (sadly hard-coded field name)
            if ($field === 'id') {
                continue;
            }

            if (isset($old[$field])) {
                // compare options and if needed alter field
                // @todo add more options here like 'len'
                if (array_key_exists('type', $old[$field]) && array_key_exists('type', $options)) {
                    $oldSQLFieldType = $this->getSqlFieldType($old[$field]['type']);
                    $newSQLFieldType = $this->getSqlFieldType($options['type']);
                    if ($oldSQLFieldType !== $newSQLFieldType) {
                        $this->alterField($field, $options);
                        ++$altered;
                        ++$changes;
                    }
                }

                unset($old[$field]);
            } else {
                // new field, so let's just add it
                $this->newField($field, $options);
                ++$added;
                ++$changes;
            }
        }

        // remaining old fields - drop them
        foreach ($old as $field => $options) {
            // never delete ID field (sadly hard-coded field name)
            if ($field === 'id') {
                continue;
            }

            $this->dropField($field);
            ++$dropped;
            ++$changes;
        }

        if ($changes) {
            $this->alter();

            return 'added ' . $added . ' field' . ($added === 1 ? '' : 's') . ', ' .
                'changed ' . $altered . ' field' . ($altered === 1 ? '' : 's') . ' and ' .
                'deleted ' . $dropped . ' field' . ($dropped === 1 ? '' : 's');
        }

        return 'no changes';
    }

    /**
     * Renders statement.
     */
    public function _render_statements(): string
    {
        $result = [];

        if (isset($this->args['dropField'])) {
            foreach ($this->args['dropField'] as $field => $junk) {
                $result[] = 'drop column ' . $this->_escape($field);
            }
        }

        if (isset($this->args['newField'])) {
            foreach ($this->args['newField'] as $field => $option) {
                $result[] = 'add column ' . $this->_render_one_field($field, $option);
            }
        }

        if (isset($this->args['alterField'])) {
            foreach ($this->args['alterField'] as $field => $option) {
                $result[] = 'change column ' . $this->_escape($field) . ' ' . $this->_render_one_field($field, $option);
            }
        }

        return implode(', ', $result);
    }

    /**
     * Create rough model from current set of $this->args['fields']. This is not
     * ideal solution but is designed as a drop-in solution.
     *
     * @param Persistence $persistence
     * @param string      $table
     */
    public function createModel($persistence, $table = null): Model
    {
        $this['table'] = $table ?? $this['table'];

        $m = new Model([$persistence, 'table' => $this['table']]);

        $this->importTable($this['table']);

        foreach ($this->_getFields() as $field => $options) {
            if ($field === 'id') {
                continue;
            }

            if (is_object($options)) {
                continue;
            }

            $defaults = [];

            if ($options['type']) {
                $defaults['type'] = $options['type'];
            }
            $m->addField($field, $defaults);
        }

        return $m;
    }

    /**
     * Sets newField argument.
     *
     * @param string $field
     * @param array  $options
     *
     * @return $this
     */
    public function newField($field, $options = []): self
    {
        $this->_set_args('newField', $field, $options);

        return $this;
    }

    /**
     * Sets alterField argument.
     *
     * @param array $options
     *
     * @return $this
     */
    public function alterField(string $field, $options = []): self
    {
        $this->_set_args('alterField', $field, $options);

        return $this;
    }

    /**
     * Sets dropField argument.
     *
     * @param string $field
     *
     * @return $this
     */
    public function dropField($field): self
    {
        $this->_set_args('dropField', $field, true);

        return $this;
    }

    /**
     * Return database table descriptions.
     * DB engine specific.
     */
    abstract public function describeTable(string $table);

    /**
     * Convert SQL field types to Agile Data field types.
     *
     * @param string $type SQL field type
     */
    public function getModelFieldType(string $type): ?string
    {
        // remove parenthesis and type options
        $type = trim(preg_replace('~(\(| ).*~', '', strtolower($type)));

        $map = array_replace($this->defaultMapToAgile, $this->mapToAgile);
        $a = array_key_exists($type, $map) ? $map[$type] : $map[0];

        return $a[0];
    }

    /**
     * Convert Agile Data field types to SQL field types.
     *
     * @param string|null $type    Agile Data field type
     * @param array       $options More options
     */
    public function getSqlFieldType(?string $type, array $options = []): ?string
    {
        if ($type !== null) {
            $type = strtolower($type);
        }

        $map = array_replace($this->defaultMapToPersistence, $this->mapToPersistence);
        $a = array_key_exists($type, $map) ? $map[$type] : $map[0];

        $res = $a[0];
        if (count($a) > 1) {
            $res .= ' (' . implode(',', array_slice($a, 1)) . ')';
        }

        if (!empty($options['ref_type']) && $options['ref_type'] !== self::REF_TYPE_NONE && $type === 'integer'
            && !($this instanceof Migration\Postgresql)) {
            $res .= ' unsigned';
        }

        if (
            !empty($options['mandatory'])
                || (!empty($options['ref_type']) && $options['ref_type'] === self::REF_TYPE_PRIMARY)
        ) {
            $res .= ' not null';
        }

        if (!empty($options['ref_type']) && $options['ref_type'] === self::REF_TYPE_PRIMARY) {
            $res .= ' ' . $this->primary_key_expr;
        }

        return $res;
    }

    /**
     * Import fields from database into migration field config.
     */
    public function importTable(string $table): bool
    {
        $this->table($table);
        $has_fields = false;
        foreach ($this->describeTable($table) as $row) {
            $has_fields = true;

            $type = $this->getModelFieldType($row['type']);
            $ref_type = $row['pk'] ? self::REF_TYPE_PRIMARY : self::REF_TYPE_NONE;

            $options = [
                'type' => $type,
                'ref_type' => $ref_type,
            ];

            $this->field($row['name'], $options);
        }

        return $has_fields;
    }

    /**
     * Sets table name.
     *
     * @param string $table
     *
     * @return $this
     */
    public function table($table)
    {
        $this['table'] = $table;

        return $this;
    }

    /**
     * Sets old table name.
     *
     * @param string $table
     *
     * @return $this
     */
    public function old_table($old_table)
    {
        $this['old_table'] = $old_table;

        return $this;
    }

    /**
     * Add field in template.
     *
     * @param string $name
     * @param array  $options
     *
     * @return $this
     */
    public function field($name, $options = [])
    {
        // save field in args
        $this->_set_args('field', $name, $options);

        return $this;
    }

    /**
     * Add ID field in template.
     *
     * @param string $name
     *
     * @return $this
     */
    public function id($name = null)
    {
        $options = [
            'type' => 'integer',
            'ref_type' => self::REF_TYPE_PRIMARY,
        ];

        $this->field($name ?? 'id', $options);

        return $this;
    }

    /**
     * Render "field" template.
     *
     * @return string
     */
    public function _render_field()
    {
        $ret = [];

        if (!$this->args['field']) {
            throw new Exception('No fields defined for table');
        }

        foreach ($this->args['field'] as $field => $options) {
            if ($options instanceof Expression) {
                $ret[] = $this->_escape($field) . ' ' . $this->_consume($options);

                continue;
            }

            $ret[] = $this->_render_one_field($field, $options);
        }

        return implode(',', $ret);
    }

    /**
     * Renders one field.
     */
    protected function _render_one_field(string $field, array $options): string
    {
        $name = $options['name'] ?? $field;
        $type = $this->getSqlFieldType($options['type'] ?? null, $options);

        return $this->_escape($name) . ' ' . $type;
    }

    /**
     * Return fields.
     */
    public function _getFields(): array
    {
        return $this->args['field'];
    }

    /**
     * Sets value in args array. Doesn't allow duplicate aliases.
     *
     * @param string $what  Where to set it - table|field
     * @param string $alias Alias name
     * @param mixed  $value Value to set in args array
     */
    protected function _set_args(string $what, string $alias, $value)
    {
        // save value in args
        if ($alias === null) {
            $this->args[$what][] = $value;
        } else {
            // don't allow multiple values with same alias
            if (isset($this->args[$what][$alias])) {
                throw (new Exception(ucfirst($what) . ' alias should be unique'))
                    ->addMoreInfo('alias', $alias);
            }

            $this->args[$what][$alias] = $value;
        }
    }
}
