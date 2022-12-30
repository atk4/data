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

        return \Closure::bind(function () use ($type, $platform) {
            // make sure handles are initialized
            $type->convertToDatabaseValue(new \stdClass(), $platform);

            TestCase::assertSame(count($type->handles), count($type->handlesIndex));

            return $type->handles;
        }, null, LocalObjectType::class)();
    }

    protected function setUp(): void
    {
        parent::setUp();

        static::assertCount(0, $this->getLocalObjectHandles());
    }

    protected function tearDown(): void
    {
        static::assertCount(0, $this->getLocalObjectHandles());

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
        static::assertMatchesRegularExpression('~^\w+-\w+$~', $v1);
        static::assertNotSame($v1, $v2);
        static::assertNotSame($v1, $v3);

        static::assertSame($obj1, $t1->convertToPHPValue($v1, $platform));
        static::assertSame($obj2, $t1->convertToPHPValue($v2, $platform));
        static::assertSame($obj1, $t2->convertToPHPValue($v3, $platform));

        static::assertSame($v1, $t1->convertToDatabaseValue($obj1, $platform));
        static::assertSame($obj1, $t1->convertToPHPValue($v1, $platform));

        static::assertCount(2, $this->getLocalObjectHandles($t1));
        static::assertCount(1, $this->getLocalObjectHandles($t2));
        $obj1WeakRef = \WeakReference::create($obj1);
        static::assertSame($obj1, $obj1WeakRef->get());
        unset($obj1);
        static::assertCount(1, $this->getLocalObjectHandles($t1));
        static::assertCount(0, $this->getLocalObjectHandles($t2));
        static::assertNull($obj1WeakRef->get());
        unset($obj2);
        static::assertCount(0, $this->getLocalObjectHandles($t1));
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
        static::assertNotNull($objWeakRef->get());
        static::assertSame($objWeakRef->get(), $entity->get('v'));

        $entity->save();
        static::assertNotNull($objWeakRef->get());
        static::assertSame($objWeakRef->get(), $entity->get('v'));

        $entity->reload();
        static::assertNotNull($objWeakRef->get());
        static::assertSame($objWeakRef->get(), $entity->get('v'));

        $entity2 = $model->load($entity->getId());
        $entity->unload();
        static::assertNotNull($objWeakRef->get());
        static::assertNull($entity->get('v'));
        static::assertSame($objWeakRef->get(), $entity2->get('v'));

        $entity2->unload();
        static::assertNull($objWeakRef->get());
        static::assertNull($entity2->get('v'));
    }
}
