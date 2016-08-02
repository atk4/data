<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Field_One
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
     * Is field editable? Normally you can edit fields.
     *
     * @var bool
     */
    public $editable = true;

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
            $this->$key = $val;
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
                'type'     => 'int', //$m->getElement($m->id_field)->type,
                'system'   => true,
                'join'     => $this->join,
                'default'  => $this->default,
                'editable' => $this->editable,
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
        if (is_object($this->model) && $this->model instanceof \Closure) {
            $c = $this->model;

            $c = $c($this->owner, $this);
            if (!$c->persistence) {
                $c = $this->owner->persistence->add($c, $defaults);
            }

            return $c;
        }

        if (is_object($this->model)) {
            $c = clone $this->model;
            if (!$this->model->persistence && $this->owner->persistence) {
                $this->owner->persistence->add($c, $defaults);
            }

            return $c;
        }

        // last effort - try to add model
        $p = $this->owner->persistence;

        if (is_array($this->model)) {
            $model = $this->model[0];
            $md = $this->model;
            unset($md[0]);

            $defaults = array_merge($md, $defaults);
        } else {
            $model = $this->model;
        }

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
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = [])
    {
        $m = $this->getModel($defaults);
        $m->addHook('afterDelete', function ($m) {
            $this->owner[$this->our_field] = null;
        });

        $load_by_field = $this->their_field ?: $m->id_field;
        if ($this->owner[$this->our_field]) {
            $m->tryLoadBy($load_by_field, $this->owner[$this->our_field]);
        }

        return
            $m->addHook('afterSave', function ($m) {
                $this->owner[$this->our_field] = $this->their_field ?  $m[$this->their_field] : $m->id;
            });
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

        if ($this->our_field) {
            $arr['our_field'] = $this->our_field;
        }

        if ($this->their_field) {
            $arr['their_field'] = $this->their_field;
        }

        return $arr;
    }

    // }}}
}
