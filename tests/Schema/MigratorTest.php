<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Field\PasswordField;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Schema\Migrator;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Identifier;

class MigratorTest extends TestCase
{
    protected function createDemoMigrator(string $table): Migrator
    {
        return $this->createMigrator()
            ->table($table)
            ->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'atk4_money']);
    }

    protected function isTableExist(string $table): bool
    {
        foreach ($this->createSchemaManager()->listTableNames() as $v) {
            $vUnquoted = (new Identifier($v))->getName();
            if ($vUnquoted === $table) {
                return true;
            }
        }

        return false;
    }

    public function testCreate(): void
    {
        $this->createDemoMigrator('user')->create();
        $this->assertTrue($this->isTableExist('user'));

        $this->db->dsql()
            ->mode('insert')
            ->table('user')
            ->setMulti([
                'id' => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
            ])->executeStatement();
    }

    public function testCreateTwiceException(): void
    {
        $this->createDemoMigrator('user')->create();
        $this->assertTrue($this->isTableExist('user'));

        $this->expectException(TableExistsException::class);
        $this->createDemoMigrator('user')->create();
    }

    public function testDrop(): void
    {
        $this->createDemoMigrator('user')->create();
        $this->assertTrue($this->isTableExist('user'));
        $this->createMigrator()->table('user')->drop();
        $this->assertFalse($this->isTableExist('user'));
    }

    public function testDropException(): void
    {
        $this->expectException(TableNotFoundException::class);
        $this->createMigrator()->table('user')->drop();
    }

    public function testDropIfExists(): void
    {
        $this->createMigrator()->table('user')->dropIfExists();

        $this->createDemoMigrator('user')->create();
        $this->assertTrue($this->isTableExist('user'));
        $this->createMigrator()->table('user')->dropIfExists();
        $this->assertFalse($this->isTableExist('user'));

        $this->createMigrator()->table('user')->dropIfExists();
    }

    /**
     * @dataProvider providerCharacterTypeFieldCaseSensitivityData
     */
    public function testCharacterTypeFieldCaseSensitivity(string $type, bool $isBinary): void
    {
        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('v', ['type' => $type]);

        $this->createMigrator($model)->create();

        $model->import([['v' => 'mixedcase'], ['v' => 'MIXEDCASE'], ['v' => 'MixedCase']]);

        $model->addCondition('v', 'MixedCase');
        $model->setOrder($this->getDatabasePlatform() instanceof OraclePlatform && in_array($type, ['text', 'blob'], true) ? 'id' : 'v');

        $this->assertSameExportUnordered(
            $isBinary ? [['id' => 3]] : [['id' => 1], ['id' => 2], ['id' => 3]],
            $model->export(['id'])
        );
    }

    public function providerCharacterTypeFieldCaseSensitivityData(): array
    {
        return [
            ['string', false],
            ['binary', true],
            ['text', false],
            ['blob', true],
        ];
    }

    private function makePseudoRandomString(bool $isBinary, int $length): string
    {
        $baseChars = [];
        if ($isBinary) {
            for ($i = 0; $i <= 0xFF; ++$i) {
                $baseChars[crc32($length . '_' . $i)] = chr($i);
            }
        } else {
            for ($i = 0; $i <= 0x10FFFF; $i = $i * 1.001 + 1) {
                $iInt = (int) $i;
                if ($iInt < 0xD800 || $iInt > 0xDFFF) {
                    $baseChars[crc32($length . '_' . $iInt)] = mb_chr($iInt);
                }
            }
        }
        ksort($baseChars);

        $res = str_repeat(implode('', $baseChars), intdiv($length, count($baseChars)) + 1);
        if ($isBinary) {
            return substr($res, 0, $length);
        }

        return mb_substr($res, 0, $length);
    }

    /**
     * @dataProvider providerCharacterTypeFieldLongData
     */
    public function testCharacterTypeFieldLong(string $type, bool $isBinary, int $length): void
    {
        if ($length === 0) {
            $str = '';

            // TODO Oracle converts empty string to NULL
            // https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle
            if ($this->getDatabasePlatform() instanceof OraclePlatform && in_array($type, ['string', 'text'], true)) {
                $str = 'x';
            }
        } else {
            $str = $this->makePseudoRandomString($isBinary, $length - 1);
            if (!$isBinary) {
                $str = preg_replace('~[\x00-\x1f]~', '-', $str);
            }
            $this->assertSame($length - 1, $isBinary ? strlen($str) : mb_strlen($str));
        }

        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('v', ['type' => $type]);

        $this->createMigrator($model)->create();

        $model->import([['v' => $str . (
            // MSSQL database ignores trailing \0 characters even with binary comparison
            // https://dba.stackexchange.com/questions/48660/comparing-binary-0x-and-0x00-turns-out-to-be-equal-on-sql-server
            $isBinary ? ($this->getDatabasePlatform() instanceof SQLServerPlatform ? ' ' : "\0") : '.'
        )]]);
        $model->import([['v' => $str]]);

        $model->addCondition('v', $str);
        $rows = $model->export();
        $this->assertCount(1, $rows);
        $row = reset($rows);
        unset($rows);
        $this->assertSame(['id', 'v'], array_keys($row));
        $this->assertSame(2, $row['id']);
        $this->assertSame(strlen($str), strlen($row['v']));
        $this->assertTrue($str === $row['v']);

        // remove once https://github.com/php/php-src/issues/8928 is fixed
        if (str_starts_with($_ENV['DB_DSN'], 'oci8') && $length > 1000) {
            return;
        }

        // functional test for Expression::escapeStringLiteral() method
        $strRaw = $model->getPersistence()->typecastSaveField($model->getField('v'), $str);
        $strRawSql = \Closure::bind(function () use ($model, $strRaw) {
            return $model->expr('')->escapeStringLiteral($strRaw);
        }, null, Expression::class)();
        $query = $this->db->getConnection()->dsql()
            ->field($model->expr($strRawSql));
        $resRaw = $query->getOne();
        if ($this->getDatabasePlatform() instanceof OraclePlatform && $isBinary) {
            $this->assertNotSame(strlen($str), strlen($resRaw));
        } else {
            $this->assertSame(strlen($str), strlen($resRaw));
            $this->assertTrue($str === $resRaw);
        }
        $res = $model->getPersistence()->typecastLoadField($model->getField('v'), $resRaw);
        $this->assertSame(strlen($str), strlen($res));
        $this->assertTrue($str === $res);

        if (!$isBinary) {
            $str = $this->makePseudoRandomString($isBinary, $length);

            // PostgreSQL does not support \0 character
            // https://stackoverflow.com/questions/1347646/postgres-error-on-insert-error-invalid-byte-sequence-for-encoding-utf8-0x0
            if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $str = str_replace("\0", '-', $str);
            }

            $this->assertSame($length, mb_strlen($str));
            $strSql = \Closure::bind(function () use ($model, $str) {
                return $model->expr('')->escapeStringLiteral($str);
            }, null, Expression::class)();
            $query = $this->db->getConnection()->dsql()
                ->field($model->expr($strSql));
            $res = $query->getOne();
            if ($this->getDatabasePlatform() instanceof OraclePlatform && $length === 0) {
                $this->assertNull($res);
            } else {
                $this->assertSame(strlen($str), strlen($res));
                $this->assertTrue($str === $res);
            }
        }
    }

    public function providerCharacterTypeFieldLongData(): array
    {
        return [
            ['string', false, 0],
            ['binary', true, 0],
            ['text', false, 0],
            ['blob', true, 0],
            ['string', false, 255],
            ['binary', true, 255],
            ['text', false, 255],
            ['blob', true, 255],
            // expected to fail with pdo_oci driver, multibyte Oracle CLOB stream read support
            // is broken with long strings, oci8 driver is NOT affected,
            // CI images ghcr.io/mvorisek/image-php are patched
            // remove comment once https://github.com/php/php-src/pull/8018 is merged & released
            ['text', false, str_starts_with($_ENV['DB_DSN'], 'pdo_oci') && !isset($_ENV['CI']) ? 16 * 1024 : 256 * 1024],
            ['blob', true, 256 * 1024],
        ];
    }

    public function testSetModelCreate(): void
    {
        $user = new TestUser($this->db);
        $this->createMigrator($user)->create();

        $user->createEntity()
            ->save(['name' => 'john', 'is_admin' => true, 'notes' => 'some long notes']);
        $this->assertSame([
            ['id' => 1, 'name' => 'john', 'password' => null, 'is_admin' => true, 'notes' => 'some long notes', 'main_role_id' => null],
        ], $user->export());
    }
}

class TestUser extends Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('password', [PasswordField::class]);
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->hasOne('role_id', ['model' => [TestRole::class], 'our_field' => 'main_role_id', 'their_field' => 'id']);
    }
}

class TestRole extends Model
{
    public $table = 'role';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->hasMany('Users', ['model' => [TestUser::class], 'our_field' => 'id', 'their_field' => 'main_role_id']);
    }
}
