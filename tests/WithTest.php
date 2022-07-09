<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;

class WithTest extends TestCase
{
    public function testWith(): void
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John', 'salary' => 2500],
                20 => ['id' => 20, 'name' => 'Peter', 'salary' => 4000],
            ],
            'invoice' => [
                1 => ['id' => 1, 'net' => 500, 'user_id' => 10],
                2 => ['id' => 2, 'net' => 200, 'user_id' => 20],
                3 => ['id' => 3, 'net' => 100, 'user_id' => 20],
                4 => ['id' => 4, 'net' => 400, 'user_id' => 20],
            ],
        ]);

        // setup models
        $m_user = new Model($this->db, ['table' => 'user']);
        $m_user->addField('name');
        $m_user->addField('salary', ['type' => 'integer']);

        $m_invoice = new Model($this->db, ['table' => 'invoice']);
        $m_invoice->addField('net', ['type' => 'integer']);
        $m_invoice->hasOne('user_id', ['model' => $m_user]);
        $m_invoice->addCondition('net', '>', 100);

        // setup test model
        $m = clone $m_user;
        $m->addWith('i', $m_invoice); // add cursor
        $j_invoice = $m->join('i.user_id'); // join cursor
        $j_invoice->addField('invoiced', ['type' => 'integer', 'actual' => 'net']); // add field from joined cursor

        // tests
        $this->assertSameSql(
            'with "i" as (select "id", "net", "user_id" from "invoice" where "net" > :a)' . "\n"
                . 'select "user"."id", "user"."name", "user"."salary", "_i"."net" "invoiced" from "user" inner join "i" "_i" on "_i"."user_id" = "user"."id"',
            $m->action('select')->render()[0]
        );

        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $serverVersion = $this->db->getConnection()->getConnection()->getWrappedConnection()->getServerVersion();
            if (preg_match('~^5\.(?!5\.5-.+?-MariaDB)~', $serverVersion)) {
                $this->markTestIncomplete('MySQL Server 5.x does not support WITH clause');
            }
        }

        $this->assertSameExportUnordered([
            ['id' => 10, 'name' => 'John', 'salary' => 2500, 'invoiced' => 500],
            ['id' => 20, 'name' => 'Peter', 'salary' => 4000, 'invoiced' => 200],
            ['id' => 20, 'name' => 'Peter', 'salary' => 4000, 'invoiced' => 400],
        ], $m->export());
    }

    public function testUniqueNameException1(): void
    {
        $m1 = new Model(null, ['table' => 't']);
        $m2 = new Model();

        $this->expectException(Exception::class);
        $m1->addWith('t', $m2);
    }

    public function testUniqueNameException2(): void
    {
        $m1 = new Model();
        $m2 = new Model();
        $m1->addWith('t', $m2);

        $this->expectException(Exception::class);
        $m1->addWith('t', $m2);
    }
}
