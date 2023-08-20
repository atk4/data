<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Factory;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * Provides generic functionality for joining data.
 *
 * @method Model getOwner()
 */
abstract class Join
{
    use DiContainerTrait;
    use InitializerTrait {
        init as private _init;
    }
    use JoinLinkTrait;
    use TrackableTrait {
        setOwner as private _setOwner;
    }

    /** Foreign table or WITH/CTE alias when used with SQL persistence. */
    protected string $foreignTable;

    /** Alias for the joined table. */
    public ?string $foreignAlias = null;

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join like "right".
     *
     * @var string
     */
    protected $kind;

    /** Weak join does not update foreign table. */
    protected bool $weak = false;

    /**
     * Normally the foreign table is saved first, then it's ID is used in the
     * primary table. When deleting, the primary table record is deleted first
     * which is followed by the foreign table record.
     *
     * If you are using the following syntax:
     *
     * $user->join('contact', 'default_contact_id')
     *
     * Then the ID connecting tables is stored in foreign table and the order
     * of saving and delete needs to be reversed. In this case $reverse
     * will be set to `true`. You can specify value of this property.
     */
    protected bool $reverse = false;

    /**
     * Field to be used for matching inside master table.
     * By default it's $foreignTable . '_id'.
     */
    public ?string $masterField = null;

    /**
     * Field to be used for matching in a foreign table.
     * By default it's 'id'.
     */
    public ?string $foreignField = null;

    /**
     * Field to be used as foreign model ID field.
     * By default it's 'id'.
     */
    public ?string $foreignIdField = null;

    /**
     * When $prefix is set, then all the fields generated through
     * our wrappers will be automatically prefixed inside the model.
     */
    protected string $prefix = '';

    /** @var mixed ID indexed by spl_object_id(entity) used by a joined table. */
    protected $idByOid;

    /** @var array<int, array<string, mixed>> Data indexed by spl_object_id(entity) which is populated here as the save/insert progresses. */
    private array $saveBufferByOid = [];

    public function __construct(string $foreignTable)
    {
        $this->foreignTable = $foreignTable;

        // handle foreign table containing a dot - that will be reverse join
        // TODO this table split condition makes JoinArrayTest::testForeignFieldNameGuessTableWithSchema test
        // quite inconsistent - drop it?
        if (str_contains($this->foreignTable, '.')) {
            // split by LAST dot in foreignTable name
            [$this->foreignTable, $this->foreignField] = preg_split('~\.(?=[^.]+$)~', $this->foreignTable);
            $this->reverse = true;
        }
    }

    /**
     * Create fake foreign model, in the future, this method should be removed
     * in favor of always requiring an object model.
     */
    protected function createFakeForeignModel(): Model
    {
        $fakeModel = new Model($this->getOwner()->getPersistence(), [
            'table' => $this->foreignTable,
            'idField' => $this->foreignIdField,
        ]);
        foreach ($this->getOwner()->getFields() as $ownerField) {
            if ($ownerField->hasJoin() && $ownerField->getJoin()->shortName === $this->shortName) {
                $ownerFieldPersistenceName = $ownerField->getPersistenceName();
                if ($ownerFieldPersistenceName !== $fakeModel->idField && $ownerFieldPersistenceName !== $this->foreignField) {
                    $fakeModel->addField($ownerFieldPersistenceName, [
                        'type' => $ownerField->type,
                    ]);
                }
            }
        }
        if ($fakeModel->idField !== $this->foreignField && $this->foreignField !== null) {
            $fakeModel->addField($this->foreignField, ['type' => 'integer']);
        }

        return $fakeModel;
    }

