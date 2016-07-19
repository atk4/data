<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

class Field_One
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;


    /**
     * What should we pass into owner->ref() to get
     * through to this reference.
     */
    protected $link;

    /**
     * Definition of the destination model, that can
     * be either an object, a callback or a string.
     */
    protected $model;

    protected $our_field = null;

    /**
     * their field will be $table.'_id' by default.
     */
    protected $their_field = null;

    /**
     * points to the join if we are part of one.
     */
    protected $join = null;

    /**
     * default constructor. Will copy argument into properties.
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
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName()
    {
        return '#ref_'.$this->link;
    }

    public function init()
    {
        $this->_init();
        if (!$this->our_field) {
            $this->our_field = $this->link;
        }
        if (!$this->owner->hasElement($this->our_field)) {
            $this->owner->addField($this->our_field, ['system' => true, 'join' => $this->join]);
        }
    }

    public function getModel($defaults = [])
    {
        if (!isset($defaults['table_alias'])) {
            $alias = $this->link;
            $alias = preg_replace('/_id/','',$alias);
            $alias = preg_replace('/([a-zA-Z])[a-zA-Z]*[^a-zA-Z]*/','\1',$alias);
            $defaults['table_alias']=$alias;
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
            if ($this->model->persistence) {
                throw new Exception([
                    'When relating to object, it must not be associated with persistence yet.'
                    // actually - that in the future we will support it.
                ]);
            }
            $c = clone $this->model;
            return $this->owner->persistence->add($c, $defaults);
        }

        // last effort - try to add model
        $p = $this->owner->persistence;

        return $p->add($p->normalizeClassName($this->model, 'Model'), $defaults);

        throw new Exception([
            'Model is not defined for the relation',
            'model' => $this->model,
        ]);
    }

    protected function referenceOurValue()
    {
        $this->owner->persistence_data['use_table_prefixes'] = true;

        return $this->owner->getElement($this->our_field);
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table but
     * form the join instead.
     */
    public function ref($defaults = [])
    {
        $m = $this->getModel($defaults);
        if ($this->owner->loaded()) {
            if ($this->their_field) {
                return $m->loadBy($this->their_field, $this->owner[$this->our_field]);
            } else {
                return $m->load($this->owner[$this->our_field]);
            }
        } else {
            $m = clone $m; // we will be adding conditions!

            $values = $this->owner->action('field', [$this->our_field]);

            return $m->addCondition($this->their_field ?: $m->id_field, $values);
        }
    }

    // {{{ Debug Methods
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
