<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Reference implements a link between one model and another. The basic components for
 * a reference is ability to generate the destination model, which is returned through
 * getModel() and that's pretty much it.
 *
 * It's possible to extend the basic reference with more meaningful references.
 */
class Reference
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;
    use \atk4\core\DIContainerTrait;

    /**
     * Use this alias for related entity by default. This can help you
     * if you create sub-queries or joins to separate this from main
     * table. The table_alias will be uniquely generated.
     *
     * @var string
     */
    protected $table_alias;

    /**
     * What should we pass into owner->ref() to get through to this reference.
     * Each reference has a unique identifier, although it's stored
     * in Model's elements as '#ref-xx'.
     *
     * @var string
     */
    protected $link;

    /**
     * Definition of the destination model, that can be either an object, a
     * callback or a string. This can be defined during initialization and
     * then used inside getModel() to fully populate and associate with
     * persistence.
     *
     * @var Model|null
     */
    public $model;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string
     */
    protected $our_field = null;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string
     */
    protected $their_field = null;

    /**
     * Default constructor. Will copy argument into properties.
     *
     * @param string $link a short_name component
     */
    public function __construct($link)
    {
        $this->link = $link;
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
    }

    /**
     * Returns destination model that is linked through this reference. Will apply
     * necessary conditions.
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
                $this->table_alias = preg_replace('/_'.($this->owner->id_field ?: 'id').'/', '', $this->table_alias);
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

            $c = $c($this->owner, $this, $defaults);
            if (!$c->persistence && $this->owner->persistence) {
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

        return $p->add($model, $defaults);
    }

    /**
     * Returns referenced model without any extra conditions. However other
     * relationship types may override this to imply conditions.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = [])
    {
        return $this->getModel($defaults);
    }

    /**
     * Returns referenced model without any extra conditions. Ever when extended
     * must always respond with Model that does not look into current record
     * or scope.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function refModel($defaults = [])
    {
        return $this->getModel($defaults);
    }

    /**
     * If you have set $their_field property correctly, then calling this method
     * will traverse into a related model and fetch the type of their field. This
     * method will be extra careful to avoid any loops.
     *
     * @param string $their_field
     *
     * @return string|null
     */
    public function guessFieldType($their_field = null)
    {
        return;
        if (!is_string($their_field)) {
            return;
        }

        // We shouldn't traverse into ourselves recursively
        $cl = get_class($this->owner).'::'.$this->short_name;

        // see where we shouldn't traverse
        if (isset($this->owner->_do_not_traverse_into)) {
            $dnti = $this->owner->_do_not_traverse_into;

            if (isset($dnti[$cl])) {
                return;
            }
        }
        $dnti[$cl] = true;

        $m = $this->getModel(['_do_not_traverse_into' => $dnti]);

        $f = $m->hasElement($their_field ?: $this->their_field);

        return $f ? $f->type : null;
    }

    // {{{ Debug Methods

    /**
     * List of properties to show in var_dump.
     */
    protected $__debug_fields = ['link', 'model', 'our_field', 'their_field'];

    /**
     * Returns array with useful debug info for var_dump.
     *
     * @return array
     */
    public function __debugInfo()
    {
        $arr = [];
        foreach ($this->__debug_fields as $k => $v) {
            $k = is_numeric($k) ? $v : $k;
            if (isset($this->$v)) {
                $arr[$k] = $this->$v;
            }
        }

        return $arr;
    }

    // }}}
}
