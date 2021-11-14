<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;

class ModelTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testSetModelCreate(): void
    {
        $this->dropTableIfExists('user');
        $user = new TestUser($this->db);

        $this->createMigrator($user)->create();

        // now we can use user
        $user->createEntity()->save(['name' => 'john', 'is_admin' => true, 'notes' => 'some long notes']);
    }

    public function testImportTable(): void
    {
        $this->assertTrue(true);

        return; // TODO enable once import to Model is supported using DBAL
        // @phpstan-ignore-next-line
        $this->dropTableIfExists('user');

        $migrator = $this->createMigrator();

        $migrator->table('user')->id()
            ->field('foo')
            ->field('str', ['type' => 'string'])
            ->field('bool', ['type' => 'boolean'])
            ->field('int', ['type' => 'integer'])
            ->field('mon', ['type' => 'atk4_money'])
            ->field('flt', ['type' => 'float'])
            ->field('date', ['type' => 'date'])
            ->field('datetime', ['type' => 'datetime'])
            ->field('time', ['type' => 'time'])
            ->field('txt', ['type' => 'text'])
            ->field('arr', ['type' => 'array'])
            ->field('json', ['type' => 'json'])
            ->field('obj', ['type' => 'object'])
            ->create();

        $this->db->dsql()->table('user')
            ->set([
                'id' => 1,
                'foo' => 'quite short value, max 255 characters',
                'str' => 'quite short value, max 255 characters',
                'bool' => true,
                'int' => 123,
                'mon' => 123.45,
                'flt' => 123.456789,
                'date' => (new \DateTime())->format('Y-m-d'),
                'datetime' => (new \DateTime())->format('Y-m-d H:i:s'),
                'time' => (new \DateTime())->format('H:i:s'),
                'txt' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'arr' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'json' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
                'obj' => 'very long text value' . str_repeat('-=#', 1000), // 3000+ chars
            ])->insert();

        $migrator2 = $this->createMigrator();
        $migrator2->importTable('user');

        $migrator2->mode('create');

        $q1 = preg_replace('/\([0-9,]*\)/i', '', $migrator->render()); // remove parenthesis otherwise we can't differ money from float etc.
        $q2 = preg_replace('/\([0-9,]*\)/i', '', $migrator2->render());
        $this->assertSame($q1, $q2);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testMigrateTable(): void
    {
        $this->dropTableIfExists('user');
        $migrator = $this->createMigrator();
        $migrator->table('user')->id()
            ->field('foo')
            ->field('bar', ['type' => 'integer'])
            ->field('baz', ['type' => 'text'])
            ->create();
        $this->db->dsql()->table('user')
            ->setMulti([
                'id' => 1,
                'foo' => 'foovalue',
                'bar' => 123,
                'baz' => 'long text value',
            ])->insert();
    }

    public function testCreateModel(): void
    {
        $this->assertTrue(true);

        return; // TODO enable once create from Model is supported using DBAL
        // @phpstan-ignore-next-line
        $this->dropTableIfExists('user');

        $this->createMigrator(new TestUser($this->db))->create();

        $user_model = $this->createMigrator()->createModel($this->db, 'user');

        $this->assertSame(
            [
                'name',
                'password',
                'is_admin',
                'notes',
                'main_role_id', // our_field here not role_id (reference name)
            ],
            array_keys($user_model->getFields())
        );
    }

    /**
     * @dataProvider providerCharacterTypeFieldCaseSensitivityData
     */
    public function testCharacterTypeFieldCaseSensitivity(string $type, bool $isBinary): void
    {
        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('v', ['type' => $type]);

        $this->createMigrator($model)->dropIfExists()->create();

        $model->import([['v' => 'mixedcase'], ['v' => 'MIXEDCASE'], ['v' => 'MixedCase']]);

        $model->addCondition('v', 'MixedCase');
        $model->setOrder('v');

        $this->assertSame($isBinary ? [['id' => 3]] : [['id' => 1], ['id' => 2], ['id' => 3]], $model->export(['id']));
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

    private function makePseudoRandomString(bool $isBinary, int $lengthBytes): string
    {
        $baseChars = [];
        if ($isBinary) {
            for ($i = 0; $i <= 0xFF; ++$i) {
                $baseChars[crc32($lengthBytes . '_' . $i)] = chr($i);
            }
        } else {
            for ($i = 0; $i <= 0x10FFFF; $i = $i * 1.001 + 1) {
                $iInt = (int) $i;
                if ($iInt < 0xD800 || $iInt > 0xDFFF) {
                    $baseChars[crc32($lengthBytes . '_' . $i)] = mb_chr($iInt);
                }
            }
        }
        ksort($baseChars);

        $res = str_repeat(implode('', $baseChars), intdiv($lengthBytes, count($baseChars)) + 1);
        if ($isBinary) {
            return substr($res, 0, $lengthBytes);
        }

        $res = mb_strcut($res, 0, $lengthBytes);
        $padLength = $lengthBytes - strlen($res);
        foreach ($baseChars as $ch) {
            if (strlen($ch) === $padLength) {
                $res .= $ch;

                break;
            }
        }

        return $res;
    }

    /**
     * @dataProvider providerCharacterTypeFieldLongData
     */
    public function testCharacterTypeFieldLong(string $type, bool $isBinary, int $lengthBytes): void
    {
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $lengthBytes = min($lengthBytes, 500);
        }

        $str = $this->makePseudoRandomString($isBinary, $lengthBytes);
        if (!$isBinary) {
            $str = preg_replace('~[\x00-\x1f]~', '-', $str);
        }
        $this->assertSame($lengthBytes, strlen($str));

        $model = new Model($this->db, ['table' => 'user']);
        $model->addField('v', ['type' => $type]);

        $this->createMigrator($model)->dropIfExists()->create();

        $model->import([['v' => $str . ($isBinary ? "\0" : '.')]]);
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
    }

    public function providerCharacterTypeFieldLongData(): array
    {
        return [
            ['string', false, 100],
            ['binary', true, 100],
            ['text', false, 256 * 1024],
            ['blob', true, 256 * 1024],
        ];
    }
}

class TestUser extends \Atk4\Data\Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('password');
        $this->addField('is_admin', ['type' => 'boolean']);
        $this->addField('notes', ['type' => 'text']);

        $this->hasOne('role_id', ['model' => [TestRole::class], 'our_field' => 'main_role_id', 'their_field' => 'id']);
    }
}

class TestRole extends \Atk4\Data\Model
{
    public $table = 'role';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->hasMany('Users', [TestUser::class, 'our_field' => 'id', 'their_field' => 'main_role_id']);
    }
}
