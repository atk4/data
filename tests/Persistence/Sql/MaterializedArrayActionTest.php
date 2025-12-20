<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Data\Persistence\Array_\Action as ArrayAction;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\MaterializedArrayAction;
use Atk4\Data\Persistence\Sql\Mysql\Connection as MysqlConnection;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class MaterializedArrayActionTest extends TestCase
{
    /**
     * @return array{string, array<string, mixed>}
     */
    private function renderQuery(Expressionable $query): array
    {
        $render = $query->getDsqlExpression($this->getConnection()->expr())->render();

        self::assertSame([
            '(' . $render[0] . ')',
            $render[1],
        ], $this->getConnection()->expr('[]', [$query])->render());

        return $render;
    }

    private function isServerMysql5x(): bool
    {
        return $this->getDatabasePlatform() instanceof AbstractMySQLPlatform
            && !MysqlConnection::isServerMariaDb($this->getConnection())
            && version_compare($this->getConnection()->getServerVersion(), '6.0') < 0;
    }

    private function isServerMariadb105OrLower(): bool
    {
        return $this->getDatabasePlatform() instanceof AbstractMySQLPlatform
            && MysqlConnection::isServerMariaDb($this->getConnection())
            && version_compare($this->getConnection()->getServerVersion(), '10.6') < 0;
    }

    private function isServerPostgreSQL16OrLower(): bool
    {
        return $this->getDatabasePlatform() instanceof PostgreSQLPlatform
            && version_compare($this->getConnection()->getServerVersion(), '17.0') < 0;
    }

    public function testRenderZeroRows(): void
    {
        $action = new ArrayAction([], ['bool', 'int', 'float', 'string']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame([':a' => '[]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && !$this->isServerMysql5x() && !$this->isServerMariadb105OrLower()) {
            self::assertSame([':a' => '[]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSame($this->isServerPostgreSQL16OrLower() ? [':a' => '<t></t>'] : [':a' => '[]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSame([':a' => '[]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame([':xxaaaa' => '[]'], $render[1]);
        } else {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` limit 0, 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null, ':c' => null, ':d' => null], $render[1]);
        }

        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }

    public function testRenderOneRow(): void
    {
        $action = new ArrayAction([
            ['bool' => false, 'int' => 0, 'float' => 0.0, 'string' => 'Mark'],
        ], ['bool', 'int', 'float', 'string']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame([':a' => '[[false,0,0.0,"Mark"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && !$this->isServerMysql5x() && !$this->isServerMariadb105OrLower()) {
            self::assertSame([':a' => '[[false,0,0.0,"Mark"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSame($this->isServerPostgreSQL16OrLower() ? [':a' => '<t><r c0="0" c1="0" c2="0.0" c3="Mark"/></t>'] : [':a' => '[[false,0,0.0,"Mark"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSame([':a' => '[[false,0,0.0,"Mark"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame([':xxaaaa' => '[[false,0,0.0,"Mark"]]'], $render[1]);
        } else {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string`', $render[0]);
            self::assertSame([':a' => false, ':b' => 0, ':c' => 0.0, ':d' => 'Mark'], $render[1]);
        }

        self::{'assertEquals'}([
            ['bool' => '0', 'int' => 0, 'float' => 0.0, 'string' => 'Mark'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }

    public function testRenderMultipleRows(): void
    {
        $action = new ArrayAction([
            ['bool' => true, 'int' => \PHP_INT_MIN, 'float' => -1e-20, 'string' => ''],
            ['bool' => null, 'int' => \PHP_INT_MAX, 'float' => 1.0123456789123e50, 'string' => ' <foo>&"\'🔥' . "\n"],
        ], ['bool', 'int', 'float', 'string']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('select case when json_type(`value`, \'$[0]\') not in(\'array\', \'object\') then json_extract(`value`, \'$[0]\') end `bool`, case when json_type(`value`, \'$[1]\') not in(\'array\', \'object\') then json_extract(`value`, \'$[1]\') end `int`, case when json_type(`value`, \'$[2]\') not in(\'array\', \'object\') then json_extract(`value`, \'$[2]\') end `float`, case when json_type(`value`, \'$[3]\') not in(\'array\', \'object\') then json_extract(`value`, \'$[3]\') end `string` from json_each(:a, \'$\') where `key` is not null', $render[0]);
            self::assertSame([':a' => '[[true,-9223372036854775808,-1.0e-20,""],[null,9223372036854775807,1.0123456789123e+50," <foo>&\"\'🔥\n"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && !$this->isServerMysql5x() && !$this->isServerMariadb105OrLower()) {
            self::assertSameSql('select `c0` `bool`, `c1` `int`, `c2` `float`, `c3` `string` from json_table(:a, \'$[*]\' columns (`c0` TINYINT' . (str_contains($render[0], 'TINYINT(1)') ? '(1)' : '') . ' path \'$[0]\', `c1` BIGINT path \'$[1]\', `c2` DOUBLE PRECISION path \'$[2]\', `c3` VARCHAR(255) COLLATE `utf8mb4_unicode_ci` path \'$[3]\')) `t`', $render[0]);
            self::assertSame([':a' => '[[true,-9223372036854775808,-1.0e-20,""],[null,9223372036854775807,1.0123456789123e+50," <foo>&\"\'🔥\n"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if ($this->isServerPostgreSQL16OrLower()) {
                self::assertSameSql('select `c0` `bool`, `c1` `int`, `c2` `float`, `c3` `string` from xmltable(\'/t/r\' passing xmlparse(document :a) columns `c0` BOOLEAN path \'@c0\', `c1` BIGINT path \'@c1\', `c2` DOUBLE PRECISION path \'@c2\', `c3` ATK4__CIVARCHAR path \'@c3\') `t`', $render[0]);
                self::assertSame([':a' => '<t><r c0="1" c1="-9223372036854775808" c2="-1.0E-20" c3=""/><r c1="9223372036854775807" c2="1.0123456789123E+50" c3=" &#x3c;foo>&#x26;&#x22;\'🔥&#xa;"/></t>'], $render[1]);
            } else {
                self::assertSameSql('select `c0` `bool`, `c1` `int`, `c2` `float`, `c3` `string` from json_table(:a, \'strict $[*]\' columns (`c0` BOOLEAN path \'strict $[0]\', `c1` BIGINT path \'strict $[1]\', `c2` DOUBLE PRECISION path \'strict $[2]\', `c3` ATK4__CIVARCHAR path \'strict $[3]\')) `t`', $render[0]);
                self::assertSame([':a' => '[[true,-9223372036854775808,-1.0e-20,""],[null,9223372036854775807,1.0123456789123e+50," <foo>&\"\'🔥\n"]]'], $render[1]);
            }
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select `c0` `bool`, `c1` `int`, `c2` `float`, `c3` `string` from openjson(concat(\'[\', :a, \']\'), \'$[0]\') with (`c0` BIT \'$[0]\', `c1` BIGINT \'$[1]\', `c2` DOUBLE PRECISION \'$[2]\', `c3` NVARCHAR(1020) \'$[3]\') `t`', $render[0]);
            self::assertSame([':a' => '[[true,-9223372036854775808,-1.0e-20,""],[null,9223372036854775807,1.0123456789123e+50," <foo>&\"\'🔥\n"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select `c0` `bool`, `c1` `int`, `c2` `float`, `c3` `string` from json_table(:a, \'$[*]\' columns (`c0` NUMBER(1) path \'$[0]\'' . (version_compare($this->getConnection()->getServerVersion(), '21.0') >= 0 ? ' ALLOW BOOLEAN TO NUMBER' : '') . ', `c1` NUMBER(20) path \'$[1]\', `c2` NUMBER path \'$[2]\', `c3` VARCHAR2 path \'$[3]\')) `t`', $render[0]);
            self::assertSame([':xxaaaa' => '[[true,-9223372036854775808,-1.0e-20,""],[null,9223372036854775807,1.0123456789123e+50," <foo>&\"\'🔥\n"]]'], $render[1]);
        } else {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` union all select :e, :f, :g, :h', $render[0]);
            self::assertSame([':a' => true, ':b' => \PHP_INT_MIN, ':c' => -1e-20, ':d' => '', ':e' => null, ':f' => \PHP_INT_MAX, ':g' => 1.0123456789123e50, ':h' => ' <foo>&"\'🔥' . "\n"], $render[1]);
        }

        self::{'assertEquals'}([
            ['bool' => '1', 'int' => \PHP_INT_MIN, 'float' => -1e-20, 'string' => ''],
            ['bool' => null, 'int' => \PHP_INT_MAX, 'float' => 1.0123456789123e50, 'string' => $this->fixExpectedJsonValueUnquoteForMariadb106To115(' <foo>&"\'🔥' . "\n")],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }

    public function testColumnTypeMismatchException(): void
    {
        $action = new ArrayAction([['foo' => 1], ['foo' => '1']], ['foo']);
        $query = new MaterializedArrayAction($action);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column consists of more than one type');
        $this->renderQuery($query);
    }

    public function testGetRowsUpdatedGenerator(): void
    {
        $action = new ArrayAction([], ['foo', 'bar']);
        $query = new MaterializedArrayAction($action);

        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        $action->generator = new \ArrayIterator([['foo' => 1, 'bar' => 'u']]);
        self::assertSame([
            ['foo' => '1', 'bar' => 'u'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        $action->generator = new \ArrayIterator([['foo' => 1, 'bar' => 'u'], ['foo' => null, 'bar' => 'v']]);
        self::assertSame([
            ['foo' => '1', 'bar' => 'u'],
            ['foo' => null, 'bar' => 'v'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        $action->generator = new \ArrayIterator([]);
        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }
}
