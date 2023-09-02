<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types as DbalTypes;

/**
 * Tests that condition is applied when traversing hasMany
 * also that the original model can be re-loaded with a different
 * value without making any condition stick.
 */
class ReferenceSqlTest extends TestCase
{
    public function testBasic(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
            'order' => [
                ['amount' => 20, 'user_id' => 1],
                ['amount' => 15, 'user_id' => 2],
                ['amount' => 5, 'user_id' => 1],
                ['amount' => 3, 'user_id' => 1],
                ['amount' => 8, 'user_id' => 3],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount', ['type' => 'integer']);
        $o->addField('user_id', ['type' => 'integer']);

        $u->hasMany('Orders', ['model' => $o]);

        $oo = $u->load(1)->ref('Orders');
        $ooo = $oo->load(1);
        self::assertSame(20, $ooo->get('amount'));
        $ooo = $oo->tryLoad(2);
        self::assertNull($ooo);
        $ooo = $oo->load(3);
        self::assertSame(5, $ooo->get('amount'));

        $oo = $u->load(2)->ref('Orders');
        $ooo = $oo->tryLoad(1);
        self::assertNull($ooo);
        $ooo = $oo->load(2);
        self::assertSame(15, $ooo->get('amount'));
        $ooo = $oo->tryLoad(3);
        self::assertNull($ooo);

        $oo = $u->addCondition('id', '>', '1')->ref('Orders');

        $this->assertSameSql(
            'select `id`, `amount`, `user_id` from `order` `_O_7442e29d7d53` where `user_id` in (select `id` from `user` where `id` > :a)',
            $oo->action('select')->render()[0]
        );
    }

    /**
     * Tests to make sure refLink properly generates field links.
     */
    public function testLink(): void
    {
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->addField('user_id', ['type' => 'integer']);

        $u->hasMany('Orders', ['model' => $o]);

        $this->assertSameSql(
            'select `id`, `amount`, `user_id` from `order` `_O_7442e29d7d53` where `user_id` = `user`.`id`',
            $u->refLink('Orders')->action('select')->render()[0]
        );
    }

    public function testBasic2(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'currency' => 'EUR'],
                ['name' => 'Peter', 'currency' => 'GBP'],
                ['name' => 'Joe', 'currency' => 'EUR'],
            ],
            'currency' => [
                ['currency' => 'EUR', 'name' => 'Euro'],
                ['currency' => 'USD', 'name' => 'Dollar'],
                ['currency' => 'GBP', 'name' => 'Pound'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('currency');

        $c = new Model($this->db, ['table' => 'currency']);
        $c->addField('currency');
        $c->addField('name');

        $this->markTestIncompleteOnMySQL56PlatformAsCreateUniqueStringIndexHasLengthLimit();

        $u->hasOne('cur', ['model' => $c, 'ourField' => 'currency', 'theirField' => 'currency']);
        $this->createMigrator()->createForeignKey($u->getReference('cur'));

        $cc = $u->load(1)->ref('cur');
        self::assertSame('Euro', $cc->get('name'));

        $cc = $u->load(2)->ref('cur');
        self::assertSame('Pound', $cc->get('name'));
    }

    public function testLink2(): void
    {
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('currency_code');

        $c = new Model($this->db, ['table' => 'currency']);
        $c->addField('code');
        $c->addField('name');

        $u->hasMany('cur', ['model' => $c, 'ourField' => 'currency_code', 'theirField' => 'code']);

        $this->assertSameSql(
            'select `id`, `code`, `name` from `currency` `_c_b5fddf1ef601` where `code` = `user`.`currency_code`',
            $u->refLink('cur')->action('select')->render()[0]
        );
    }

    /**
     * Tests that condition defined on the parent model is retained when traversing through hasMany.
     */
    public function testBasicOne(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
            'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');

        $o->hasOne('user_id', ['model' => $u]);

        self::assertSame('John', $o->load(1)->ref('user_id')->get('name'));
        self::assertSame('Peter', $o->load(2)->ref('user_id')->get('name'));
        self::assertSame('John', $o->load(3)->ref('user_id')->get('name'));
        self::assertSame('Joe', $o->load(5)->ref('user_id')->get('name'));

        $o->addCondition('amount', '>', 6);
        $o->addCondition('amount', '<', 9);

        $this->assertSameSql(
            'select `id`, `name` from `user` `_u_e8701ad48ba0` where `id` in (select `user_id` from `order` where (`amount` > :a and `amount` < :b))',
            $o->ref('user_id')->action('select')->render()[0]
        );
    }

