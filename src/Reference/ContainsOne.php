<?php

declare(strict_types=1);

namespace atk4\data\Reference;

use atk4\data\Model;
use atk4\data\Persistence;
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
        'visible' => false, // not visible in UI Table, Grid and Crud
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
    public function init(): void
    {
        parent::init();

        if (!$this->our_field) {
            $this->our_field = $this->link;
        }

        $ourModel = $this->getOurModel();

        if (!$ourModel->hasElement($this->our_field)) {
            $ourModel->addField($this->our_field, [
                'type' => $this->type,
                'reference' => $this,
                'system' => $this->system,
                'caption' => $this->caption, // it's ref models caption, but we can use it here for field too
                'ui' => $this->ui,
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
     * @return Persistence
     */
    protected function getDefaultPersistence($model)
    {
        return new Persistence\ArrayOfStrings([
            $this->table_alias => $this->getOurFieldValue() ? [1 => $this->getOurFieldValue()] : [],
        ]);
    }

    /**
     * Returns referenced model with loaded data record.
     *
     * @param array $defaults Properties
     */
    public function ref($defaults = []): Model
    {
        $ourModel = $this->getOurModel();

        // get model
        // will not use ID field
        $theirModel = $this->getTheirModel(array_merge($defaults, [
            'contained_in_root_model' => $ourModel->contained_in_root_model ?: $ourModel,
            'id_field' => false,
            'table' => $this->table_alias,
        ]));

        // set some hooks for ref_model
        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $theirModel->onHook($spot, function ($theirModel) {
                $row = $theirModel->persistence->getRawDataByTable($this->table_alias);
                $row = $row ? array_shift($row) : null; // get first and only one record from array persistence
                $this->getOurModel()->save([$this->getOurFieldName() => $row]);
            });
        }

        // try to load any (actually only one possible) record
        $theirModel->tryLoadAny();

        return $theirModel;
    }
}
