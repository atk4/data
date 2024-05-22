<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Field\PasswordField;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Schema\Migrator;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;

class MigratorTest extends TestCase
{
    protected function createDemoMigrator(string $table): Migrator
    {
        return $this->createMigrator()
            ->table($table)
            ->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer', 'nullable' => false])
            ->field('ismall', ['type' => 'smallint'])
            ->field('ibig', ['type' => 'bigint'])
            ->field('baz', ['type' => 'text'])
            ->field('bin1', ['type' => 'binary'])
            ->field('bin2', ['type' => 'blob'])
            ->field('bl', ['type' => 'boolean'])
            ->field('tm', ['type' => 'time'])
            ->field('dt', ['type' => 'date'])
            ->field('dttm', ['type' => 'datetime'])
            ->field('fl', ['type' => 'float'])
            ->field('mn', ['type' => 'atk4_money'])
            ->field('json', ['type' => 'json'])
            ->field('lobj', ['type' => 'atk4_local_object']);
    }

    public function testCreate(): void
    {
        $this->createDemoMigrator('user')->create();
        self::assertTrue($this->createMigrator()->isTableExists('user'));

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
        self::assertTrue($this->createMigrator()->isTableExists('user'));

        $this->expectException(TableExistsException::class);
        $this->createDemoMigrator('user')->create();
    }

    public function testDrop(): void
    {
        $this->createDemoMigrator('user')->create();
        self::assertTrue($this->createMigrator()->isTableExists('user'));
        $this->createMigrator()->table('user')->drop();
        self::assertFalse($this->createMigrator()->isTableExists('user'));
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
        self::assertTrue($this->createMigrator()->isTableExists('user'));
        $this->createMigrator()->table('user')->dropIfExists();
        self::assertFalse($this->createMigrator()->isTableExists('user'));

        $this->createMigrator()->table('user')->dropIfExists();
    }

