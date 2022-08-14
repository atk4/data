<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Factory;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;

/**
 * Reference implements a link between one model and another. The basic components for
 * a reference is ability to generate the destination model, which is returned through
 * getModel() and that's pretty much it.
 *
 * It's possible to extend the basic reference with more meaningful references.
 *
 * @method Model getOwner() our model
 */
class Reference
{
    use DiContainerTrait;
    use InitializerTrait {
        init as private _init;
    }
    use TrackableTrait {
        setOwner as private _setOwner;
    }

    /**
     * Use this alias for related entity by default. This can help you
     * if you create sub-queries or joins to separate this from main
     * table. The tableAlias will be uniquely generated.
     *
     * @var string
     */
    protected $tableAlias;

    /**
     * What should we pass into owner->ref() to get through to this reference.
     * Each reference has a unique identifier, although it's stored
     * in Model's elements as '#ref-xx'.
     *
     * @var string
     */
    public $link;

    /**
     * Definition of the destination their model, that can be either an object, a
     * callback or a string. This can be defined during initialization and
     * then used inside getModel() to fully populate and associate with
     * persistence.
     *
     * @var Model|\Closure(object, static, array): Model|array
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
     * @var string|null
     */
    protected $their_field;

    /**
     * Caption of the referenced model. Can be used in UI components, for example.
     * Should be in plain English and ready for proper localization.
     *
     * @var string|null
     */
    public $caption;

    public function __construct(string $link)
    {
        $this->link = $link;
    }

    /**
     * @param Model $owner
     *
     * @return $this
     */
    public function setOwner(object $owner)
    {
        $owner->assertIsModel();

        return $this->_setOwner($owner);
    }

    /**
     * @param mixed $value
     */
    protected function assertReferenceValueNotNull($value): void
    {
        if ($value === null) {
            throw (new Exception('Unable to traverse on null value'))
                ->addMoreInfo('value', $value);
        }
    }

    public function getOurFieldName(): string
    {
        return $this->our_field ?: $this->getOurModel(null)->id_field;
    }

    final protected function getOurField(): Field
    {
        return $this->getOurModel(null)->getField($this->getOurFieldName());
    }

    /**
     * @return mixed
     */
    final protected function getOurFieldValue(Model $ourEntity)
    {
        return $this->getOurModel($ourEntity)->get($this->getOurFieldName());
    }

    public function getTheirFieldName(Model $theirModel = null): string
    {
        return $this->their_field ?? ($theirModel ?? Model::assertInstanceOf($this->model))->id_field;
    }

    protected function onHookToOurModel(Model $model, string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $model->onHookDynamic(
            $spot,
            static function (Model $model) use ($name): self {
                return $model->getModel(true)->getElement($name);
            },
            $fx,
            $args,
            $priority
        );
    }

    protected function onHookToTheirModel(Model $model, string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        if ($model->ownerReference !== null && $model->ownerReference !== $this) {
            throw new Exception('Model owner reference unexpectedly already set');
        }
        $model->ownerReference = $this;
        $getThisFx = static function (Model $model) {
            return $model->ownerReference;
        };

        return $model->onHookDynamic(
            $spot,
            $getThisFx,
            $fx,
            $args,
            $priority
        );
    }

    protected function init(): void
    {
        $this->_init();

        $this->initTableAlias();
    }

    /**
     * Will use #ref-<link>.
     */
    public function getDesiredName(): string
    {
        return '#ref-' . $this->link;
    }

    public function getOurModel(?Model $ourModel): Model
    {
        if ($ourModel === null) {
            $ourModel = $this->getOwner();
        }

        $this->getOwner()->assertIsModel($ourModel->getModel(true));

        return $ourModel;
    }

    /**
     * Create destination model that is linked through this reference. Will apply
     * necessary conditions.
     *
     * IMPORTANT: the returned model must be a fresh clone or freshly built from a seed
     */
    public function createTheirModel(array $defaults = []): Model
    {
        // set tableAlias
        $defaults['tableAlias'] ??= $this->tableAlias;

        // if model is Closure, then call the closure and it should return a model
        if ($this->model instanceof \Closure) {
            $m = ($this->model)($this->getOurModel(null), $this, $defaults);
        } else {
            $m = $this->model;
        }

        if (is_object($m)) {
            $theirModel = Factory::factory(clone $m, $defaults);
        } else {
            // add model from seed
            $modelDefaults = $m;
            $theirModelSeed = [$modelDefaults[0]];
            unset($modelDefaults[0]);
            $defaults = array_merge($modelDefaults, $defaults);

            $theirModel = Factory::factory($theirModelSeed, $defaults);
        }

        $this->addToPersistence($theirModel, $defaults);

        return $theirModel;
    }

    protected function initTableAlias(): void
    {
        if (!$this->tableAlias) {
            $ourModel = $this->getOurModel(null);

            $aliasFull = $this->link;
            $alias = preg_replace('~_(' . preg_quote($ourModel->id_field !== false ? $ourModel->id_field : '', '~') . '|id)$~', '', $aliasFull);
            $alias = preg_replace('~([0-9a-z]?)[0-9a-z]*[^0-9a-z]*~i', '$1', $alias);
            if ($ourModel->tableAlias !== null) {
                $aliasFull = $ourModel->tableAlias . '_' . $aliasFull;
                $alias = preg_replace('~^_(.+)_[0-9a-f]{12}$~', '$1', $ourModel->tableAlias) . '_' . $alias;
            }
            $this->tableAlias = '_' . $alias . '_' . substr(md5($aliasFull), 0, 12);
        }
    }

    protected function addToPersistence(Model $theirModel, array $defaults = []): void
    {
        if (!$theirModel->issetPersistence()) {
            $persistence = $this->getDefaultPersistence($theirModel);
            if ($persistence !== false) {
                $theirModel->setDefaults($defaults);
                $theirModel->setPersistence($persistence);
            }
        } elseif ($defaults !== []) {
            // TODO this seems dangerous
        }

        // set model caption
        if ($this->caption !== null) {
            $theirModel->caption = $this->caption;
        }
    }

    /**
     * Returns default persistence for theirModel.
     *
     * @return Persistence|false
     */
    protected function getDefaultPersistence(Model $theirModel)
    {
        $ourModel = $this->getOurModel(null);

        // this is useful for ContainsOne/Many implementation in case when you have
        // SQL_Model->containsOne()->hasOne() structure to get back to SQL persistence
        // from Array persistence used in ContainsOne model
        if ($ourModel->containedInEntity && $ourModel->containedInEntity->issetPersistence()) {
            return $ourModel->containedInEntity->getPersistence();
        }

        return $ourModel->issetPersistence() ? $ourModel->getPersistence() : false;
    }

    /**
     * Returns referenced model without any extra conditions. However other
     * relationship types may override this to imply conditions.
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        return $this->createTheirModel($defaults);
    }

    /**
     * Returns referenced model without any extra conditions. Ever when extended
     * must always respond with Model that does not look into current record
     * or scope.
     */
    public function refModel(Model $ourModel, array $defaults = []): Model
    {
        return $this->createTheirModel($defaults);
    }

    /** @var array<int|string, string> */
    protected $__debug_fields = ['link', 'model', 'our_field', 'their_field'];

    public function __debugInfo(): array
    {
        $arr = [];
        foreach ($this->__debug_fields as $k => $v) {
            $k = is_int($k) ? $v : $k;
            if ($this->{$v} !== null) {
                $arr[$k] = $this->{$v};
            }
        }

        return $arr;
    }
}
