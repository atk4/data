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
use Atk4\Data\Persistence;
use Atk4\Data\Reference;

/**
 * Provides generic functionality for joining data.
 *
 * @method Model getOwner()
 */
class Join
{
    use DiContainerTrait;
    use InitializerTrait {
        init as private _init;
    }
    use JoinLinkTrait;
    use TrackableTrait {
        setOwner as private _setOwner;
    }

    /**
     * Name of the table (or collection) that can be used to retrieve data from.
     * For SQL, This can also be an expression or sub-select.
     *
     * @var string
     */
    protected $foreign_table;

    /**
     * If $persistence is set, then it's used for loading
     * and storing the values, instead $owner->persistence.
     *
     * @var Persistence|Persistence\Sql|null
     */
    protected $persistence;

    /**
     * Field that is used as native "ID" in the foreign table.
     * When deleting record, this field will be conditioned.
     *
     * ->where($join->id_field, $join->id)->delete();
     *
     * @var string
     */
    protected $id_field = 'id';

    /**
     * By default this will be either "inner" (for strong) or "left" for weak joins.
     * You can specify your own type of join by passing ['kind' => 'right']
     * as second argument to join().
     *
     * @var string
     */
    protected $kind;

    /** @var bool Is our join weak? Weak join will stop you from touching foreign table. */
    protected $weak = false;

    /**
     * Normally the foreign table is saved first, then it's ID is used in the
     * primary table. When deleting, the primary table record is deleted first
     * which is followed by the foreign table record.
     *
     * If you are using the following syntax:
     *
     * $user->join('contact','default_contact_id');
     *
     * Then the ID connecting tables is stored in foreign table and the order
     * of saving and delete needs to be reversed. In this case $reverse
     * will be set to `true`. You can specify value of this property.
     *
     * @var bool
     */
    protected $reverse;

    /**
     * Field to be used for matching inside master table. By default
     * it's $foreign_table.'_id'.
     * Note that it should be actual field name in master table.
     *
     * @var string
     */
    protected $master_field;

    /**
     * Field to be used for matching in a foreign table. By default
     * it's 'id'.
     * Note that it should be actual field name in foreign table.
     *
     * @var string
     */
    protected $foreign_field;

    /** @var string A short symbolic name that will be used as an alias for the joined table. */
    public $foreign_alias;

    /**
     * When $prefix is set, then all the fields generated through
     * our wrappers will be automatically prefixed inside the model.
     *
     * @var string
     */
    protected $prefix = '';

    /** @var mixed ID indexed by spl_object_id(entity) used by a joined table. */
    protected $idByOid;

    /** @var array<int, array<string, mixed>> Data indexed by spl_object_id(entity) which is populated here as the save/insert progresses. */
    private $saveBufferByOid = [];

