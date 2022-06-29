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
        $m->addWith($m_invoice, 'i', ['user_id', 'net' => 'invoiced']); // add cursor
        $j_invoice = $m->join('i.user_id'); // join cursor
        $j_invoice->addField('invoiced', ['type' => 'integer']); // add field from joined cursor

        // tests
        $this->assertSameSql(
            'with "i" ("user_id", "invoiced") as (select "user_id", "net" from "invoice" where "net" > :a)' . "\n"
                . 'select "user"."id", "user"."name", "user"."salary", "_i"."invoiced" from "user" inner join "i" "_i" on "_i"."user_id" = "user"."id"',
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

    public function testUniqueAliasException(): void
    {
        $m1 = new Model();
        $m2 = new Model();
        $m1->addWith($m2, 't');
        $this->expectException(Exception::class);
        $m1->addWith($m2, 't');
    }
}