    public function getForeignModel(): Model
    {
        // TODO this should be removed in the future
        if (!isset($this->getOwner()->cteModels[$this->foreignTable])) {
            return $this->createFakeForeignModel();
        }

        return $this->getOwner()->cteModels[$this->foreignTable]['model'];
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
     * @template T of Model
     *
     * @param \Closure(T, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed $fx
     * @param array<int, mixed>                                                                        $args
     */
    protected function onHookToOwnerBoth(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamic(
            $spot,
            static function (Model $model) use ($name): self {
                /** @var self */
                $obj = $model->getModel(true)->getElement($name);
                $model->getModel(true)->assertIsModel($obj->getOwner());

                return $obj;
            },
            $fx,
            $args,
            $priority
        );
    }

    /**
     * @template T of Model
     *
     * @param \Closure(T, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed $fx
     * @param array<int, mixed>                                                                        $args
     */
    protected function onHookToOwnerEntity(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamic(
            $spot,
            static function (Model $entity) use ($name): self {
                /** @var self */
                $obj = $entity->getModel()->getElement($name);
                $entity->assertIsEntity($obj->getOwner());

                return $obj;
            },
            $fx,
            $args,
            $priority
        );
    }

    private function getModelTableString(Model $model): string
    {
        if (is_object($model->table)) {
            return $this->getModelTableString($model->table);
        }

        return $model->table;
    }

    /**
     * Will use either foreignAlias or #join-<table>.
     */
    public function getDesiredName(): string
    {
        return '#join-' . ($this->foreignAlias ?? $this->foreignTable);
    }

    protected function init(): void
    {
        $this->_init();

        $this->getForeignModel(); // assert valid foreignTable

        // owner model should have idField set
        $idField = $this->getOwner()->idField;
        if (!$idField) {
            throw (new Exception('Join owner model must have idField set'))
                ->addMoreInfo('model', $this->getOwner());
        }

        if ($this->reverse === true) {
            if ($this->masterField && $this->masterField !== $idField) { // TODO not implemented yet, see https://github.com/atk4/data/issues/803
                throw (new Exception('Joining tables on non-id fields is not implemented yet'))
                    ->addMoreInfo('masterField', $this->masterField)
                    ->addMoreInfo('idField', $idField);
            }

            if (!$this->masterField) {
                $this->masterField = $idField;
            }

            if (!$this->foreignField) {
                $this->foreignField = preg_replace('~^.+\.~s', '', $this->getModelTableString($this->getOwner())) . '_' . $idField;
            }
        } else {
            $this->reverse = false;

            if (!$this->masterField) {
                $this->masterField = $this->foreignTable . '_' . $idField;
            }

            if (!$this->foreignField) {
                $this->foreignField = $idField;
            }
        }

        // if kind is not specified, figure out join type
        if (!$this->kind) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        $this->initJoinHooks();
    }

    protected function initJoinHooks(): void
    {
        $this->onHookToOwnerEntity(Model::HOOK_AFTER_UNLOAD, \Closure::fromCallable([$this, 'afterUnload']));

        if ($this->reverse) {
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
        } else {
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));

            $this->onHookToOwnerEntity(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    private function getJoinNameFromShortName(): string
    {
        return str_starts_with($this->shortName, '#join-') ? substr($this->shortName, 6) : null;
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table, but
     * form the join instead.
     *
     * @param array<mixed> $seed
     */
    public function addField(string $name, array $seed = []): Field
    {
        $seed['joinName'] = $this->getJoinNameFromShortName();

        return $this->getOwner()->addField($this->prefix . $name, $seed);
    }

    /**
     * Adds multiple fields.
     *
     * @param array<string, array<mixed>>|array<int, string> $fields
     * @param array<mixed>                                   $seed
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $seed = [])
    {
        foreach ($fields as $k => $v) {
            if (is_int($k)) {
                $k = $v;
                $v = [];
            }

            $this->addField($k, Factory::mergeSeeds($v, $seed));
        }

        return $this;
    }

    /**
     * Another join will be attached to a current join.
     *
     * @param array<string, mixed> $defaults
     */
    public function join(string $foreignTable, array $defaults = []): self
    {
        $defaults['joinName'] = $this->getJoinNameFromShortName();

        return $this->getOwner()->join($foreignTable, $defaults);
    }

    /**
     * Another leftJoin will be attached to a current join.
     *
     * @param array<string, mixed> $defaults
     */
    public function leftJoin(string $foreignTable, array $defaults = []): self
    {
        $defaults['joinName'] = $this->getJoinNameFromShortName();

        return $this->getOwner()->leftJoin($foreignTable, $defaults);
    }

    /**
     * Creates reference based on a field from the join.
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\HasOne
     */
    public function hasOne(string $link, array $defaults = [])
    {
        $defaults['joinName'] = $this->getJoinNameFromShortName();

        return $this->getOwner()->hasOne($link, $defaults);
    }

    /**
     * Creates reference based on the field from the join.
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $link, array $defaults = [])
    {
        return $this->getOwner()->hasMany($link, $defaults);
    }

    /*
    /**
     * Wrapper for ContainsOne that will associate field with join.
     *
     * @TODO NOT IMPLEMENTED!
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\ContainsOne
     *X/
    public function containsOne(string $link, array $defaults = []) // : Reference
    {
        $defaults['joinName'] = $this->getJoinNameFromShortName();

        return $this->getOwner()->containsOne($link, $defaults);
    }

    /**
     * Wrapper for ContainsMany that will associate field with join.
     *
     * @TODO NOT IMPLEMENTED!
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\ContainsMany
     *X/
    public function containsMany(string $link, array $defaults = []) // : Reference
    {
        return $this->getOwner()->containsMany($link, $defaults);
    }
    */

    /**
     * @param mixed $value
     */
    protected function assertReferenceIdNotNull($value): void
    {
        if ($value === null) {
            throw (new Exception('Unable to join on null value'))
                ->addMoreInfo('value', $value);
        }
    }

    /**
     * @return mixed
     *
     * @internal should be not used outside atk4/data
     */
    protected function getId(Model $entity)
    {
        return $this->idByOid[spl_object_id($entity)];
    }

    /**
     * @param mixed $id
     *
     * @internal should be not used outside atk4/data
     */
    protected function setId(Model $entity, $id): void
    {
        $this->assertReferenceIdNotNull($id);

        $this->idByOid[spl_object_id($entity)] = $id;
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function unsetId(Model $entity): void
    {
        unset($this->idByOid[spl_object_id($entity)]);
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function issetSaveBuffer(Model $entity): bool
    {
        return isset($this->saveBufferByOid[spl_object_id($entity)]);
    }

    /**
     * @return array<string, mixed>
     *
     * @internal should be not used outside atk4/data
     */
    protected function getReindexAndUnsetSaveBuffer(Model $entity): array
    {
        $resOur = $this->saveBufferByOid[spl_object_id($entity)];
        $this->unsetSaveBuffer($entity);

        $res = [];
        foreach ($resOur as $k => $v) {
            $res[$this->getOwner()->getField($k)->getPersistenceName()] = $v;
        }

        return $res;
    }

    /**
     * @internal should be not used outside atk4/data
     */
    protected function unsetSaveBuffer(Model $entity): void
    {
        unset($this->saveBufferByOid[spl_object_id($entity)]);
    }

    /**
     * @param mixed $value
     */
    public function setSaveBufferValue(Model $entity, string $fieldName, $value): void
    {
        $entity->assertIsEntity($this->getOwner());

        if (!isset($this->saveBufferByOid[spl_object_id($entity)])) {
            $this->saveBufferByOid[spl_object_id($entity)] = [];
        }

        $this->saveBufferByOid[spl_object_id($entity)][$fieldName] = $value;
    }

    protected function afterUnload(Model $entity): void
    {
        $this->unsetId($entity);
        $this->unsetSaveBuffer($entity);
    }

    abstract public function afterLoad(Model $entity): void;

    /**
     * @param array<string, mixed> $data
     */
    public function beforeInsert(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        // the value for the masterField is set, so we are going to use existing record anyway
        if ($entity->get($this->masterField) !== null) {
            return;
        }

        $foreignModel = $this->getForeignModel();
        $foreignEntity = $foreignModel->createEntity()
            ->setMulti($this->getReindexAndUnsetSaveBuffer($entity))
            /* ->set($this->foreignField, null) */;
        $foreignEntity->save();

        $this->setId($entity, $foreignEntity->getId());

        if ($this->hasJoin()) {
            $this->getJoin()->setSaveBufferValue($entity, $this->masterField, $this->getId($entity));
        } else {
            $data[$this->masterField] = $this->getId($entity);
        }

        // $entity->set($this->masterField, $this->getId($entity)); // TODO needed? from array persistence
    }

    public function afterInsert(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $id = $this->hasJoin() ? $this->getJoin()->getId($entity) : $entity->getId();
        $this->assertReferenceIdNotNull($id);
        // $this->setSaveBufferValue($entity, $this->masterField, $id); // TODO needed? from array persistence

        $foreignModel = $this->getForeignModel();
        $foreignEntity = $foreignModel->createEntity()
            ->setMulti($this->getReindexAndUnsetSaveBuffer($entity))
            ->set($this->foreignField, $id);
        $foreignEntity->save();

        $this->setId($entity, $entity->getId()); // TODO why is this here? it seems to be not needed
    }

    /**
     * @param array<string, mixed> $data
     */
    public function beforeUpdate(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if (!$this->issetSaveBuffer($entity)) {
            return;
        }

        $foreignModel = $this->getForeignModel();
        $foreignId = $this->reverse ? $entity->getId() : $entity->get($this->masterField);
        $this->assertReferenceIdNotNull($foreignId);
        $saveBuffer = $this->getReindexAndUnsetSaveBuffer($entity);
        $foreignModel->atomic(function () use ($foreignModel, $foreignId, $saveBuffer) {
            $foreignModel = (clone $foreignModel)->addCondition($this->foreignField, $foreignId);
            foreach ($foreignModel as $foreignEntity) {
                $foreignEntity->setMulti($saveBuffer);
                $foreignEntity->save();
            }
        });

        // $this->setId($entity, ??); // TODO needed? from array persistence
    }

    public function doDelete(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $foreignModel = $this->getForeignModel();
        $foreignId = $this->reverse ? $entity->getId() : $entity->get($this->masterField);
        $this->assertReferenceIdNotNull($foreignId);
        $foreignModel->atomic(function () use ($foreignModel, $foreignId) {
            $foreignModel = (clone $foreignModel)->addCondition($this->foreignField, $foreignId);
            foreach ($foreignModel as $foreignEntity) {
                $foreignEntity->delete();
            }
        });

        $this->unsetId($entity); // TODO needed? from array persistence
    }
}
