<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Reference_One extends Reference
{

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
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [
        'editable' => true,
        'visible'  => false,
    ];

    /**
     * Is field mandatory? By default fields are not mandatory.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Reference_One will also add a field corresponding
     * to 'our_field' unless it exists of course.
     */
    public function init()
    {
        parent::init();

        if (!$this->our_field) {
            $this->our_field = $this->link;
        }

        if (!$this->owner->hasElement($this->our_field)) {
            $this->owner->addField($this->our_field, [
                'type'          => null, // $this->guessFieldType(),
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
        // Imants: probably this piece of code should be moved to Reference_SQL_One->ref() method,
        //         because only Persistence_SQL supports actions.
        if (isset($this->owner->persistence) && $this->owner->persistence instanceof Persistence_SQL) {
            $values = $this->owner->action('field', [$this->our_field]);

            return $m->addCondition($this->their_field ?: $m->id_field, $values);
        }

        // can not load referenced model or set conditions on it, so we just return it
        return $m;
    }

    /**
     * List of properties to show in var_dump.
     */
    protected $debug_fields = ['our_field', 'their_field', 'type', 'system', 'never_save', 'never_persist', 'read_only', 'ui', 'join'];
}
