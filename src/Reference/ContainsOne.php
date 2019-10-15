<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Reference;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence\ArrayOfStrings;
use atk4\data\Reference;

/**
 * ContainsOne reference.
 */
class ContainsOne extends Reference
{
    /**
     * Field type.
     *
     * @var string
     */
    public $type = 'array';

    /**
     * Is it system field?
     *
     * @var bool
     */
    public $system = true;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [
        'visible'  => false, // not visible in UI Table, Grid and CRUD
        'editable' => true, // but should be editable in UI Form
    ];

    /**
     * Required! We need table alias for internal use only.
     *
     * @var string
     */
    protected $table_alias = 'tbl';

    /**
     * Reference\ContainsOne will also add a field corresponding
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
                'type'              => $this->type,
                'reference'         => $this,
                'system'            => $this->system,
                'caption'           => $this->caption, // it's ref models caption, but we can use it here for field too
                'ui'                => $this->ui,
            ]);
        }
    }

    /**
     * Returns default persistence. It will be empty at this point.
     *
     * @see ref()
     *
     * @param Model $model Referenced model
     *
     * @return Persistence|false
     */
    protected function getDefaultPersistence($model)
    {
        $m = $this->owner;

        // model should be loaded
        /* Imants: it looks that this is not actually required - disabling
        if (!$m->loaded()) {
            throw new Exception(['Model should be loaded!', 'model' => get_class($m)]);
        }
        */

        // set data source of referenced array persistence
        $row = $m[$this->our_field] ?: [];
        //$row = $m->persistence->typecastLoadRow($m, $row); // we need this typecasting because we set persistence data directly

        $data = [$this->table_alias => $row ? [1 => $row] : []];
        $p = new ArrayOfStrings($data);

        return $p;
    }

    /**
     * Returns referenced model with loaded data record.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = []) : Model
    {
        // get model
        // will not use ID field
        $m = $this->getModel(array_merge($defaults, [
            'contained_in_root_model' => $this->owner->contained_in_root_model ?: $this->owner,
            'id_field'                => false,
            'table'                   => $this->table_alias,
        ]));

        // set some hooks for ref_model
        $m->addHook(['afterSave', 'afterDelete'], function ($model) {
            $row = $model->persistence->data[$this->table_alias];
            $row = $row ? array_shift($row) : null; // get first and only one record from array persistence
            $this->owner->save([$this->our_field => $row]);
        });

        // try to load any (actually only one possible) record
        $m->tryLoadAny();

        return $m;
    }
}
