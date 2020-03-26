<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\dsql\Connection;
use atk4\dsql\Expression;
use atk4\dsql\Query;

/**
 * Persistence\SQL class.
 */
class SQL extends Persistence
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
    public $_default_seed_addField = ['\atk4\data\Field_SQL'];

    /**
     * Default class when adding hasOne field.
     *
     * @var string
     */
    public $_default_seed_hasOne = ['\atk4\data\Reference\HasOne_SQL'];

    /**
     * Default class when adding hasMany field.
     *
     * @var string
     */
    public $_default_seed_hasMany = null; //'atk4\data\Reference\HasMany';

    /**
     * Default class when adding Expression field.
     *
     * @var string
     */
    public $_default_seed_addExpression = ['\atk4\data\Field_SQL_Expression'];

    /**
     * Default class when adding join.
     *
     * @var string
     */
    public $_default_seed_join = ['\atk4\data\Join\SQL'];

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
            throw new Exception([
                'You can only use Persistance_SQL with Connection class from atk4\dsql',
                'connection' => $connection,
            ]);
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
    public function disconnect()
    {
        parent::disconnect();

        unset($this->connection);
    }

    /**
     * Returns Query instance.
     *
     * @return Query
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
     * @param callable $f
     *
     * @return mixed
     */
    public function atomic($f)
    {
        return $this->connection->atomic($f);
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $m        Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @return Model
     */
    public function add($m, $defaults = []): Model
    {
        // Use our own classes for fields, references and expressions unless
        // $defaults specify them otherwise.
        $defaults = array_merge([
            '_default_seed_addField'      => $this->_default_seed_addField,
            '_default_seed_hasOne'        => $this->_default_seed_hasOne,
            '_default_seed_hasMany'       => $this->_default_seed_hasMany,
            '_default_seed_addExpression' => $this->_default_seed_addExpression,
            '_default_seed_join'          => $this->_default_seed_join,
        ], $defaults);

        $m = parent::add($m, $defaults);

        if (!isset($m->table) || (!is_string($m->table) && $m->table !== false)) {
            throw new Exception([
                'Property $table must be specified for a model',
                'model' => $m,
            ]);
        }

        // When we work without table, we can't have any IDs
        if ($m->table === false) {
            $m->removeField($m->id_field);
            $m->addExpression($m->id_field, '1');
            //} else {
            // SQL databases use ID of int by default
            //$m->getField($m->id_field)->type = 'integer';
        }

        // Sequence support
        if ($m->sequence && $id_field = $m->hasField($m->id_field)) {
            $id_field->default = $this->dsql()->mode('seq_nextval')->sequence($m->sequence);
        }

        return $m;
    }

    /**
     * Initialize persistence.
     *
     * @param Model $m
     */
    protected function initPersistence(Model $m)
    {
        $m->addMethod('expr', $this);
        $m->addMethod('dsql', $this);
        $m->addMethod('exprNow', $this);
    }

    /**
     * Creates new Expression object from expression string.
     *
     * @param Model $m
     * @param mixed $expr
     * @param array $args
     *
     * @return Expression
     */
    public function expr(Model $m, $expr, $args = []): Expression
    {
        if (!is_string($expr)) {
            return $this->connection->expr($expr, $args);
        }
        preg_replace_callback(
            '/\[[a-z0-9_]*\]|{[a-z0-9_]*}/i',
            function ($matches) use (&$args, $m) {
                $identifier = substr($matches[0], 1, -1);
                if ($identifier && !isset($args[$identifier])) {
                    $args[$identifier] = $m->getField($identifier);
                }

                return $matches[0];
            },
            $expr
        );

        return $this->connection->expr($expr, $args);
    }

    /**
     * Creates new Query object with current_timestamp(precision) expression.
     *
     * @param Model $m
     * @param int   $precision
     *
     * @return Query
     */
    public function exprNow($precision = null)
    {
        return $this->connection->dsql()->exprNow($precision);
    }

    /**
     * Initializes base query for model $m.
     *
     * @param Model $m
     *
     * @return Query
     */
    public function initQuery(Model $m): Query
    {
        $d = $m->persistence_data['dsql'] = $this->dsql();

        if ($m->table) {
            if (isset($m->table_alias)) {
                $d->table($m->table, $m->table_alias);
            } else {
                $d->table($m->table);
            }
        }

        // add With cursors
        $this->initWithCursors($m, $d);

        return $d;
    }

    /**
     * Initializes WITH cursors.
     *
     * @param Model $m
     * @param Query $q
     */
    public function initWithCursors(Model $m, Query $q)
    {
        if (!$m->with) {
            return;
        }

        foreach ($m->with as $alias => ['model'=>$model, 'mapping'=>$mapping, 'recursive'=>$recursive]) {
            // prepare field names
            $fields_from = $fields_to = [];
            foreach ($mapping as $from => $to) {
                $fields_from[] = is_int($from) ? $to : $from;
                $fields_to[] = $to;
            }

            // prepare sub-query
            if ($fields_from) {
                $model->onlyFields($fields_from);
            }
            // 2nd parameter here strictly define which fields should be selected
            // as result system fields will not be added if they are not requested
            $sub_q = $model->action('select', [$fields_from]);

            // add With cursor
            $q->with($sub_q, $alias, $fields_to ?: null, $recursive);
        }
    }

    /**
     * Adds Field in Query.
     *
     * @param Query $q
     * @param Field $field
     */
    public function initField(Query $q, Field $field)
    {
        if ($field->useAlias()) {
            $q->field($field, $field->short_name);
        } else {
            $q->field($field);
        }
    }

    /**
     * Adds model fields in Query.
     *
     * @param Model            $m
     * @param Query            $q
     * @param array|null|false $fields
     */
    public function initQueryFields(Model $m, $q, $fields = null)
    {
        // do nothing on purpose
        if ($fields === false) {
            return;
        }

        // init fields
        if (is_array($fields)) {

            // Set of fields is strictly defined for purposes of export,
            // so we will ignore even system fields.
            foreach ($fields as $field) {
                $this->initField($q, $m->getField($field));
            }
        } elseif ($m->only_fields) {
            $added_fields = [];

            // Add requested fields first
            foreach ($m->only_fields as $field) {
                $f_object = $m->getField($field);
                if ($f_object->never_persist) {
                    continue;
                }
                $this->initField($q, $f_object);
                $added_fields[$field] = true;
            }

            // now add system fields, if they were not added
            foreach ($m->getFields() as $field => $f_object) {
                if ($f_object->never_persist) {
                    continue;
                }
                if ($f_object->system && !isset($added_fields[$field])) {
                    $this->initField($q, $f_object);
                }
            }
        } else {
            foreach ($m->getFields() as $field => $f_object) {
                if ($f_object->never_persist) {
                    continue;
                }
                $this->initField($q, $f_object);
            }
        }
    }

    /**
     * Will set limit defined inside $m onto query $q.
     *
     * @param Model $m
     * @param Query $q
     */
    protected function setLimitOrder(Model $m, Query $q)
    {
        // set limit
        if ($m->limit && ($m->limit[0] || $m->limit[1])) {
            if ($m->limit[0] === null) {
                // This is max number which is allowed in MySQL server.
                // But be aware, that PDO will downgrade this number even lower probably because
                // in LIMIT it expects numeric value and converts string (we set float values as PDO_PARAM_STR)
                // back to PDO_PARAM_INT which is goes back to max int value specific server can have.
                // On my Win10,64-bit it is 2147483647, on Travis server 9223372036854775807 etc.
                $m->limit[0] = '18446744073709551615';
            }
            $q->limit($m->limit[0], $m->limit[1]);
        }

        // set order
        if ($m->order) {
            foreach ($m->order as $o) {
                if ($o[0] instanceof Expression) {
                    $q->order($o[0], $o[1]);
                } elseif (is_string($o[0])) {
                    $q->order($m->getField($o[0]), $o[1]);
                } else {
                    throw new Exception(['Unsupported order parameter', 'model' => $m, 'field' => $o[0]]);
                }
            }
        }
    }

    /**
     * Will apply conditions defined inside $m onto query $q.
     *
     * @param Model $m
     * @param Query $q
     *
     * @return Query
     */
    public function initQueryConditions(Model $m, Query $q): Query
    {
        if (!isset($m->conditions)) {
            // no conditions are set in the model
            return $q;
        }

        foreach ($m->conditions as $cond) {

            // Options here are:
            // count($cond) == 1, we will pass the only
            // parameter inside where()

            if (count($cond) == 1) {

                // OR conditions
                if (is_array($cond[0])) {
                    foreach ($cond[0] as &$row) {
                        if (is_string($row[0])) {
                            $row[0] = $m->getField($row[0]);
                        }

                        if ($row[0] instanceof Field) {
                            $valueKey = count($row) == 2 ? 1 : 2;
                            $row[$valueKey] = $this->typecastSaveField($row[0], $row[$valueKey]);
                        }
                    }
                }

                $q->where($cond[0]);
                continue;
            }

            if (is_string($cond[0])) {
                $cond[0] = $m->getField($cond[0]);
            }

            if (count($cond) == 2) {
                if ($cond[0] instanceof Field) {
                    $cond[1] = $this->typecastSaveField($cond[0], $cond[1]);
                }
                $q->where($cond[0], $cond[1]);
            } else {
                // "like" or "regexp" conditions do not need typecasting to field type!
                if ($cond[0] instanceof Field && !in_array(strtolower($cond[1]), ['like', 'regexp'])) {
                    $cond[2] = $this->typecastSaveField($cond[0], $cond[2]);
                }
                $q->where($cond[0], $cond[1], $cond[2]);
            }
        }

        return $q;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $f, $value)
    {
        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($f->type) {
        case 'boolean':
            // if enum is not set, then simply cast value to integer
            if (!isset($f->enum) || !$f->enum) {
                $v = (int) $v;
                break;
            }

            // if enum is set, first lets see if it matches one of those precisely
            if ($v === $f->enum[1]) {
                $v = true;
            } elseif ($v === $f->enum[0]) {
                $v = false;
            }

            // finally, convert into appropriate value
            $v = $v ? $f->enum[1] : $f->enum[0];
            break;
        case 'date':
        case 'datetime':
        case 'time':
            $dt_class = isset($f->dateTimeClass) ? $f->dateTimeClass : 'DateTime';
            $tz_class = isset($f->dateTimeZoneClass) ? $f->dateTimeZoneClass : 'DateTimeZone';

            if ($v instanceof $dt_class || $v instanceof \DateTimeInterface) {
                $format = ['date' => 'Y-m-d', 'datetime' => 'Y-m-d H:i:s.u', 'time' => 'H:i:s.u'];
                $format = $f->persist_format ?: $format[$f->type];

                // datetime only - set to persisting timezone
                if ($f->type == 'datetime' && isset($f->persist_timezone)) {
                    $v = new \DateTime($v->format('Y-m-d H:i:s.u'), $v->getTimezone());
                    $v->setTimezone(new $tz_class($f->persist_timezone));
                }
                $v = $v->format($format);
            }
            break;
        case 'array':
        case 'object':
            // don't encode if we already use some kind of serialization
            $v = $f->serialize ? $v : $this->jsonEncode($f, $v);
            break;
        }

        return $v;
    }

    /**
     * This is the actual field typecasting, which you can override in your
     * persistence to implement necessary typecasting.
     *
     * @param Field $f
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $f, $value)
    {
        // LOB fields return resource stream
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        // work only on copied value not real one !!!
        $v = is_object($value) ? clone $value : $value;

        switch ($f->type) {
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
            $v = round($v, 4);
            break;
        case 'boolean':
            if (isset($f->enum) && is_array($f->enum)) {
                if (isset($f->enum[0]) && $v == $f->enum[0]) {
                    $v = false;
                } elseif (isset($f->enum[1]) && $v == $f->enum[1]) {
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
            $dt_class = isset($f->dateTimeClass) ? $f->dateTimeClass : 'DateTime';
            $tz_class = isset($f->dateTimeZoneClass) ? $f->dateTimeZoneClass : 'DateTimeZone';

            if (is_numeric($v)) {
                $v = new $dt_class('@'.$v);
            } elseif (is_string($v)) {
                // ! symbol in date format is essential here to remove time part of DateTime - don't remove, this is not a bug
                $format = ['date' => '+!Y-m-d', 'datetime' => '+!Y-m-d H:i:s', 'time' => '+!H:i:s'];
                if ($f->persist_format) {
                    $format = $f->persist_format;
                } else {
                    $format = $format[$f->type];
                    if (strpos($v, '.') !== false) { // time possibly with microseconds, otherwise invalid format
                        $format = preg_replace('~(?<=H:i:s)(?![. ]*u)~', '.u', $format);
                    }
                }

                // datetime only - set from persisting timezone
                if ($f->type == 'datetime' && isset($f->persist_timezone)) {
                    $v = $dt_class::createFromFormat($format, $v, new $tz_class($f->persist_timezone));
                    if ($v !== false) {
                        $v->setTimezone(new $tz_class(date_default_timezone_get()));
                    }
                } else {
                    $v = $dt_class::createFromFormat($format, $v);
                }

                if ($v === false) {
                    throw new Exception(['Incorrectly formatted date/time', 'format' => $format, 'value' => $value, 'field' => $f]);
                }

                // need to cast here because DateTime::createFromFormat returns DateTime object not $dt_class
                // this is what Carbon::instance(DateTime $dt) method does for example
                if ($dt_class != 'DateTime') {
                    $v = new $dt_class($v->format('Y-m-d H:i:s.u'), $v->getTimezone());
                }
            }
            break;
        case 'array':
            // don't decode if we already use some kind of serialization
            $v = $f->serialize ? $v : $this->jsonDecode($f, $v, true);
            break;
        case 'object':
            // don't decode if we already use some kind of serialization
            $v = $f->serialize ? $v : $this->jsonDecode($f, $v, false);
            break;
        }

        return $v;
    }

    /**
     * Executing $model->action('update') will call this method.
     *
     * @param Model  $m
     * @param string $type
     * @param array  $args
     *
     * @return Query
     */
    public function action(Model $m, $type, $args = [])
    {
        if (!is_array($args)) {
            throw new Exception([
                '$args must be an array',
                'args' => $args,
            ]);
        }

        $q = $this->initQuery($m);
        switch ($type) {
            case 'insert':
                return $q->mode('insert');
                // cannot apply conditions now

            case 'update':
                $q->mode('update');
                break;

            case 'delete':
                $q->mode('delete');
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q, $type]);

                return $q;

            case 'select':
                $this->initQueryFields($m, $q, isset($args[0]) ? $args[0] : null);
                break;

            case 'count':
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q]);
                if (isset($args['alias'])) {
                    $q->reset('field')->field('count(*)', $args['alias']);
                } else {
                    $q->reset('field')->field('count(*)');
                }

                return $q;

            case 'field':
                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $type,
                    ]);
                }

                $field = is_string($args[0]) ? $m->getField($args[0]) : $args[0];
                $m->hook('initSelectQuery', [$q, $type]);
                if (isset($args['alias'])) {
                    $q->reset('field')->field($field, $args['alias']);
                } elseif ($field instanceof Field_SQL_Expression) {
                    $q->reset('field')->field($field, $field->short_name);
                } else {
                    $q->reset('field')->field($field);
                }
                $this->initQueryConditions($m, $q);
                $this->setLimitOrder($m, $q);

                return $q;

            case 'fx':
            case 'fx0':
                if (!isset($args[0], $args[1])) {
                    throw new Exception([
                        'fx action needs 2 arguments, eg: ["sum", "amount"]',
                        'action' => $type,
                    ]);
                }

                $fx = $args[0];
                $field = is_string($args[1]) ? $m->getField($args[1]) : $args[1];
                $this->initQueryConditions($m, $q);
                $m->hook('initSelectQuery', [$q, $type]);

                if ($type == 'fx') {
                    $expr = "$fx([])";
                } else {
                    $expr = "coalesce($fx([]), 0)";
                }

                if (isset($args['alias'])) {
                    $q->reset('field')->field($q->expr($expr, [$field]), $args['alias']);
                } elseif ($field instanceof Field_SQL_Expression) {
                    $q->reset('field')->field($q->expr($expr, [$field]), $fx.'_'.$field->short_name);
                } else {
                    $q->reset('field')->field($q->expr($expr, [$field]));
                }

                return $q;

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'type' => $type,
                ]);
        }

        $this->initQueryConditions($m, $q);
        $this->setLimitOrder($m, $q);
        $m->hook('initSelectQuery', [$q, $type]);

        return $q;
    }

    /**
     * Tries to load data record, but will not fail if record can't be loaded.
     *
     * @param Model $m
     * @param mixed $id
     *
     * @return array
     */
    public function tryLoad(Model $m, $id)
    {
        if (!$m->id_field) {
            throw new Exception(['Unable to load field by "id" when Model->id_field is not defined.', 'id'=>$id]);
        }

        $load = $m->action('select');
        $load->where($m->getField($m->id_field), $id);
        $load->limit(1);

        // execute action
        try {
            $data = $this->typecastLoadRow($m, $load->getRow());
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to load due to query error',
                'query'      => $load->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        if (!$data) {
            return;
        }

        if (!isset($data[$m->id_field]) || is_null($data[$m->id_field])) {
            throw new Exception([
                'Model uses "id_field" but it wasn\'t available in the database',
                'model'       => $m,
                'id_field'    => $m->id_field,
                'id'          => $id,
                'data'        => $data,
            ]);
        }

        $m->id = $data[$m->id_field];

        return $data;
    }

    /**
     * Loads a record from model and returns a associative array.
     *
     * @param Model $m
     * @param mixed $id
     *
     * @return array
     */
    public function load(Model $m, $id)
    {
        $data = $this->tryLoad($m, $id);

        if (!$data) {
            throw new Exception([
                'Record was not found',
                'model'      => $m,
                'id'         => $id,
                'conditions' => $m->conditions,
            ], 404);
        }

        return $data;
    }

    /**
     * Tries to load any one record.
     *
     * @param Model $m
     *
     * @return array
     */
    public function tryLoadAny(Model $m)
    {
        $load = $m->action('select');
        $load->limit(1);

        // execute action
        try {
            $data = $this->typecastLoadRow($m, $load->getRow());
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to load due to query error',
                'query'      => $load->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        if (!$data) {
            return;
        }

        if ($m->id_field) {

            // If id_field is not set, model will be read-only
            if (isset($data[$m->id_field])) {
                $m->id = $data[$m->id_field];
            } else {
                throw new Exception([
                    'Model uses "id_field" but it was not available in the database',
                    'model'       => $m,
                    'id_field'    => $m->id_field,
                    'data'        => $data,
                ]);
            }
        }

        return $data;
    }

    /**
     * Loads any one record.
     *
     * @param Model $m
     *
     * @return array
     */
    public function loadAny(Model $m)
    {
        $data = $this->tryLoadAny($m);

        if (!$data) {
            throw new Exception([
                'No matching records were found',
                'model'      => $m,
                'conditions' => $m->conditions,
            ], 404);
        }

        return $data;
    }

    /**
     * Inserts record in database and returns new record ID.
     *
     * @param Model $m
     * @param array $data
     *
     * @return mixed
     */
    public function insert(Model $m, $data)
    {
        $insert = $m->action('insert');

        // don't set id field at all if it's NULL
        if ($m->id_field && array_key_exists($m->id_field, $data) && $data[$m->id_field] === null) {
            unset($data[$m->id_field]);
        }

        $insert->set($this->typecastSaveRow($m, $data));

        $st = null;

        try {
            $m->hook('beforeInsertQuery', [$insert]);
            $st = $insert->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to execute insert query',
                'query'      => $insert->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        $m->hook('afterInsertQuery', [$insert, $st]);

        return $m->lastInsertID();
    }

    /**
     * Export all DataSet.
     *
     * @param Model      $m
     * @param array|null $fields
     * @param bool       $typecast_data Should we typecast exported data
     *
     * @return array
     */
    public function export(Model $m, $fields = null, $typecast_data = true)
    {
        $data = $m->action('select', [$fields])->get();

        if ($typecast_data) {
            $data = array_map(function ($r) use ($m) {
                return $this->typecastLoadRow($m, $r);
            }, $data);
        }

        return $data;
    }

    /**
     * Prepare iterator.
     *
     * @param Model $m
     *
     * @return \PDOStatement
     */
    public function prepareIterator(Model $m)
    {
        try {
            $export = $m->action('select');

            return $export->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to execute iteration query',
                'query'      => $export->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }
    }

    /**
     * Updates record in database.
     *
     * @param Model $m
     * @param mixed $id
     * @param array $data
     */
    public function update(Model $m, $id, $data)
    {
        if (!$m->id_field) {
            throw new Exception(['id_field of a model is not set. Unable to update record.']);
        }

        $update = $this->initQuery($m);
        $update->mode('update');

        $data = $this->typecastSaveRow($m, $data);

        // only apply fields that has been modified
        $update->set($data);
        $update->where($m->getField($m->id_field), $id);

        $st = null;

        try {
            $m->hook('beforeUpdateQuery', [$update]);
            if ($data) {
                $st = $update->execute();
            }
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to update due to query error',
                'query'      => $update->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }

        if ($m->id_field && isset($data[$m->id_field]) && $m->dirty[$m->id_field]) {
            // ID was changed
            $m->id = $data[$m->id_field];
        }

        $m->hook('afterUpdateQuery', [$update, $st]);

        // if any rows were updated in database, and we had expressions, reload
        if ($m->reload_after_save === true && (!$st || $st->rowCount())) {
            $d = $m->dirty;
            $m->reload();
            $m->_dirty_after_reload = $m->dirty;
            $m->dirty = $d;
        }
    }

    /**
     * Deletes record from database.
     *
     * @param Model $m
     * @param mixed $id
     */
    public function delete(Model $m, $id)
    {
        if (!$m->id_field) {
            throw new Exception(['id_field of a model is not set. Unable to delete record.']);
        }

        $delete = $this->initQuery($m);
        $delete->mode('delete');
        $delete->where($m->id_field, $id);
        $m->hook('beforeDeleteQuery', [$delete]);

        try {
            $delete->execute();
        } catch (\PDOException $e) {
            throw new Exception([
                'Unable to delete due to query error',
                'query'      => $delete->getDebugQuery(false),
                'model'      => $m,
                'conditions' => $m->conditions,
            ], null, $e);
        }
    }

    public function getFieldSQLExpression(Field $field, Expression $expression)
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

        // If our Model has expr() method (inherited from Persistence_SQL) then use it
        if ($field->owner->hasMethod('expr')) {
            $field->owner->expr($mask, $prop);
        }

        // Otherwise call method from expression
        return $expression->expr($mask, $prop);
    }
}