    public function __construct(string $foreign_table = null)
    {
        $this->foreign_table = $foreign_table;

        // handle foreign table containing a dot - that will be reverse join
        if (strpos($this->foreign_table, '.') !== false) {
            // split by LAST dot in foreign_table name
            [$this->foreign_table, $this->foreign_field] = preg_split('~\.+(?=[^.]+$)~', $this->foreign_table);
            $this->reverse = true;
        }
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

    protected function onHookToOwnerBoth(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->short_name; // use static function to allow this object to be GCed

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

    protected function onHookToOwnerEntity(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->short_name; // use static function to allow this object to be GCed

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
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '#join_' . $this->foreign_table;
    }

    protected function init(): void
    {
        $this->_init();

        // owner model should have id_field set
        $id_field = $this->getOwner()->id_field;
        if (!$id_field) {
            throw (new Exception('Joins owner model should have id_field set'))
                ->addMoreInfo('model', $this->getOwner());
        }

        if ($this->reverse === true) {
            if ($this->master_field && $this->master_field !== $id_field) { // TODO not implemented yet, see https://github.com/atk4/data/issues/803
                throw (new Exception('Joining tables on non-id fields is not implemented yet'))
                    ->addMoreInfo('master_field', $this->master_field)
                    ->addMoreInfo('id_field', $this->id_field);
            }

            if (!$this->master_field) {
                $this->master_field = $id_field;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = $this->getModelTableString($this->getOwner()) . '_' . $id_field;
            }
        } else {
            $this->reverse = false;

            if (!$this->master_field) {
                $this->master_field = $this->foreign_table . '_' . $id_field;
            }

            if (!$this->foreign_field) {
                $this->foreign_field = $id_field;
            }
        }

        $this->onHookToOwnerEntity(Model::HOOK_AFTER_UNLOAD, \Closure::fromCallable([$this, 'afterUnload']));

        // if kind is not specified, figure out join type
        if (!$this->kind) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }
    }

    /**
     * Adding field into join will automatically associate that field
     * with this join. That means it won't be loaded from $table, but
     * form the join instead.
     */
    public function addField(string $name, array $seed = []): Field
    {
        $seed['joinName'] = $this->short_name;

        return $this->getOwner()->addField($this->prefix . $name, $seed);
    }

    /**
     * Adds multiple fields.
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $defaults = [])
    {
        foreach ($fields as $name => $seed) {
            if (is_int($name)) {
                $name = $seed;
                $seed = [];
            }

            $this->addField($name, Factory::mergeSeeds($seed, $defaults));
        }

        return $this;
    }

    /**
     * Another join will be attached to a current join.
     *
     * @param array<string, mixed> $defaults
     */
    public function join(string $foreign_table, array $defaults = []): self
    {
        $defaults['joinName'] = $this->short_name;

        return $this->getOwner()->join($foreign_table, $defaults);
    }

    /**
     * Another leftJoin will be attached to a current join.
     *
     * @param array<string, mixed> $defaults
     */
    public function leftJoin(string $foreign_table, array $defaults = []): self
    {
        $defaults['joinName'] = $this->short_name;

        return $this->getOwner()->leftJoin($foreign_table, $defaults);
    }

    /**
     * Creates reference based on a field from the join.
     *
     * @return Reference\HasOne
     */
    public function hasOne(string $link, array $defaults = [])
    {
        $defaults['joinName'] = $this->short_name;

        return $this->getOwner()->hasOne($link, $defaults);
    }

    /**
     * Creates reference based on the field from the join.
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $link, array $defaults = [])
    {
        $defaults = array_merge([
            'our_field' => $this->id_field,
            'their_field' => $this->getModelTableString($this->getOwner()) . '_' . $this->id_field,
        ], $defaults);

        return $this->getOwner()->hasMany($link, $defaults);
    }

    /**
     * Wrapper for containsOne that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @return ???
     */
    /*
    public function containsOne(Model $model, array $defaults = [])
    {
        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsOne($model, $defaults);
    }
    */

    /**
     * Wrapper for containsMany that will associate field
     * with join.
     *
     * @todo NOT IMPLEMENTED !
     *
     * @return ???
     */
    /*
    public function containsMany(Model $model, array $defaults = [])
    {
        if (is_string($defaults[0])) {
            $defaults[0] = $this->addField($defaults[0], ['system' => true]);
        }

        return parent::containsMany($model, $defaults);
    }
    */

    /**
     * Will iterate through this model by pulling
     *  - fields
     *  - references
     *  - conditions.
     *
     * and then will apply them locally. If you think that any fields
     * could clash, then use ['prefix' => 'm2'] which will be pre-pended
     * to all the fields. Conditions will be automatically mapped.
     *
     * @todo NOT IMPLEMENTED !
     */
    /*
    public function importModel(Model $model, array $defaults = [])
    {
        // not implemented yet !!!
    }
    */

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
     * @internal should be not used outside atk4/data
     */
    protected function getAndUnsetSaveBuffer(Model $entity): array
    {
        $res = $this->saveBufferByOid[spl_object_id($entity)];
        $this->unsetSaveBuffer($entity);

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

    /**
     * Clears id and save buffer.
     */
    protected function afterUnload(Model $entity): void
    {
        $this->unsetId($entity);
        $this->unsetSaveBuffer($entity);
    }
}
