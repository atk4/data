<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\core\DIContainerTrait;
use atk4\core\InitializerTrait;
use atk4\core\TrackableTrait;

/**
 * Class description?
 */
class Join
{
    use TrackableTrait;
    use InitializerTrait {
        init as _init;
    }
    use DIContainerTrait;

    /**
     * Name of the table (or collection) that can be used to retrieve data from.
     * For SQL, This can also be an expression or sub-select.
     *
     * @var string
     */
    protected $foreign_table;

    /**
     * If $persistence is set, then it's used for loading
     * and storing the values, instead $owner->persistence.
     *
     * @var Persistence
     */
    protected $persistence = null;

    /**
     * ID used by a joined table.
     *
     * @var mixed
     */
    protected $id = null;

    /**
     * Field that is used as native "ID" in the foreign table.
     * When deleting record, this field will be conditioned.
     *
     * ->where($join->id_field, $join->id)->delete();
     *
     * @var string
     */
    protected $id_field = 'id';

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join by passing ['kind'=>'right']
     * as second argument to join().
     *
     * @var string
     */
    protected $kind;

    /**
     * Is our join weak? Weak join will stop you from touching foreign table.
     *
     * @var bool
     */
    protected $weak = false;

    /**
     * Normally the foreign table is saved first, then it's ID is used in the
     * primary table. When deleting, the primary table record is deleted first
     * which is followed by the foreign table record.
     *
     * If you are using the following syntax:
     *
     * $user->join('contact','default_contact_id');
     *
     * Then the ID connecting tables is stored in foreign table and the order
     * of saving and delete needs to be reversed. In this case $reverse
     * will be set to `true`. You can specify value of this property.
     *
     * @var bool
     */
    protected $reverse;

    /**
     * Field to be used for matching inside master field. By default
     * it's $foreign_table.'_id'.
     *
     * @var string
     */
    protected $master_field;

    /**
     * Field to be used for matching in a foreign table. By default
     * it's 'id'.
     *
     * @var string
     */
    protected $foreign_field;

    /**
     * A short symbolic name that will be used as an alias for the joined table.
     *
     * @var string
     */
    public $foreign_alias;

    /**
     * When $prefix is set, then all the fields generated through
     * our wrappers will be automatically prefixed inside the model.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Data which is populated here as the save/insert progresses.
     *
     * @var array
     */
    protected $save_buffer = [];

    /**
     * When join is done on another join.
     *
     * @var Join
     */
    protected $join = null;

    /**
     * Default constructor. Will copy argument into properties.
     *
     * @param array $defaults
     */
    public function __construct($foreign_table = null)
    {
        if (isset($foreign_table)) {
            $this->foreign_table = $foreign_table;
        }
    }

    /**
     * Will use either foreign_alias or create #join_<table>.
     *
     * @return string
     */
    public function getDesiredName()
    {
        return '#join_'.$this->foreign_table;
    }

    /**
     * Initialization.
     */
    public function init()
    {
        $this->_init();

        // handle foreign table containing a dot
        if (is_string($this->foreign_table)
            && strpos($this->foreign_table, '.') !== false
        ) {
            if (!isset($this->reverse)) {
                $this->reverse = true;
                if (isset($this->master_field)) {
                    // both master and foreign fields are set

                    // master_field exists, no we will use that
                    /*
                    if (!is_object($this->master_field)
                        && !$this->owner->hasElement($this->master_field
                    )) {
                     */
                    throw new Exception([
                            'You are trying to link tables on non-id fields. This is not implemented yet',
                            'condition' => $this->owner->table.'.'.$this->master_field.' = '.$this->foreign_table,
                        ]);
                    /*
                    }

                    $this->reverse = 'link';

                     */
                }
            }
            list($this->foreign_table, $this->foreign_field) =
                explode('.', $this->foreign_table, 2);
            if (!$this->master_field) {
                $this->master_field = 'id';
            }
        } else {
            $this->reverse = false;
            $id_field = $this->owner->id_field ?: 'id';
            if (!$this->master_field) {
                $this->master_field = $this->foreign_table.'_'.$id_field;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = $id_field;
            }
        }

        $this->owner->addHook('afterUnload', $this);
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table, but
     * form the join instead.
     *
     * @param string $n
     * @param array  $defaults
     *
     * @return Field
     */
    public function addField($n, $defaults = [])
    {
        $defaults['join'] = $this;

        return $this->owner->addField($this->prefix.$n, $defaults);
    }

    /**
     * Adds multiple fields.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function addFields($fields = [])
    {
        foreach ($fields as $field) {
            if (is_array($field)) {
                $name = $field[0];
                unset($field[0]);
                $this->addField($name, $field);
            } else {
                $this->addField($field);
            }
        }

        return $this;
    }

    /**
     * Adds any object to owner model.
     *
     * @param object|string $object
     * @param array         $defaults
     *
     * @return object
     */
    public function add($object, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['name' => $defaults];
        }

        $defaults['join'] = $this;

        return $this->owner->add($object, $defaults);
    }