    /**
     * @dataProvider provideCharacterTypeFieldCaseSensitivityCases
     */
    #[DataProvider('provideCharacterTypeFieldCaseSensitivityCases')]
    public function testCharacterTypeFieldCaseSensitivity(string $type, bool $isBinary): void
    {
        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('v', ['type' => $type]);

        $this->createMigrator($model)->create();

        $model->import([
            ['v' => 'mixedcaseß'],
            ['v' => 'MIXEDCASEß'],
            ['v' => 'MixedCaseß'],
        ]);

        if (!$this->getDatabasePlatform() instanceof OraclePlatform || !in_array($type, ['text', 'blob'], true)) {
            $model->setOrder('v');
        }

        $expectedExport = $isBinary
            ? [['id' => 3]]
            : [['id' => 1], ['id' => 2], ['id' => 3]];

        $model->addCondition('v', 'MixedCaseß');
        self::assertSameExportUnordered($expectedExport, $model->export(['id']));

        $model->scope()->clear();
        $model->addCondition('v', '=', (clone $model)->addCondition('id', 3)->action('field', ['v']));
        self::assertSameExportUnordered($expectedExport, $model->export(['id']));

        // TODO
        if (!$this->getDatabasePlatform() instanceof OraclePlatform || !in_array($type, ['text', 'blob'], true)) {
            $model->scope()->clear();
            $model->addCondition('v', 'in', ['MixedCaseß', 'foo']);
            self::assertSameExportUnordered($expectedExport, $model->export(['id']));

            $model->scope()->clear();
            $model->addCondition('v', 'in', (clone $model)->addCondition('id', 3)->action('field', ['v']));
            self::assertSameExportUnordered($expectedExport, $model->export(['id']));
        }
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideCharacterTypeFieldCaseSensitivityCases(): iterable
    {
        yield ['string', false];
        yield ['binary', true];
        yield ['text', false];
        yield ['blob', true];
    }

    protected function makePseudoRandomString(bool $isBinary, int $length): string
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
     * @dataProvider provideCharacterTypeFieldLongCases
     */
    #[DataProvider('provideCharacterTypeFieldLongCases')]
    public function testCharacterTypeFieldLong(string $type, bool $isBinary, int $length): void
    {
        if ($length > 1000) {
            $this->debug = false;
        }

        if ($length === 0) {
            $str = '';

            // TODO Oracle always converts empty string to null
            // https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
            if ($this->getDatabasePlatform() instanceof OraclePlatform && in_array($type, ['string', 'text'], true)) {
                $str = 'x';
            }
        } else {
            $str = $this->makePseudoRandomString($isBinary, $length - 1);
            if (!$isBinary) {
                $str = preg_replace('~[\x00-\x1f]~', '-', $str);
            }
            self::assertSame($length - 1, $isBinary ? strlen($str) : mb_strlen($str));
        }

        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('v', ['type' => $type]);

        $this->createMigrator($model)->create();

        $model->import([
            ['v' => $str . (
                // MSSQL database ignores trailing \0 characters even with binary comparison
                // https://dba.stackexchange.com/questions/48660/comparing-binary-0x-and-0x00-turns-out-to-be-equal-on-sql-server
                $isBinary ? ($this->getDatabasePlatform() instanceof SQLServerPlatform ? ' ' : "\0") : '.'
            )],
            ['v' => $str],
        ]);

        $model->addCondition('v', $str);
        $rows = $model->export();
        self::assertCount(1, $rows);
        $row = reset($rows);
        unset($rows);
        self::assertSame(['id', 'v'], array_keys($row));
        self::assertSame(2, $row['id']);
        self::assertSame(strlen($str), strlen($row['v']));
        self::assertTrue($str === $row['v']);

        // remove once https://github.com/php/php-src/issues/8928 is fixed
        if (str_starts_with($_ENV['DB_DSN'], 'oci8') && $length > 1000) {
            return;
        }

        // functional test for Expression::escapeStringLiteral() method
        $strRaw = $model->getPersistence()->typecastSaveField($model->getField('v'), $str);
        $strRawSql = \Closure::bind(static fn () => $model->expr('')->escapeStringLiteral($strRaw), null, Expression::class)();
        $query = $this->getConnection()->dsql()
            ->field($model->expr($strRawSql));
        $resRaw = $query->getOne();
        if ($this->getDatabasePlatform() instanceof OraclePlatform && $isBinary) {
            self::assertNotSame(strlen($str), strlen($resRaw));
        } else {
            self::assertSame(strlen($str), strlen($resRaw));
            self::assertTrue($str === $resRaw);
        }
        $res = $model->getPersistence()->typecastLoadField($model->getField('v'), $resRaw);
        self::assertSame(strlen($str), strlen($res));
        self::assertTrue($str === $res);

        if (!$isBinary) {
            $str = $this->makePseudoRandomString($isBinary, $length);

            // PostgreSQL does not support \0 character
            // https://stackoverflow.com/questions/1347646/postgres-error-on-insert-error-invalid-byte-sequence-for-encoding-utf8-0x0
            if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $str = str_replace("\0", '-', $str);
            }

            self::assertSame($length, mb_strlen($str));
            $strSql = \Closure::bind(static fn () => $model->expr('')->escapeStringLiteral($str), null, Expression::class)();
            $query = $this->getConnection()->dsql()
                ->field($model->expr($strSql));
            $res = $query->getOne();
            if ($this->getDatabasePlatform() instanceof OraclePlatform && $length === 0) {
                self::assertNull($res);
            } else {
                self::assertSame(strlen($str), strlen($res));
                self::assertTrue($str === $res);
            }
        }
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideCharacterTypeFieldLongCases(): iterable
    {
        yield ['string', false, 0];
        yield ['binary', true, 0];
        yield ['text', false, 0];
        yield ['blob', true, 0];
        yield ['string', false, 255];
        yield ['binary', true, 255];
        yield ['text', false, 255];
        yield ['blob', true, 255];
        yield ['text', false, 256 * 1024];
        yield ['blob', true, 256 * 1024];
    }

    public function testSetModelCreate(): void
    {
        $user = new TestUser($this->db);
        $this->createMigrator($user)->create();

        $user->createEntity()
            ->save(['name' => 'john', 'is_admin' => true, 'notes' => 'some long notes']);
        self::assertSame([
            ['id' => 1, 'name' => 'john', 'password' => null, 'is_admin' => true, 'notes' => 'some long notes', 'main_role_id' => null],
        ], $user->export());
    }

    public function testIntrospectTableToModelBasic(): void
    {
        $creatingMigrator = $this->createDemoMigrator('user')->create();
        $introspectedModel = $this->createMigrator()->introspectTableToModel('user');

        $expectedFields = [];
        foreach ($creatingMigrator->table->getColumns() as $column) {
            $expectedFields[$column->getName()] = [
                'type' => Type::getTypeRegistry()->lookupName($column->getType()), // TODO simplify once https://github.com/doctrine/dbal/pull/6130 is merged
                'nullable' => !$column->getNotnull(),
            ];
        }

        $introspectedFields = [];
        foreach ($introspectedModel->getFields() as $field) {
            $introspectedFields[$field->shortName] = [
                'type' => $field->type,
                'nullable' => $field->nullable && !$field->required,
            ];
        }

        // TODO fix DBAL column comment type hint
        // see PlatformFixColumnCommentTypeHintTrait trait used for MSSQL and Oracle platforms
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            $expectedFields['bin1']['type'] = 'blob'; // should be "binary"
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $expectedFields['bin1']['type'] = 'blob'; // should be "binary"
        }

        self::assertSame($expectedFields, $introspectedFields);
    }

    public function testIntrospectTableToModelPrimaryKeyNonFirstColumn(): void
    {
        $this->createMigrator()
            ->table('t')
            ->field('a')
            ->id()
            ->field('b')
            ->create();

        $model = $this->createMigrator()->introspectTableToModel('t');

        self::assertSame(['a', 'id', 'b'], array_keys($model->getFields()));
        self::assertSame('id', $model->idField);
    }

    public function testIntrospectTableToModelNoPrimaryKeyException(): void
    {
        $this->createMigrator()
            ->table('t')
            ->field('a')
            ->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Table must contain exactly one primary key');
        $this->createMigrator()->introspectTableToModel('t');
    }
}

class TestUser extends Model
{
    public $table = 'user';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('password', [PasswordField::class]);
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->hasOne('role_id', ['model' => [TestRole::class], 'ourField' => 'main_role_id', 'theirField' => 'id']);
    }
}

class TestRole extends Model
{
    public $table = 'role';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->hasMany('Users', ['model' => [TestUser::class], 'ourField' => 'id', 'theirField' => 'main_role_id']);
    }
}
