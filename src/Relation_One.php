<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Relation_One
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;

    /**
     * Use this alias for related entity by default.
     *
     * @var string
     */
    protected $table_alias;

    /**
     * What should we pass into owner->ref() to get
     * through to this reference.
     *
     * @var string
     */
    protected $link;

    /**
     * Definition of the destination model, that can
     * be either an object, a callback or a string.
     *
     * @var Model|null
     */
    public $model;

    /**
     * Our field will be 'id' by default.
     *
     * @var string
     */
    protected $our_field = null;

    /**
     * Their field will be $table.'_id' by default.
     *
     * @var string
     */
    protected $their_field = null;

    /**
     * Points to the join if we are part of one.
     *
     * @var Join|null
     */
    protected $join = null;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default = null;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Is field mandatory? By default fields are not mandatory.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Default constructor. Will copy argument into properties.
     *
     * @param array $defaults
     */
    public function __construct($defaults = [])
    {
        if (isset($defaults[0])) {
            $this->link = $defaults[0];
            unset($defaults[0]);
        }

        foreach ($defaults as $key => $val) {
            if (is_array($val)) {
                $this->$key = array_merge(isset($this->$key) && is_array($this->$key) ? $this->$key : [], $val);
            } else {
                $this->$key = $val;
            }
        }
    }

    /**
     * Will use #ref_<link>.
     *
     * @return string
     */
    public function getDesiredName()
    {
        return '#ref_'.$this->link;
    }

    /**
     * Initialization.
     */
    public function init()
    {
        $this->_init();
        if (!$this->our_field) {
            $this->our_field = $this->link;
        }
        if (!$this->owner->hasElement($this->our_field)) {
            // Imants: proper way would be to get actual field type of id field of related model,
            // but if we try to do so here, then we end up in infinite loop :(
            //$m = $this->getModel();
            $this->owner->addField($this->our_field, [
                'type'          => 'int', //$m->getElement($m->id_field)->type,
                'system'        => true,
                'join'          => $this->join,
                'default'       => $this->default,
                'never_persist' => $this->never_persist,
                'read_only'     => $this->read_only,
                'ui'            => $this->ui,
                'mandatory'     => $this->mandatory,
            ]);
        }
    }

    /**
     * Returns model of field.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function getModel($defaults = [])
    {
        // set table_alias
        if (!isset($defaults['table_alias'])) {
            if (!$this->table_alias) {
                $this->table_alias = $this->link;
                $this->table_alias = preg_replace('/_id/', '', $this->table_alias);
                $this->table_alias = preg_replace('/([a-zA-Z])[a-zA-Z]*[^a-zA-Z]*/', '\1', $this->table_alias);
                if (isset($this->owner->table_alias)) {
                    $this->table_alias = $this->owner->table_alias.'_'.$this->table_alias;
                }
            }
            $defaults['table_alias'] = $this->table_alias;
        }

        // if model is Closure, then call it and return model
        if (is_object($this->model) && $this->model instanceof \Closure) {
            $c = $this->model;

            $c = $c($this->owner, $this);
            if (!$c->persistence) {
                $c = $this->owner->persistence->add($c, $defaults);
            }

            return $c;
        }

        // if model is set, then return clone of this model
        if (is_object($this->model)) {
            $c = clone $this->model;
            if (!$this->model->persistence && $this->owner->persistence) {
                $this->owner->persistence->add($c, $defaults);
            }

            return $c;
        }

        // last effort - try to add model
        if (is_array($this->model)) {
            $model = $this->model[0];
            $md = $this->model;
            unset($md[0]);

            $defaults = array_merge($md, $defaults);
        } else {
            $model = $this->model;
        }

        $p = $this->owner->persistence;

        return $p->add($p->normalizeClassName($model, 'Model'), $defaults);
    }

    /**
     * Returns our field or id field.
     *
     * @return Field
     */
    protected function referenceOurValue()
    {
        $this->owner->persistence_data['use_table_prefixes'] = true;

        return $this->owner->getElement($this->our_field);
    }

    /**
     * If owner model is loaded, then return referenced model with respective record loaded.
     *
     * If owner model is not loaded, then return referenced model with condition set.
     * This can happen in case of deep traversal $m->ref('Many')->ref('one_id'), for example.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = [])
    {
        $m = $this->getModel($defaults);

        // add hook to set our_field = null when record of referenced model is deleted
        $m->addHook('afterDelete', function ($m) {
            $this->owner[$this->our_field] = null;
        });

        // if owner model is loaded, then try to load referenced model
        if ($this->owner->loaded()) {
            if ($this->their_field) {
                if ($this->owner[$this->our_field]) {
                    $m->tryLoadBy($this->their_field, $this->owner[$this->our_field]);
                }

                return
                    $m->addHook('afterSave', function ($m) {
                        $this->owner[$this->our_field] = $m[$this->their_field];
                    });
            } else {
                if ($this->owner[$this->our_field]) {
                    $m->tryLoad($this->owner[$this->our_field]);
                }

                return
                    $m->addHook('afterSave', function ($m) {
                        $this->owner[$this->our_field] = $m->id;
                    });
            }
        }

        // if owner model is not loaded, then return referenced model with condition set
        // Imants: probably this piece of code should be moved to Relation_SQL_One->ref() method,
        //         because only Persistence_SQL supports actions.
        if (isset($this->owner->persistence) && $this->owner->persistence instanceof Persistence_SQL) {
            $values = $this->owner->action('field', [$this->our_field]);

            return $m->addCondition($this->their_field ?: $m->id_field, $values);
        }

        // can not load referenced model or set conditions on it, so we just return it
        return $m;
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $arr = [
            'ref'     => $this->link,
            'model'   => $this->model,
        ];

        foreach ([
            'our_field', 'their_field', 'type', 'system', 'never_save', 'never_persist', 'read_only', 'ui', 'join',
        ] as $key) {
            if (isset($this->$key)) {
                $arr[$key] = $this->$key;
            }
        }

        return $arr;
    }

    // }}}
}
