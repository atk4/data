<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Reference\WeakAnalysingMap;

/**
 * Reference implements a link between our model and their model..
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

    /** @var WeakAnalysingMap<list<mixed>, \Closure, Model|Persistence> */
    private static WeakAnalysingMap $analysingClosureMap;

    /** @var WeakAnalysingMap<array{Persistence, array<mixed>|\Closure(Persistence, array<string, mixed>): Model|Model, array<mixed>}, Model, Model|Persistence> */
    private static WeakAnalysingMap $analysingTheirModelMap;

    /**
     * Use this alias for their model by default. This can help you
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
     * Seed of their model. If it is a Model instance, self::createTheirModel() must
     * always clone it to return a new instance.
     *
     * @var array<mixed>|\Closure(Persistence, array<string, mixed>): Model|Model
     */
    protected $model;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     */
    protected ?string $ourField = null;

    /**
     * This is an optional property which can be used by your implementation
     * to store field-level relationship based on a common field matching.
     */
    protected ?string $theirField = null;

    /**
     * Database our/their field types must always match, but DBAL types can be different in theory,
     * set this to false when the DBAL types are intentionally different.
     */
    public bool $checkTheirType = true;

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
        return $this->ourField
            ?? $this->getOurModel()->idField;
    }

    final protected function getOurField(): Field
    {
        return $this->getOurModel()->getField($this->getOurFieldName());
    }

    /**
     * @return mixed
     */
    final protected function getOurFieldValue(Model $ourEntity)
    {
        $this->assertOurModelOrEntity($ourEntity);

        return $ourEntity->get($this->getOurFieldName());
    }

    public function getTheirFieldName(?Model $theirModel = null): string
    {
        return $this->theirField
            ?? ($theirModel ?? Model::assertInstanceOf($this->model))->idField;
    }

    /**
     * @param \Closure<T of Model>(T, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed $fx
     * @param array<int, mixed>                                                                                    $args
     */
    protected function onHookToOurModel(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $this->getOurModel()->onHookDynamic(
            $spot,
            static function (Model $modelOrEntity) use ($name): self {
                /** @var self */
                $obj = $modelOrEntity->getModel(true)->getElement($name);
                $modelOrEntity->getModel(true)->assertIsModel($obj->getOwner());

                return $obj;
            },
            $fx,
            $args,
            $priority
        );
    }

    /**
     * @param \Closure(Model, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed $fx
     * @param array<int, mixed>                                                                            $args
     */
    protected function onHookToTheirModel(Model $theirModel, string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $theirModel->assertIsModel();

        $ourModel = $this->getOurModel();
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $theirModel->onHookDynamic(
            $spot,
            static function () use ($ourModel, $name): self {
                /** @var self */
                $obj = $ourModel->getElement($name);
                $ourModel->assertIsModel($obj->getOwner());

                return $obj;
            },
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

    public function assertOurModelOrEntity(Model $ourModelOrEntity): void
    {
        $this->getOwner()
            ->assertIsModel($ourModelOrEntity->getModel(true));
    }

    public function getOurModel(): Model
    {
        $ourModel = $this->getOwner();
        $ourModel->assertIsModel();

        return $ourModel;
    }

    protected function initTableAlias(): void
    {
        if (!$this->tableAlias) {
            $ourModel = $this->getOurModel();

            $aliasFull = $this->link;
            $alias = preg_replace('~_(' . preg_quote($ourModel->idField !== false ? $ourModel->idField : '', '~') . '|id)$~', '', $aliasFull);
            $alias = preg_replace('~([0-9a-z]?)[0-9a-z]*[^0-9a-z]*~i', '$1', $alias);
            if ($ourModel->tableAlias !== null) {
                $aliasFull = $ourModel->tableAlias . '_' . $aliasFull;
                $alias = preg_replace('~^_(.+)_[0-9a-f]{12}$~', '$1', $ourModel->tableAlias) . '_' . $alias;
            }
            $this->tableAlias = '_' . $alias . '_' . substr(md5($aliasFull), 0, 12);
        }
    }

    /**
     * Returns default persistence for their model.
     *
     * @return Persistence|false
     */
    protected function getDefaultPersistence()
    {
        $ourModel = $this->getOurModel();

        // this is useful for ContainsOne/Many implementation in case when you have
        // SQL_Model->containsOne()->hasOne() structure to get back to SQL persistence
        // from Array persistence used in ContainsOne model
        if ($ourModel->containedInEntity !== null && $ourModel->containedInEntity->getModel()->issetPersistence()) {
            return $ourModel->containedInEntity->getModel()->getPersistence();
        }

        return $ourModel->issetPersistence()
            ? $ourModel->getPersistence()
            : false;
    }

    /**
     * @param array<string, mixed> $defaults
     */
    protected function createTheirModelBeforeInit(array $defaults): Model
    {
        $defaults['tableAlias'] ??= $this->tableAlias;

        // if model is Closure, then call the closure and it should return a model
        if ($this->model instanceof \Closure) {
            $persistence = Persistence::assertInstanceOf($this->getDefaultPersistence());
            $m = ($this->model)($persistence, $defaults);
        } else {
            $m = $this->model;
        }

        if (is_object($m)) {
            $theirModelSeed = clone $m;
        } else {
            \Closure::bind(static fn () => Model::_fromSeedPrecheck($m, false), null, Model::class)();
            $theirModelSeed = [$m[0]];
            unset($m[0]);
            $defaults = array_merge($m, $defaults);
        }

        $theirModel = Model::fromSeed($theirModelSeed, $defaults);

        return $theirModel;
    }

    protected function createTheirModelSetPersistence(Model $theirModel): void
    {
        if (!$theirModel->issetPersistence()) {
            $persistence = $this->getDefaultPersistence();
            if ($persistence !== false) {
                $theirModel->setPersistence($persistence);
            }
        }
    }

    protected function createTheirModelAfterInit(Model $theirModel): void
    {
        if ($this->caption !== null) {
            $theirModel->caption = $this->caption;
        }

        if ($this->checkTheirType) {
            $ourField = $this->getOurField();
            $theirField = $theirModel->getField($this->getTheirFieldName($theirModel));
            if ($theirField->type !== $ourField->type) {
                throw (new Exception('Reference type mismatch'))
                    ->addMoreInfo('ourField', $ourField)
                    ->addMoreInfo('ourFieldType', $ourField->type)
                    ->addMoreInfo('theirField', $theirField)
                    ->addMoreInfo('theirFieldType', $theirField->type);
            }
        }
    }

    /**
     * Create their model that is linked through this reference. Will apply
     * necessary conditions.
     *
     * IMPORTANT: the returned model must be a fresh clone or freshly built from a seed
     *
     * @param array<string, mixed> $defaults
     */
    final public function createTheirModel(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModelBeforeInit($defaults);
        $this->createTheirModelSetPersistence($theirModel);
        $this->createTheirModelAfterInit($theirModel);

        return $theirModel;
    }

    /**
     * @template T of array<mixed>
     *
     * @param T $analysingKey
     *
     * @return T
     */
    private function deduplicateAnalysingKey(array $analysingKey, object $analysingOwner): array
    {
        if ((self::$analysingClosureMap ?? null) === null) {
            self::$analysingClosureMap = new WeakAnalysingMap();
        }

        foreach ($analysingKey as $k => $v) {
            if (is_array($v)) {
                $analysingKey[$k] = $this->deduplicateAnalysingKey($v, $analysingOwner);
            } elseif ($v instanceof \Closure) {
                $fxRefl = new \ReflectionFunction($v);

                $fxKey = [
                    self::$analysingClosureMap,
                    $fxRefl->getFileName()
                        . '-' . $fxRefl->getStartLine()
                        . '-' . $fxRefl->getEndLine() . '-' . $fxRefl->getName(), // https://github.com/php/php-src/issues/11391
                    $fxRefl->getClosureScopeClass() !== null ? $fxRefl->getClosureScopeClass()->getName() : null,
                    $fxRefl->getClosureThis(),
                    \PHP_VERSION_ID < 80100 ? $fxRefl->getStaticVariables() : $fxRefl->getClosureUsedVariables(),
                ];

                // optimization - simplify key to improve hashing speed
                if ($fxKey[4] === []) {
                    unset($fxKey[4]);
                    if ($fxKey[3] === null) {
                        unset($fxKey[3]);
                    }
                }

                $fx = self::$analysingClosureMap->get($fxKey, $analysingOwner);
                if ($fx === null) {
                    $fx = $v;
                    self::$analysingClosureMap->set($fxKey, $fx, $analysingOwner);
                }

                $analysingKey[$k] = $fx;
            }
        }

        return $analysingKey;
    }

    /**
     * Same as self::createTheirModel() but the created model is deduplicated based on our model persistence,
     * self::$model seed and $defaults parameter to guard recursion from possibly recursively invoked Model::init()
     * and also to improve performance when used for their field/reference analysing purposes.
     *
     * @param array<string, mixed> $defaults
     */
    public function createAnalysingTheirModel(array $defaults = []): Model
    {
        if ((self::$analysingTheirModelMap ?? null) === null) {
            self::$analysingTheirModelMap = new WeakAnalysingMap();
        }

        $ourPersistence = $this->getOurModel()->getPersistence();
        $analysingKey = [$ourPersistence, $this->model, $defaults];
        $analysingOwner = $this->getOwner();

        // optimization - keep referenced for whole persistence lifetime if seed is class name only or unbound \Closure
        if ($defaults === []) {
            if (is_array($this->model) && count($this->model) === 1 && is_string($this->model[0] ?? null)) {
                $analysingOwner = $ourPersistence;
            } elseif ($this->model instanceof \Closure) {
                $fxRefl = new \ReflectionFunction($this->model);
                if ($fxRefl->getClosureThis() === null
                    && (\PHP_VERSION_ID < 80100 ? $fxRefl->getStaticVariables() : $fxRefl->getClosureUsedVariables()) === []) {
                    $analysingOwner = $ourPersistence;
                }
            }
        }

        $analysingKey = $this->deduplicateAnalysingKey($analysingKey, $analysingOwner);

        $theirModel = self::$analysingTheirModelMap->get($analysingKey, $analysingOwner);
        if ($theirModel === null) {
            try {
                $theirModel = $this->createTheirModelBeforeInit($defaults);
                self::$analysingTheirModelMap->set($analysingKey, $theirModel, $analysingOwner);
                $this->createTheirModelSetPersistence($theirModel);
                $this->createTheirModelAfterInit($theirModel);

                // make analysing model unusable
                \Closure::bind(static function () use ($theirModel) {
                    unset($theirModel->{'_persistence'});
                }, null, Model::class)();
            } catch (\Throwable $e) {
                if ($theirModel !== null) {
                    \Closure::bind(static function () use ($theirModel) {
                        $theirModel->_initialized = false;
                    }, null, Model::class)();
                }

                throw $e;
            }
        }

        $theirModel->assertIsInitialized();

        return $theirModel;
    }

    /**
     * Create their model. May be overridden to imply traversal conditions.
     *
     * @param array<string, mixed> $defaults
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        return $this->createTheirModel($defaults);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $res = [];
        foreach (['link', 'model', 'ourField', 'theirField'] as $k) {
            if ($this->{$k} !== null) {
                $res[$k] = $this->{$k};
            }
        }

        return $res;
    }
}
