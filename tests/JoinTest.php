<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Array_ as ArrayPersistence;

class JoinTest extends TestCase
{
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
}
