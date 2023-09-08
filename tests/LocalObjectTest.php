<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Type\LocalObjectHandle;
use Atk4\Data\Type\LocalObjectType;
use Doctrine\DBAL\Types as DbalTypes;

class LocalObjectTest extends TestCase
{
    /**
     * @return \WeakMap<object, LocalObjectHandle>
     */
    protected function getLocalObjectHandles(LocalObjectType $type = null): \WeakMap
    {
        if ($type === null) {
            /** @var LocalObjectType */
            $type = DbalTypes\Type::getType('atk4_local_object');
        }

        $platform = $this->getDatabasePlatform();

        return \Closure::bind(static function () use ($type, $platform) {
            // make sure handles are initialized
            $type->convertToDatabaseValue(new \stdClass(), $platform);

            TestCase::assertSame(count($type->handles), count($type->handlesIndex));

            return $type->handles;
        }, null, LocalObjectType::class)();
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::assertCount(0, $this->getLocalObjectHandles());
    }

    protected function tearDown(): void
    {
        self::assertCount(0, $this->getLocalObjectHandles());

        parent::tearDown();
    }

    public function testTypeBasic(): void
    {
        $t1 = new LocalObjectType();
        $t2 = new LocalObjectType();
        $platform = $this->getDatabasePlatform();

        $obj1 = new \stdClass();
        $obj2 = new \stdClass();

        $v1 = $t1->convertToDatabaseValue($obj1, $platform);
        $v2 = $t1->convertToDatabaseValue($obj2, $platform);
        $v3 = $t2->convertToDatabaseValue($obj1, $platform);
        self::assertMatchesRegularExpression('~^stdClass-\w+-\w+$~', $v1);
        self::assertNotSame($v1, $v2);
        self::assertNotSame($v1, $v3);

        self::assertSame($obj1, $t1->convertToPHPValue($v1, $platform));
        self::assertSame($obj2, $t1->convertToPHPValue($v2, $platform));
        self::assertSame($obj1, $t2->convertToPHPValue($v3, $platform));

        self::assertSame($v1, $t1->convertToDatabaseValue($obj1, $platform));
        self::assertSame($obj1, $t1->convertToPHPValue($v1, $platform));

        self::assertCount(2, $this->getLocalObjectHandles($t1));
        self::assertCount(1, $this->getLocalObjectHandles($t2));
        $obj1WeakRef = \WeakReference::create($obj1);
        self::assertSame($obj1, $obj1WeakRef->get());
        unset($obj1);
        self::assertCount(1, $this->getLocalObjectHandles($t1));
        self::assertCount(0, $this->getLocalObjectHandles($t2));
        self::assertNull($obj1WeakRef->get());
        unset($obj2);
        self::assertCount(0, $this->getLocalObjectHandles($t1));

        $obj3 = new \stdClass();
        $v4 = $t1->convertToDatabaseValue($obj3, $platform);
        self::assertNotNull($v4);
        self::assertNotSame($v4, $v1);
        self::assertNotSame($v4, $v2);
        self::assertNotSame($v4, $v3);
    }

    public function testTypeCloneException(): void
    {
        $t = new LocalObjectType();

        $this->expectException(\Error::class);
        clone $t;
    }

    public function testTypeDifferentInstanceException(): void
    {
        $t1 = new LocalObjectType();
        $t2 = new LocalObjectType();
        $platform = $this->getDatabasePlatform();

        $obj = new \stdClass();
        $v = $t1->convertToDatabaseValue($obj, $platform);

        $t1->convertToPHPValue($v, $platform);

        $this->expectException(Exception::class);
        $t2->convertToPHPValue($v, $platform);
    }

    public function testTypeReleasedException(): void
    {
        $t = new LocalObjectType();
        $platform = $this->getDatabasePlatform();

        $obj = new \stdClass();
        $v = $t->convertToDatabaseValue($obj, $platform);

        $t->convertToPHPValue($v, $platform);

        unset($obj);
        if (\PHP_MAJOR_VERSION < 8) { // force WeakMap polyfill housekeeping
            $this->getLocalObjectHandles($t);
        }

        $this->expectException(Exception::class);
        $t->convertToPHPValue($v, $platform);
    }

    public function testEntityKeepsReference(): void
    {
        $model = new Model($this->db, ['table' => 't']);
        $model->addField('v', ['type' => 'atk4_local_object']);
        $this->createMigrator($model)->create();

        $entity = $model->createEntity();
        $obj = new \stdClass();
        $objWeakRef = \WeakReference::create($obj);
        $entity->set('v', $obj);
        unset($obj);
        self::assertNotNull($objWeakRef->get());
        self::assertSame($objWeakRef->get(), $entity->get('v'));

        $entity->save();
        self::assertNotNull($objWeakRef->get());
        self::assertSame($objWeakRef->get(), $entity->get('v'));

        $entity->reload();
        self::assertNotNull($objWeakRef->get());
        self::assertSame($objWeakRef->get(), $entity->get('v'));

        $entity2 = $model->load($entity->getId());
        $entity->unload();
        self::assertNotNull($objWeakRef->get());
        self::assertNull($entity->get('v'));
        self::assertSame($objWeakRef->get(), $entity2->get('v'));

        $entity2->unload();
        self::assertNull($objWeakRef->get());
        self::assertNull($entity2->get('v'));
    }

    public function testDatabaseValueLengthIsLimited(): void
    {
        $t = new LocalObjectType();
        $platform = $this->getDatabasePlatform();

        $obj1 = new LocalObjectDummyClassWithLongNameAWithLongNameBWithLongNameCWithLongNameDWithLongNameEWithLongNameFWithLongNameGWithLongNameHWithLongNameIWithLongNameJWithLongNameKWithLongNameL();
        $obj2 = new class() extends LocalObjectDummyClassWithLongNameAWithLongNameBWithLongNameCWithLongNameDWithLongNameEWithLongNameFWithLongNameGWithLongNameHWithLongNameIWithLongNameJWithLongNameKWithLongNameL {};

        $v1 = $t->convertToDatabaseValue($obj1, $platform);
        $v2 = $t->convertToDatabaseValue($obj2, $platform);

        self::assertSame($obj1, $t->convertToPHPValue($v1, $platform));
        self::assertSame($obj2, $t->convertToPHPValue($v2, $platform));

        self::assertLessThan(250, strlen($v1));
        self::assertLessThan(250, strlen($v2));

        self::assertSame('Atk4\Data\Tests\LocalObjectDummyClassWithLongNameAWithLongNameBWithLongNameCWith...eFWithLongNameGWithLongNameHWithLongNameIWithLongNameJWithLongNameKWithLongNameL', explode('-', $v1)[0]);
        self::assertSame('Atk4\Data\Tests\LocalObjectDummyClassWithLongNameAWithLongNameBWithLongNameCWith...NameGWithLongNameHWithLongNameIWithLongNameJWithLongNameKWithLongNameL@anonymous', explode('-', $v2)[0]);
    }
}

class LocalObjectDummyClassWithLongNameAWithLongNameBWithLongNameCWithLongNameDWithLongNameEWithLongNameFWithLongNameGWithLongNameHWithLongNameIWithLongNameJWithLongNameKWithLongNameL {}
