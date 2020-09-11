<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\dsql\Connection;
use atk4\dsql\Expression;
use atk4\dsql\Query;

/**
 * Persistence\Sql class.
 */
class Sql extends Persistence
{
    /**
     * Connection object.
     *
     * @var \atk4\dsql\Connection
     */
    public $connection;

    /**
     * Default class when adding new field.
     *
     * @var string
     */
    public $_default_seed_addField = [\atk4\data\FieldSql::class];

    /**
     * Default class when adding hasOne field.
     *
     * @var string
     */
    public $_default_seed_hasOne = [\atk4\data\Reference\HasOneSql::class];

    /**
     * Default class when adding hasMany field.
     *
     * @var string
     */
    public $_default_seed_hasMany; // [\atk4\data\Reference\HasMany::class];

    /**
     * Default class when adding Expression field.
     *
     * @var string
     */
    public $_default_seed_addExpression = [FieldSqlExpression::class];

    /**
     * Default class when adding join.
     *
     * @var string
     */
    public $_default_seed_join = [Sql\Join::class];

    /**
     * Constructor.
     *
     * @param Connection|string $connection
     * @param string            $user
     * @param string            $password
     * @param array             $args
     */
    public function __construct($connection, $user = null, $password = null, $args = [])
    {
        if ($connection instanceof \atk4\dsql\Connection) {
            $this->connection = $connection;

            return;
        }

        if (is_object($connection)) {
            throw (new Exception('You can only use Persistance_SQL with Connection class from atk4\dsql'))
                ->addMoreInfo('connection', $connection);
        }

        // attempt to connect.
        $this->connection = \atk4\dsql\Connection::connect(
            $connection,
            $user,
            $password,
            $args
        );
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
        parent::disconnect();

        $this->connection = null;
    }

    /**
     * Returns Query instance.
     */
    public function dsql(): Query
    {
        return $this->connection->dsql();
    }

    /**
     * Atomic executes operations within one begin/end transaction, so if
     * the code inside callback will fail, then all of the transaction
     * will be also rolled back.
     *
     * @return mixed
     */
    public function atomic(\Closure $fx)
    {
        return $this->connection->atomic($fx);
    }

