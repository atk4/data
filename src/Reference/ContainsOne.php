<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Reference;

use atk4\data\Exception;
use atk4\data\Model;
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
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * @var null|bool|array|string
     */
    public $serialize = 'json';

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
                'serialize'         => $this->serialize,
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
        $data = [$this->table_alias => []];
        $p = new \atk4\data\Persistence\Array_($data);

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
        // model should be loaded
        if (!$this->owner->loaded()) {
            throw new Exception(['Model should be loaded!', 'model' => get_class($this->owner)]);
        }

        // get model
        // will not use ID field
        $m = $this->getModel(array_merge($defaults, ['id_field' => false, 'table' => $this->table_alias]));

        // set data source of referenced array persistence
        $row = $this->owner[$this->our_field] ?: [];
        $row = $this->owner->persistence->typecastLoadRow($m, $row);
        $m->persistence->data = [$this->table_alias => ($row ? [1 => $row] : [])];

        // set some hooks for ref_model
        $m->addHook(['beforeSave'], function ($m) {
            $row = $m->get();
            $row = $this->owner->persistence->typecastSaveRow($m, $row);
            $this->owner->save([$this->our_field => $row]);
            $m->breakHook(false);
        });

        $m->addHook(['beforeDelete'], function ($m) {
            $this->owner->save([$this->our_field => null]);
            $m->breakHook(false);
        });

        // try to load any (actually only one possible) record
        $m->tryLoadAny();

        return $m;
    }
}
