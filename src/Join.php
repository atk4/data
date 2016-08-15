<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Join
{
    use \atk4\core\TrackableTrait {
        init as _init;
    }
    use \atk4\core\InitializerTrait;

    /**
     * Name of the table (or collection) that can be used to retrieve data from.
     * For SQL, This can also be an expression or sub-select.
     */
    protected $foreign_table;

    /**
     * If $persistence is set, then it's used for loading
     * and storing the values, instead $owner->persistence.
     */
    protected $persistence = null;


    /**
     * ID used by a joined table.
     */
    protected $id = null;

    /**
     * Field that is used as native "ID" in the foreign table.
     * When deleting record, this field will be conditioned.
     *
     * ->where($join->id_field, $join->id)->delete();
     */
    protected $id_field = 'id';

    /**
     * Is our join weak? Weak join will stop you from touching foreign table.
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
     */
    protected $reverse;

    /**
     * Field to be used for matching inside master field. By default
     * it's $foreign_table.'_id'.
     */
    protected $master_field;

    /**
     * Field to be used for matching in a foreign table. By default
     * it's 'id'.
     */
    protected $foreign_field;

    /**
     * When $prefix is set, then all the fields generated through
     * our wrappers will be automatically prefixed inside the model.
     */
    protected $prefix = '';

    /**
     * Data which is populated here as the save/insert progresses.
     */
    protected $save_buffer = [];

    /**
     * When join is done on another join.
     */
    protected $join = null;

    /**
     * default constructor. Will copy argument into properties.
     */
    public function __construct($defaults = [])
    {
        if (isset($defaults[0])) {
            $this->foreign_table = $defaults[0];
            unset($defaults[0]);
        }

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName()
    {
        return '#join_'.$this->foreign_table;
    }

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
                    /*
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
            if (!$this->master_field) {
                $this->master_field = $this->foreign_table.'_id';
            }

            if (!$this->foreign_field) {
                $this->foreign_field = 'id';
            }
        }

        $this->owner->addHook('afterUnload', $this);
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead.
     */
    public function addField($n, $defaults = [])
    {
        $defaults['join'] = $this;

        return $this->owner->addField($this->prefix.$n, $defaults);
    }

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

    public function add($object, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['name' => $defaults];
        }

        $defaults['join'] = $this;

        return $this->owner->add($object, $defaults);
    }

    /**
     * Join will be attached to a current join.
     */
    public function join($foreign_table, $defaults = [])
    {
        if (!is_array($defaults)) {
            $defaults = ['master_field' => $defaults];
        }
        $defaults['join'] = $this;

        return $this->owner->join($foreign_table, $defaults);
    }

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
     */
    public function weakJoin($defaults = [])
    {
        $defaults['join'] = $this;

        return $this->owner->weakJoin($defaults);
    }

    /**
     * creates relation based on a field from the join.
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
     * creates relation based on the field from the join.
     */
    public function hasMany($model, $defaults = [])
    {
        $defaults = array_merge([
            'our_field'   => $this->id_field,
            'their_field' => $this->table.'_id',
        ], $defaults);

        return parent::hasMany($model, $defaults);
    }

    /**
     * wrapper for containsOne that will associate field
     * with join.
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
     * wrapper for containsMany that will associate field
     * with join.
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
     *  - relations
     *  - conditions.
     *
     * and then will apply them locally. Any you think that any fields
     * could clash, then use ['prefix'=>'m2'] which will be pre-pended
     * to all the fields. Conditions will me automatically mapped.
     */
    public function importModel($m, $defaults = [])
    {
    }

    /**
     * Joins with the primary table of the model and
     * then import all of the data into our model.
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

    public function set($field, $value)
    {
        $this->save_buffer[$field] = $value;
    }

    public function afterUnload()
    {
        $this->id = null;
        $this->save_buffer = [];
    }
}
