<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Array_ as ArrayPersistence;

class JoinTest extends TestCase
{
    /**
     * @return mixed
     */
    private function getProtected(object $obj, string $name)
    {
        return \Closure::bind(static fn () => $obj->{$name}, null, $obj)();
    }

    public function testDirection(): void
    {
        $db = new ArrayPersistence(['user' => [], 'contact' => []]);
        $m = new Model($db, ['table' => 'user']);
        $m->addField('contact_id', ['type' => 'bigint']);
        $m->addField('test_id', ['type' => 'bigint']);

        $j = $m->join('contact');
        self::assertFalse($j->reverse);
        self::assertSame('contact_id', $this->getProtected($j, 'masterField'));
        self::assertSame('id', $this->getProtected($j, 'foreignField'));

        $j = $m->join('contact2.test_id');
        self::assertTrue($j->reverse);
        self::assertSame('id', $this->getProtected($j, 'masterField'));
        self::assertSame('test_id', $this->getProtected($j, 'foreignField'));

        $j = $m->join('contact3', ['masterField' => 'test_id']);
        self::assertFalse($j->reverse);
        self::assertSame('test_id', $this->getProtected($j, 'masterField'));
        self::assertSame('id', $this->getProtected($j, 'foreignField'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reverse join with non-ID master field is not implemented yet');
        $j = $m->join('contact4.foo_id', ['masterField' => 'test_id', 'reverse' => true]);
        // self::assertTrue($j->reverse);
        // self::assertSame('test_id', $this->getProtected($j, 'masterField'));
        // self::assertSame('foo_id', $this->getProtected($j, 'foreignField'));
    }

    public function testDirectionException(): void
    {
        $db = new ArrayPersistence();
        $m = new Model($db, ['table' => 'user']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reverse join with non-ID master field is not implemented yet');
        $m->join('contact.foo_id', ['masterField' => 'test_id']);
    }

    public function testAddJoinDuplicateNameException(): void
    {
        $db = new ArrayPersistence();
        $m = new Model($db);
        $m->addField('foo_id', ['type' => 'bigint']);
        $m->join('foo');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Join with such name already exists');
        $m->join('foo');
    }

    public function testTypeMismatchException(): void
    {
        $db = new ArrayPersistence();

        $user = new Model($db, ['table' => 'user']);
        $order = new Model($db, ['table' => 'order']);
        $order->addField('placed_by_user_id');
        $order->addCteModel('user', $user);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Join reference type mismatch');
        $order->join('user', ['masterField' => 'placed_by_user_id']);
    }

    public function testTypeMismatchWithDisabledCheck(): void
    {
        $db = new ArrayPersistence();

        $user = new Model($db, ['table' => 'user']);
        $order = new Model($db, ['table' => 'order']);
        $order->addField('placed_by_user_id');
        $order->addCteModel('user', $user);

        $j = $order->join('user', ['masterField' => 'placed_by_user_id', 'checkTheirType' => false]);

        self::assertSame('user', $j->getForeignModel()->table);
        self::assertSame('string', $order->getField('placed_by_user_id')->type);
        self::assertSame('bigint', $j->getForeignModel()->getIdField()->type);
    }

    public function testForeignFieldNameGuessTableWithSchema(): void
    {
        $db = new ArrayPersistence();

        $m = new Model($db, ['table' => 'db.user']);
        $m->addField('contact_id', ['type' => 'bigint']);
        $j = $m->join('contact');
        self::assertFalse($j->reverse);
        self::assertSame('contact_id', $this->getProtected($j, 'masterField'));
        self::assertSame('id', $this->getProtected($j, 'foreignField'));

        $j = $m->join('contact2', ['reverse' => true]);
        self::assertTrue($j->reverse);
        self::assertSame('id', $this->getProtected($j, 'masterField'));
        self::assertSame('user_id', $this->getProtected($j, 'foreignField'));
    }
}