    /**
     * {@inheritdoc}
     */
    public function add(Model $model, array $defaults = []): Model
    {
        // Use our own classes for fields, references and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_seed_addField' => $this->_default_seed_addField,
            '_default_seed_hasOne' => $this->_default_seed_hasOne,
            '_default_seed_hasMany' => $this->_default_seed_hasMany,
            '_default_seed_addExpression' => $this->_default_seed_addExpression,
            '_default_seed_join' => $this->_default_seed_join,
        ], $defaults);

        $model = parent::add($model, $defaults);

        if (!isset($model->table) || (!is_string($model->table) && $model->table !== false)) {
            throw (new Exception('Property $table must be specified for a model'))
                ->addMoreInfo('model', $model);
        }

        // When we work without table, we can't have any IDs
        if ($model->table === false) {
            $model->removeField($model->id_field);
            $model->addExpression($model->id_field, '1');
            //} else {
            // SQL databases use ID of int by default
            //$m->getField($m->id_field)->type = 'integer';
        }

        // Sequence support
        if ($model->sequence && $model->hasField($model->id_field)) {
            $model->getField($model->id_field)->default = $this->dsql()->mode('seq_nextval')->sequence($model->sequence);
        }

        return $model;
    }

    /**
     * Initialize persistence.
     */
    protected function initPersistence(Model $model)
    {
        parent::initPersistence($model);

        $model->addMethod('expr', \Closure::fromCallable([$this, 'expr']));
        $model->addMethod('dsql', \Closure::fromCallable([$this, 'dsql']));
        $model->addMethod('exprNow', \Closure::fromCallable([$this, 'exprNow']));
    }

    /**
     * Creates new Expression object from expression string.
     *
     * @param mixed $expr
     * @param array $args
     */
    public function expr(Model $model, $expr, $args = []): Expression
    {
        if (!is_string($expr)) {
            return $this->connection->expr($expr, $args);
        }
        preg_replace_callback(
            '/\[[a-z0-9_]*\]|{[a-z0-9_]*}/i',
            function ($matches) use (&$args, $model) {
                $identifier = substr($matches[0], 1, -1);
                if ($identifier && !isset($args[$identifier])) {
                    $args[$identifier] = $model->getField($identifier);
                }

                return $matches[0];
            },
            $expr
        );

        return $this->connection->expr($expr, $args);
    }

    /**
     * Creates new Query object with current_timestamp(precision) expression.
     */
    public function exprNow(int $precision = null): Expression
    {
        return $this->connection->dsql()->exprNow($precision);
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $field, $value)
    {
        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($field->type) {
            case 'boolean':
                // if enum is not set, then simply cast value to integer
                if (!isset($field->enum) || !$field->enum) {
                    $v = (int) $v;

                    break;
                }

                // if enum is set, first lets see if it matches one of those precisely
                if ($v === $field->enum[1]) {
                    $v = true;
                } elseif ($v === $field->enum[0]) {
                    $v = false;
                }

                // finally, convert into appropriate value
                $v = $v ? $field->enum[1] : $field->enum[0];

                break;
            case 'date':
            case 'datetime':
            case 'time':
                $dt_class = $field->dateTimeClass ?? \DateTime::class;
                $tz_class = $field->dateTimeZoneClass ?? \DateTimeZone::class;

                if ($v instanceof $dt_class || $v instanceof \DateTimeInterface) {
                    $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s.u', 'time' => 'H:i:s.u'];
                    $format = $field->persist_format ?: $format[$field->type];

                    // datetime only - set to persisting timezone
                    if ($field->type === 'datetime' && isset($field->persist_timezone)) {
                        $v = new \DateTime($v->format('Y-m-d H:i:s.u'), $v->getTimezone());
                        $v->setTimezone(new $tz_class($field->persist_timezone));
                    }
                    $v = $v->format($format);
                }

                break;
            case 'array':
            case 'object':
                // don't encode if we already use some kind of serialization
                $v = $field->serialize ? $v : $this->jsonEncode($field, $v);

                break;
        }

        return $v;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $field, $value)
    {
        // LOB fields return resource stream
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($field->type) {
            case 'string':
            case 'text':
                // do nothing - it's ok as it is
                break;
            case 'integer':
                $v = (int) $v;

                break;
            case 'float':
                $v = (float) $v;

                break;
            case 'money':
                $v = round((float) $v, 4);

                break;
            case 'boolean':
                if (is_array($field->enum ?? null)) {
                    if (isset($field->enum[0]) && $v == $field->enum[0]) {
                        $v = false;
                    } elseif (isset($field->enum[1]) && $v == $field->enum[1]) {
                        $v = true;
                    } else {
                        $v = null;
                    }
                } elseif ($v === '') {
                    $v = null;
                } else {
                    $v = (bool) $v;
                }

                break;
            case 'date':
            case 'datetime':
            case 'time':
                $dt_class = $field->dateTimeClass ?? \DateTime::class;
                $tz_class = $field->dateTimeZoneClass ?? \DateTimeZone::class;

                if (is_numeric($v)) {
                    $v = new $dt_class('@' . $v);
                } elseif (is_string($v)) {
                    // ! symbol in date format is essential here to remove time part of DateTime - don't remove, this is not a bug
                    $format = ['date' => '+!Y-m-d', 'datetime' => '+!Y-m-d H:i:s', 'time' => '+!H:i:s'];
                    if ($field->persist_format) {
                        $format = $field->persist_format;
                    } else {
                        $format = $format[$field->type];
                        if (strpos($v, '.') !== false) { // time possibly with microseconds, otherwise invalid format
                            $format = preg_replace('~(?<=H:i:s)(?![. ]*u)~', '.u', $format);
                        }
                    }

                    // datetime only - set from persisting timezone
                    if ($field->type === 'datetime' && isset($field->persist_timezone)) {
                        $v = $dt_class::createFromFormat($format, $v, new $tz_class($field->persist_timezone));
                        if ($v !== false) {
                            $v->setTimezone(new $tz_class(date_default_timezone_get()));
                        }
                    } else {
                        $v = $dt_class::createFromFormat($format, $v);
                    }

                    if ($v === false) {
                        throw (new Exception('Incorrectly formatted date/time'))
                            ->addMoreInfo('format', $format)
                            ->addMoreInfo('value', $value)
                            ->addMoreInfo('field', $field);
                    }

                    // need to cast here because DateTime::createFromFormat returns DateTime object not $dt_class
                    // this is what Carbon::instance(DateTime $dt) method does for example
                    if ($dt_class !== 'DateTime') {
                        $v = new $dt_class($v->format('Y-m-d H:i:s.u'), $v->getTimezone());
                    }
                }

                break;
            case 'array':
                // don't decode if we already use some kind of serialization
                $v = $field->serialize ? $v : $this->jsonDecode($field, $v, true);

                break;
            case 'object':
                // don't decode if we already use some kind of serialization
                $v = $field->serialize ? $v : $this->jsonDecode($field, $v, false);

                break;
        }

        return $v;
    }

    public function query(Model $model): AbstractQuery
    {
        return new Sql\Query($model, $this);
    }

    public function getFieldSqlExpression(Field $field, Expression $expression)
    {
        if (isset($field->owner->persistence_data['use_table_prefixes'])) {
            $mask = '{{}}.{}';
            $prop = [
                $field->join
                    ? ($field->join->foreign_alias ?: $field->join->short_name)
                    : ($field->owner->table_alias ?: $field->owner->table),
                $field->actual ?: $field->short_name,
            ];
        } else {
            // references set flag use_table_prefixes, so no need to check them here
            $mask = '{}';
            $prop = [
                $field->actual ?: $field->short_name,
            ];
        }

        // If our Model has expr() method (inherited from Persistence\Sql) then use it
        if ($field->owner->hasMethod('expr')) {
            return $field->owner->expr($mask, $prop);
        }

        // Otherwise call method from expression
        return $expression->expr($mask, $prop);
    }

    public function lastInsertId(Model $model): string
    {
        return $this->connection->lastInsertId($this->getIdSequenceName($model));
    }

    protected function syncIdSequence(Model $model): void
    {
        // PostgreSQL sequence must be manually synchronized if a row with explicit ID was inserted
        if ($this->connection instanceof \atk4\dsql\Postgresql\Connection) {
            $this->connection->expr(
                'select setval([], coalesce(max({}), 0) + 1, false) from {}',
                [$this->getIdSequenceName($model), $model->id_field, $model->table]
            )->execute();
        }
    }

    private function getIdSequenceName(Model $model): ?string
    {
        $sequenceName = $model->sequence ?: null;

        if ($sequenceName === null) {
            // PostgreSQL uses sequence internally for PK autoincrement,
            // use default name if not set explicitly
            if ($this->connection instanceof \atk4\dsql\Postgresql\Connection) {
                $sequenceName = $model->table . '_' . $model->id_field . '_seq';
            }
        }

        return $sequenceName;
    }
}