    /**
     * Another join will be attached to a current join.
     *
     * @param string $foreign_table
     * @param array  $defaults
     *
     * @return Join
     */
    public function join($foreign_table, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        }
        $defaults['join'] = $this;

        return $this->owner->join($foreign_table, $defaults);
    }

    /**
     * Another leftJoin will be attached to a current join.
     *
     * @param string $foreign_table
     * @param array  $defaults
     *
     * @return Join
     */
    public function leftJoin($foreign_table, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        }
        $defaults['join'] = $this;

        return $this->owner->leftJoin($foreign_table, $defaults);
    }

    /**
     * weakJoin will be attached to a current join.
     *
     * @todo NOT IMPLEMENTED! weakJoin method does not exist!
     *
     * @param array $defaults
     *
     * @return
     */
    public function weakJoin($defaults = [])
    {
        $defaults['join'] = $this;

        return $this->owner->weakJoin($defaults);
    }

    /**
     * Creates reference based on a field from the join.
     *
     * @param Model $model
     * @param array $defaults
     *
     * @return Reference_One
     */
    public function hasOne($model, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['model' => $defaults];
        }
        $defaults['join'] = $this;

        return $this->owner->hasOne($model, $defaults);
    }

    /**
     * Creates reference based on the field from the join.
     *
     * @param Model $model
     * @param array $defaults
     *
     * @return Reference_One
     */
    public function hasMany($model, $defaults = [])
    {
        $defaults = array_merge([
            'our_field'   => $this->id_field,
            'their_field' => $this->table.'_'.$this->id_field,
        ], $defaults);

        return parent::hasMany($model, $defaults);
    }

    /**
     * Wrapper for containsOne that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @param Model $model
     * @param array $defaults
     *
     * @return ???
     */
    public function containsOne($model, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = [$defaults];
        }

        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsOne($model, $defaults);
    }

    /**
     * Wrapper for containsMany that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @param Model $model
     * @param array $defaults
     *
     * @return ???
     */
    public function containsMany($model, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = [$defaults];
        }

        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsMany($model, $defaults);
    }

    /**
     * Will iterate through this model by pulling
     *  - fields
     *  - references
     *  - conditions.
     *
     * and then will apply them locally. If you think that any fields
     * could clash, then use ['prefix'=>'m2'] which will be pre-pended
     * to all the fields. Conditions will be automatically mapped.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @param Model $model
     * @param array $defaults
     */
    public function importModel($model, $defaults = [])
    {
        // not implemented yet !!!
    }

    /**
     * Joins with the primary table of the model and
     * then import all of the data into our model.
     *
     * @todo NOT IMPLEMENTED!
     *
     * @param Model $model
     * @param array $fields
     */
    public function weakJoinModel($model, $fields = [])
    {
        if (!is_object($model)) {
            $model = $this->owner->connection->add($model);
        }
        $j = $this->join($model->table);

        $j->importModel($model);

        return $j;
    }

    /**
     * Set value.
     *
     * @param string $field
     * @param mixed  $value
     *
     * @return $this
     */
    public function set($field, $value)
    {
        $this->save_buffer[$field] = $value;

        return $this;
    }

    /**
     * Clears id and save buffer.
     *
     * @todo CHECK IF WE ACTUALLY USE THIS METHOD SOMEWHERE
     */
    public function afterUnload()
    {
        $this->id = null;
        $this->save_buffer = [];
    }
}