    /**
     * Tests Join::addField's ability to create expressions from foreign fields.
     */
    public function testAddOneField(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '2001-01-02'],
                ['id' => 2, 'name' => 'Peter', 'date' => '2004-08-20'],
                ['id' => 3, 'name' => 'Joe', 'date' => '2005-08-20'],
            ],
            'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('date', ['type' => 'date']);

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u])->addFields([
            'username' => 'name',
            ['date', 'type' => 'date'],
        ]);

        self::assertSame('John', $o->load(1)->get('username'));
        self::{'assertEquals'}(new \DateTime('2001-01-02 UTC'), $o->load(1)->get('date'));

        self::assertSame('Peter', $o->load(2)->get('username'));
        self::assertSame('John', $o->load(3)->get('username'));
        self::assertSame('Joe', $o->load(5)->get('username'));

        // few more tests
        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u])->addFields([
            'username' => 'name',
            'thedate' => ['date', 'type' => 'date'],
        ]);
        self::assertSame('John', $o->load(1)->get('username'));
        self::{'assertEquals'}(new \DateTime('2001-01-02 UTC'), $o->load(1)->get('thedate'));

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u])->addField('date', null, ['type' => 'date']);
        self::{'assertEquals'}(new \DateTime('2001-01-02 UTC'), $o->load(1)->get('date'));
    }

    public function testRelatedExpression(): void
    {
        $vat = 0.23;

        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                ['id' => 2, 'ref_no' => 'INV204'],
                ['id' => 3, 'ref_no' => 'INV205'],
            ],
            'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('ref_no');

        $l = new Model($this->db, ['table' => 'invoice_line']);
        $l->addField('invoice_id', ['type' => 'integer']);
        $l->addField('total_net');
        $l->addField('total_vat');
        $l->addField('total_gross');

        $i->hasMany('line', ['model' => $l]);
        $i->addExpression('total_net', ['expr' => $i->refLink('line')->action('fx', ['sum', 'total_net'])]);

        $this->assertSameSql(
            'select `id`, `ref_no`, (select sum(`total_net`) from `invoice_line` `_l_6438c669e0d0` where `invoice_id` = `invoice`.`id`) `total_net` from `invoice`',
            $i->action('select')->render()[0]
        );
    }

    public function testReferenceWithObjectId(): void
    {
        $this->setDb([
            'file' => [
                1 => ['id' => 1, 'name' => 'a.txt', 'parentDirectoryId' => null],
                ['id' => 2, 'name' => 'u', 'parentDirectoryId' => null],
                ['id' => 3, 'name' => 'v', 'parentDirectoryId' => 2],
                ['id' => 4, 'name' => 'w', 'parentDirectoryId' => 2],
                ['id' => 5, 'name' => 'b.txt', 'parentDirectoryId' => 2],
                ['id' => 6, 'name' => 'c.txt', 'parentDirectoryId' => 3],
                ['id' => 7, 'name' => 'd.txt', 'parentDirectoryId' => 2],
                ['id' => 8, 'name' => 'e.txt', 'parentDirectoryId' => 4],
            ],
        ]);

        $integerWrappedType = new class() extends DbalTypes\Type {
            /**
             * TODO: Remove once DBAL 3.x support is dropped.
             */
            public function getName(): string
            {
                return self::class;
            }

            public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
            {
                return DbalTypes\Type::getType(DbalTypes\Types::INTEGER)->getSQLDeclaration($fieldDeclaration, $platform);
            }

            public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
            {
                if ($value === null) {
                    return null;
                }

                return DbalTypes\Type::getType('integer')->convertToDatabaseValue($value->getValue(), $platform);
            }

            public function convertToPHPValue($value, AbstractPlatform $platform): ?object
            {
                if ($value === null) {
                    return null;
                }

                return new class(DbalTypes\Type::getType('integer')->convertToPHPValue($value, $platform)) {
                    private int $id;

                    public function __construct(int $id)
                    {
                        $this->id = $id;
                    }

                    public function getValue(): int
                    {
                        return $this->id;
                    }
                };
            }
        };
        $integerWrappedTypeName = $integerWrappedType->getName(); // @phpstan-ignore-line

        DbalTypes\Type::addType($integerWrappedTypeName, get_class($integerWrappedType));
        try {
            $file = new Model($this->db, ['table' => 'file']);
            $file->getField('id')->type = $integerWrappedTypeName;
            $file->addField('name');
            $file->hasOne('parentDirectory', [
                'model' => $file,
                'type' => $integerWrappedTypeName,
                'ourField' => 'parentDirectoryId',
            ]);
            $file->hasMany('childFiles', [
                'model' => $file,
                'theirField' => 'parentDirectoryId',
            ]);

            $fileEntity = $file->loadBy('name', 'v');
            self::assertSame(3, $fileEntity->getId()->getValue());
            self::assertSame(3, $fileEntity->get('id')->getValue());
            self::assertSame($fileEntity->getId(), $fileEntity->get('id'));

            $c = 0;
            unset($fileEntity);
            foreach ($file as $id => $fileEntity) {
                self::assertSame($fileEntity->getId()->getValue(), $id->getValue());
                self::assertSame($fileEntity->getId(), $id);
                ++$c;
            }
            self::assertSame(8, $c);

            $fileEntity = $file->loadBy('name', 'v')->ref('childFiles')->createEntity();
            self::assertSame(3, $fileEntity->get('parentDirectoryId')->getValue());
            $fileEntity->save(['name' => 'x']);
            self::assertSame(9, $fileEntity->get('id')->getValue());

            $fileEntity = $fileEntity->ref('childFiles')->createEntity();
            self::assertSame(9, $fileEntity->get('parentDirectoryId')->getValue());
            $fileEntity->save(['name' => 'y.txt']);

            $createWrappedIntegerFx = function (int $v) use ($integerWrappedType): object {
                return $integerWrappedType->convertToPHPValue($v, $this->getDatabasePlatform());
            };

            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(10), 'name' => 'y.txt', 'parentDirectoryId' => $createWrappedIntegerFx(9)],
            ], $fileEntity->getModel()->export());
            self::assertSame([], $fileEntity->ref('childFiles')->export());

            $fileEntity = $fileEntity->ref('parentDirectory');
            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(9), 'name' => 'x', 'parentDirectoryId' => $createWrappedIntegerFx(3)],
            ], $fileEntity->getModel()->export());
            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(10), 'name' => 'y.txt', 'parentDirectoryId' => $createWrappedIntegerFx(9)],
            ], $fileEntity->ref('childFiles')->export());

            $fileEntity = $fileEntity->ref('parentDirectory');
            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(3), 'name' => 'v', 'parentDirectoryId' => $createWrappedIntegerFx(2)],
            ], $fileEntity->getModel()->export());
            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(6), 'name' => 'c.txt', 'parentDirectoryId' => $createWrappedIntegerFx(3)],
                ['id' => $createWrappedIntegerFx(9), 'name' => 'x', 'parentDirectoryId' => $createWrappedIntegerFx(3)],
            ], $fileEntity->ref('childFiles')->export());
            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(6), 'name' => 'c.txt', 'parentDirectoryId' => $createWrappedIntegerFx(3)],
                ['id' => $createWrappedIntegerFx(8), 'name' => 'e.txt', 'parentDirectoryId' => $createWrappedIntegerFx(4)],
                ['id' => $createWrappedIntegerFx(9), 'name' => 'x', 'parentDirectoryId' => $createWrappedIntegerFx(3)],
            ], $fileEntity->ref('parentDirectory')->ref('childFiles')->ref('childFiles')->export());

            $fileEntity = $fileEntity->ref('parentDirectory');
            self::{'assertEquals'}([
                ['id' => $createWrappedIntegerFx(2), 'name' => 'u', 'parentDirectoryId' => null],
            ], $fileEntity->getModel()->export());
        } finally {
            \Closure::bind(static function () use ($integerWrappedTypeName) {
                $dbalTypeRegistry = DbalTypes\Type::getTypeRegistry();
                unset($dbalTypeRegistry->instances[$integerWrappedTypeName]);
            }, null, DbalTypes\TypeRegistry::class)();
        }
    }

    public function testAggregateHasMany(): void
    {
        $vat = 0.23;

        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                ['id' => 2, 'ref_no' => 'INV204'],
                ['id' => 3, 'ref_no' => 'INV205'],
            ],
            'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('ref_no');

        $l = new Model($this->db, ['table' => 'invoice_line']);
        $l->addField('invoice_id', ['type' => 'integer']);
        $l->addField('total_net', ['type' => 'atk4_money']);
        $l->addField('total_vat', ['type' => 'atk4_money']);
        $l->addField('total_gross', ['type' => 'atk4_money']);

        $i->hasMany('line', ['model' => $l])->addFields([
            'total_net' => ['aggregate' => 'sum'],
            'total_vat' => ['aggregate' => 'sum', 'type' => 'atk4_money'],
            'total_gross' => ['aggregate' => 'sum', 'type' => 'atk4_money'],
        ]);
        $i = $i->load('1');

        // type was set explicitly
        self::assertSame('atk4_money', $i->getField('total_vat')->type);

        // type was not set and is not inherited
        self::assertSame('string', $i->getField('total_net')->type);

        self::assertSame(40.0, (float) $i->get('total_net'));
        self::assertSame(9.2, $i->get('total_vat'));
        self::assertSame(49.2, $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => ($n = 1), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
            ['total_net' => ($n = 2), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
        ]);
        $i->reload();

        self::assertSame($n = 43.0, (float) $i->get('total_net'));
        self::assertSame($n * $vat, $i->get('total_vat'));
        self::assertSame($n * ($vat + 1), $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => null, 'total_vat' => null, 'total_gross' => 1],
        ]);
        $i->reload();

        self::assertSame($n = 43.0, (float) $i->get('total_net'));
        self::assertSame($n * $vat, $i->get('total_vat'));
        self::assertSame($n * ($vat + 1) + 1, $i->get('total_gross'));
    }

    public function testOtherAggregates(): void
    {
        $vat = 0.23;

        $this->setDb([
            'list' => [
                1 => ['id' => 1, 'name' => 'Meat'],
                ['id' => 2, 'name' => 'Veg'],
                ['id' => 3, 'name' => 'Fruit'],
            ],
            'item' => [
                ['name' => 'Apple', 'code' => 'ABC', 'list_id' => 3],
                ['name' => 'Banana', 'code' => 'DEF', 'list_id' => 3],
                ['name' => 'Pork', 'code' => 'GHI', 'list_id' => 1],
                ['name' => 'Chicken', 'code' => null, 'list_id' => 1],
                ['name' => 'Pear', 'code' => null, 'list_id' => 3],
            ],
        ]);

        $buildLengthSqlFx = function (string $v): string {
            return ($this->getDatabasePlatform() instanceof SQLServerPlatform ? 'LEN' : 'LENGTH') . '(' . $v . ')';
        };

        $buildSumWithIntegerCastSqlFx = function (string $v): string {
            if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform
                || $this->getDatabasePlatform() instanceof SQLServerPlatform) {
                $v = 'CAST(' . $v . ' AS INT)';
            }

            return 'SUM(' . $v . ')';
        };

        $l = new Model($this->db, ['table' => 'list']);
        $l->addField('name');

        $i = new Model($this->db, ['table' => 'item']);
        $i->addField('list_id', ['type' => 'integer']);
        $i->addField('name');
        $i->addField('code');

        $l->hasMany('Items', ['model' => $i])->addFields([
            'items_name' => ['aggregate' => 'count', 'field' => 'name', 'type' => 'integer'],
            'items_code' => ['aggregate' => 'count', 'field' => 'code', 'type' => 'integer'], // counts only not-null values
            'items_star' => ['aggregate' => 'count', 'type' => 'integer'], // no field set, counts all rows with count(*)
            'items_c:' => ['concat' => '::', 'field' => 'name'],
            'items_c-' => ['aggregate' => $i->dsql()->groupConcat($i->expr('[name]'), '-')],
            'len' => ['aggregate' => $i->expr($buildSumWithIntegerCastSqlFx($buildLengthSqlFx('[name]'))), 'type' => 'integer'], // TODO cast should be implicit when using "aggregate", sandpit http://sqlfiddle.com/#!17/0d2c0/3
            'len2' => ['expr' => $buildSumWithIntegerCastSqlFx($buildLengthSqlFx('[name]')), 'type' => 'integer'],
            'chicken5' => ['expr' => $buildSumWithIntegerCastSqlFx('[]'), 'args' => ['5'], 'type' => 'integer'],
        ]);

        $ll = $l->load(1);
        self::assertSame(2, $ll->get('items_name')); // 2 not-null values
        self::assertSame(1, $ll->get('items_code')); // only 1 not-null value
        self::assertSame(2, $ll->get('items_star')); // 2 rows in total
        self::assertSame($ll->get('items_c:') === 'Pork::Chicken' ? 'Pork::Chicken' : 'Chicken::Pork', $ll->get('items_c:'));
        self::assertSame($ll->get('items_c-') === 'Pork-Chicken' ? 'Pork-Chicken' : 'Chicken-Pork', $ll->get('items_c-'));
        self::assertSame(strlen('Chicken') + strlen('Pork'), $ll->get('len'));
        self::assertSame(strlen('Chicken') + strlen('Pork'), $ll->get('len2'));
        self::assertSame(10, $ll->get('chicken5'));

        $ll = $l->load(2);
        self::assertSame(0, $ll->get('items_name'));
        self::assertSame(0, $ll->get('items_code'));
        self::assertSame(0, $ll->get('items_star'));
        self::assertNull($ll->get('items_c:'));
        self::assertNull($ll->get('items_c-'));
        self::assertNull($ll->get('len'));
        self::assertNull($ll->get('len2'));
        self::assertNull($ll->get('chicken5'));
    }

    protected function setupDbForTraversing(): Model
    {
        $this->setDb([
            'user' => [
                ['name' => 'Vinny', 'company_id' => 1],
                ['name' => 'Zoe', 'company_id' => 2],
            ],
            'company' => [
                ['name' => 'Vinny Company'],
                ['name' => 'Zoe Company'],
            ],
            'order' => [
                ['company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
                ['company_id' => 2, 'description' => 'Zoe Company Order', 'amount' => 10.0],
                ['company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('company_id', ['type' => 'integer']);

        $company = new Model($this->db, ['table' => 'company']);
        $company->addField('name');

        $user->hasOne('Company', ['model' => $company, 'ourField' => 'company_id', 'theirField' => 'id']);

        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('company_id', ['type' => 'integer']);
        $order->addField('description');
        $order->addField('amount', ['default' => 20, 'type' => 'float']);

        $company->hasMany('Orders', ['model' => $order]);

        return $user;
    }

    public function testReferenceHasOneTraversing(): void
    {
        $user = $this->setupDbForTraversing();
        $userEntity = $user->load(1);

        self::assertSameExportUnordered([
            ['id' => 1, 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => 3, 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $userEntity->ref('Company')->ref('Orders')->export());

        self::assertSameExportUnordered([
            ['id' => 1, 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => 2, 'company_id' => 2, 'description' => 'Zoe Company Order', 'amount' => 10.0],
            ['id' => 3, 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $userEntity->getModel()->ref('Company')->ref('Orders')->export());
    }

    public function testUnloadedEntityTraversingHasOne(): void
    {
        $user = $this->setupDbForTraversing();
        $userEntity = $user->createEntity();

        $companyEntity = $userEntity->ref('Company');
        self::assertFalse($companyEntity->isLoaded());
    }

    public function testUnloadedEntityTraversingHasOneEx(): void
    {
        $user = $this->setupDbForTraversing();
        $user->getReference('Company')->setDefaults(['ourField' => 'id']);
        $userEntity = $user->createEntity();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to traverse on null value');
        $userEntity->ref('Company');
    }

    public function testUnloadedEntityTraversingHasManyEx(): void
    {
        $user = $this->setupDbForTraversing();
        $companyEntity = $user->ref('Company')->createEntity();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to traverse on null value');
        $companyEntity->ref('Orders');
    }

    public function testReferenceHook(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'contact_id' => 2],
                ['name' => 'Peter', 'contact_id' => null],
                ['name' => 'Joe', 'contact_id' => 3],
            ],
            'contact' => [
                ['address' => 'Sue contact'],
                ['address' => 'John contact'],
                ['address' => 'Joe contact'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $c = new Model($this->db, ['table' => 'contact']);
        $c->addField('address');

        $u->hasOne('contact_id', ['model' => $c])
            ->addField('address');

        $uu = $u->load(1);
        self::assertSame('John contact', $uu->get('address'));
        self::assertSame('John contact', $uu->ref('contact_id')->get('address'));

        $uu = $u->load(2);
        self::assertNull($uu->get('address'));
        self::assertNull($uu->get('contact_id'));
        self::assertNull($uu->ref('contact_id')->get('address'));

        $uu = $u->load(3);
        self::assertSame('Joe contact', $uu->get('address'));
        self::assertSame('Joe contact', $uu->ref('contact_id')->get('address'));

        $uu = $u->load(2);
        $uu->ref('contact_id')->save(['address' => 'Peters new contact']);

        self::assertNotNull($uu->get('contact_id'));
        self::assertSame('Peters new contact', $uu->ref('contact_id')->get('address'));

        $uu->save()->reload();
        self::assertSame('Peters new contact', $uu->ref('contact_id')->get('address'));
        self::assertSame('Peters new contact', $uu->get('address'));
    }

    public function testHasOneIdFieldAsOurField(): void
    {
        $this->setDb([
            'player' => [
                ['name' => 'John'],
                ['name' => 'Messi'],
                ['name' => 'Ronaldo'],
            ],
            'stadium' => [
                ['name' => 'Sue bernabeu', 'player_id' => 3],
                ['name' => 'John camp', 'player_id' => 1],
            ],
        ]);

        $s = (new Model($this->db, ['table' => 'stadium']));
        $s->addField('name');
        $s->addField('player_id', ['type' => 'integer']);

        $p = new Model($this->db, ['table' => 'player']);
        $p->addField('name');
        $p->delete(2);
        $p->hasOne('Stadium', ['model' => $s, 'ourField' => 'id', 'theirField' => 'player_id']);
        $this->createMigrator()->createForeignKey($p->getReference('Stadium'));

        $s->createEntity()->save(['name' => 'Nou camp nou', 'player_id' => 4]);
        $pEntity = $p->createEntity()->save(['name' => 'Ivan']);

        self::assertSame('Nou camp nou', $pEntity->ref('Stadium')->get('name'));
        self::assertSame(4, $pEntity->ref('Stadium')->get('player_id'));
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user->hasMany('Orders', ['model' => [Model::class, 'table' => 'order'], 'theirField' => 'id']);
        $o = $user->ref('Orders');
        self::assertSame('order', $o->table);
    }

    public function testAddTitle(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
            'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');

        // by default not set
        $o->hasOne('user_id', ['model' => $u]);
        self::assertSame($o->getField('user_id')->isVisible(), true);

        $o->getReference('user_id')->addTitle();
        self::assertTrue($o->hasField('user'));
        self::assertSame($o->getField('user')->isVisible(), true);
        self::assertSame($o->getField('user_id')->isVisible(), false);

        // if it is set manually then it will not be changed
        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u]);
        $o->getField('user_id')->ui['visible'] = true;
        $o->getReference('user_id')->addTitle();

        self::assertSame($o->getField('user_id')->isVisible(), true);
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneTitleSet(): void
    {
        $dbData = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ],
            'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                ['id' => 2, 'user_id' => 2],
                ['id' => 3, 'user_id' => 1],
            ],
        ];

        $this->setDb($dbData);

        // with default titleField='name'
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user_id', ['model' => $u])->addTitle();

        // change order user by changing titleField value
        $o = $o->load(1);
        self::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('user', 'Peter');
        self::assertNull($o->get('user_id'));
        $o->save();
        self::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // with custom titleField='last_name'
        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user_id', ['model' => $u])->addTitle();

        // change order user by changing titleField value
        $o = $o->load(1);
        self::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('user', 'Foo');
        self::assertNull($o->get('user_id'));
        $o->save();
        self::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // with custom titleField='last_name' and custom link name
        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['model' => $u, 'ourField' => 'user_id'])->addTitle();

        // change order user by changing reference field value
        $o = $o->load(1);
        self::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('my_user', 'Foo');
        self::assertNull($o->get('user_id'));
        $o->save();
        self::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // with custom titleField='last_name' and custom link name
        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['model' => $u, 'ourField' => 'user_id'])->addTitle();

        // change order user by changing ref field and titleField value - same
        $o = $o->load(1);
        self::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('my_user', 'Foo'); // user_id = 2
        $o->set('user_id', 2);
        self::assertSame(2, $o->get('user_id'));
        $o->save();
        self::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // change order user by changing ref field and titleField value - mismatched
        $o = $o->getModel()->load(1);
        self::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('my_user', 'Foo'); // user_id = 2
        $o->set('user_id', 3);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Imported field was changed to an unexpected value');
        $o->save();
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneReferenceCaption(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ],
            'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                ['id' => 2, 'user_id' => 2],
                ['id' => 3, 'user_id' => 1],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        // now the caption is null and is generated from field name
        self::assertSame('Last Name', $u->getField('last_name')->getCaption());

        $u->getField('last_name')->caption = 'Surname';

        // now the caption is not null and the value is returned
        self::assertSame('Surname', $u->getField('last_name')->getCaption());

        $o = (new Model($this->db, ['table' => 'order']));
        $orderUserRef = $o->hasOne('my_user', ['model' => $u, 'ourField' => 'user_id']);
        $orderUserRef->addField('user_last_name', 'last_name');

        $referencedCaption = $o->getField('user_last_name')->getCaption();

        // Test: $field->caption for the field 'last_name' is defined in referenced model (User)
        // When Order add field from Referenced model User
        // caption will be passed to Order field user_last_name
        self::assertSame('Surname', $referencedCaption);
    }

    /**
     * Test if field type is taken from referenced Model if not set in HasOne::addField().
     */
    public function testHasOneReferenceType(): void
    {
        $this->setDb([
            'user' => [
                1 => [
                    'id' => 1,
                    'name' => 'John',
                    'last_name' => 'Doe',
                    'some_number' => 3,
                    'some_other_number' => 4,
                ],
            ],
            'order' => [
                1 => ['id' => 1, 'user_id' => 1],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('last_name');
        $user->addField('some_number');
        $user->addField('some_other_number');
        $user->getField('some_number')->type = 'integer';
        $user->getField('some_other_number')->type = 'integer';
        $order = (new Model($this->db, ['table' => 'order']));
        $orderUserRef = $order->hasOne('my_user', ['model' => $user, 'ourField' => 'user_id']);

        // no type set in defaults, should pull type integer from user model
        $orderUserRef->addField('some_number');
        self::assertSame('integer', $order->getField('some_number')->type);

        // set type in defaults, this should have higher priority than type set in Model
        $orderUserRef->addField('some_other_number', null, ['type' => 'string']);
        self::assertSame('string', $order->getField('some_other_number')->type);
    }
}
