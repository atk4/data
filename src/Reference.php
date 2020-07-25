<?php

declare(strict_types=1);

namespace atk4\data;

/**
 * Reference implements a link between one model and another. The basic components for
 * a reference is ability to generate the destination model, which is returned through
 * getModel() and that's pretty much it.
 *
 * It's possible to extend the basic reference with more meaningful references.
 *
 * @property Model $owner definition of "our model"
 */
class Reference
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }
    use \atk4\core\TrackableTrait;
    use \atk4\core\DiContainerTrait;
    use \atk4\core\FactoryTrait;

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
    public $link;

    /**
     * Definition of the destination (their) model, that can be either an object, a
     * callback or a string. This can be defined during initialization and
     * then used inside getModel() to fully populate and associate with
     * persistence.
     *
     * @var Model|string|array
     */
    public $model;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string
     */
    protected $our_field;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     *
     * @var string
     */
    protected $their_field;

    /**
     * Caption of the reeferenced model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string
     */
    public $caption;

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
     * Initialization.
     */
    public function init(): void
    {
        $this->_init();
    }

    /**
     * Will use #ref_<link>.
     */
    public function getDesiredName(): string
    {
        return '#ref_' . $this->link;
    }

    public function getOurModel(): Model
    {
        return $this->owner;
    }

    /**
     * @deprecated use getTheirModel instead - will be removed in dec-2020
     */
    public function getModel($defaults = []): Model
    {
        'trigger_error'('Method Reference::getModel is deprecated. Use Model::getTheirModel instead', E_USER_DEPRECATED);

        return $this->getTheirModel($defaults);
    }

    /**
     * Returns destination model that is linked through this reference. Will apply
     * necessary conditions.
     *
     * @param array $defaults Properties
     */
    public function getTheirModel($defaults = []): Model
    {
        $ourModel = $this->getOurModel();

        // set table_alias
        if (!isset($defaults['table_alias'])) {
            if (!$this->table_alias) {
                $this->table_alias = $this->link;
                $this->table_alias = preg_replace('/_' . ($ourModel->id_field ?: 'id') . '/', '', $this->table_alias);
                $this->table_alias = preg_replace('/([a-zA-Z])[a-zA-Z]*[^a-zA-Z]*/', '\1', $this->table_alias);
                if (isset($ourModel->table_alias)) {
                    $this->table_alias = $ourModel->table_alias . '_' . $this->table_alias;
                }
            }
            $defaults['table_alias'] = $this->table_alias;
        }

        // if model is Closure, then call it and return model
        if (is_object($this->model) && $this->model instanceof \Closure) {
            $closure = ($this->model)($ourModel, $this, $defaults);

            return $this->addToPersistence($closure, $defaults);
        }

        // if model is set, then return clone of this model
        if (is_object($this->model)) {
            $theirModel = clone $this->model;

            return $this->addToPersistence($theirModel, $defaults);
        }

        // last effort - try to add model
        if (is_array($this->model)) {
            $theirModel = [$this->model[0]];
            $md = $this->model;
            unset($md[0]);

            $defaults = array_merge($md, $defaults);
        } elseif (is_string($this->model)) {
            $theirModel = [$this->model];
        } else {
            $theirModel = $this->model;
        }

        if (!$theirModel instanceof Model) {
            $theirModel = $this->factory($theirModel, $defaults);
        }

        return $this->addToPersistence($theirModel, $defaults);
    }

    /**
     * Adds model to persistence.
     *
     * @param Model $model
     * @param array $defaults
     */
    protected function addToPersistence($model, $defaults = []): Model
    {
        if (!$model->persistence && $persistence = $this->getDefaultPersistence($model)) {
            $persistence->add($model, $defaults);
        }

        // set model caption
        if ($this->caption !== null) {
            $model->caption = $this->caption;
        }

        return $model;
    }

    /**
     * Returns default persistence.
     *
     * @param Model $model Referenced model
     *
     * @return Persistence|false
     */
    protected function getDefaultPersistence($model)
    {
        $ourModel = $this->getOurModel();

        // this will be useful for containsOne/Many implementation in case when you have
        // SQL_Model->containsOne()->hasOne() structure to get back to SQL persistence
        // from Array persistence used in containsOne model
        if ($ourModel->contained_in_root_model && $ourModel->contained_in_root_model->persistence) {
            return $ourModel->contained_in_root_model->persistence;
        }

        return $ourModel->persistence ?: false;
    }

    /**
     * Returns referenced model without any extra conditions. However other
     * relationship types may override this to imply conditions.
     *
     * @param array $defaults Properties
     */
    public function ref($defaults = []): Model
    {
        return $this->getTheirModel($defaults);
    }

    /**
     * Returns referenced model without any extra conditions. Ever when extended
     * must always respond with Model that does not look into current record
     * or scope.
     *
     * @param array $defaults Properties
     */
    public function refModel($defaults = []): Model
    {
        return $this->getTheirModel($defaults);
    }

    // {{{ Debug Methods

    /**
     * List of properties to show in var_dump.
     */
    protected $__debug_fields = ['link', 'model', 'our_field', 'their_field'];

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [];
        foreach ($this->__debug_fields as $k => $v) {
            $k = is_numeric($k) ? $v : $k;
            if (isset($this->{$v})) {
                $arr[$k] = $this->{$v};
            }
        }

        return $arr;
    }

    // }}}
}
