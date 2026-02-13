<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Types as DbalTypes;
use PHPUnit\Framework\ExpectationFailedException;

class TypecastingTest extends TestCase
{
    /** @var string */
    private $defaultTzBackup;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultTzBackup = date_default_timezone_get();
    }

    #[\Override]
    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTzBackup);

        parent::tearDown();
    }

    public function testType(): void
    {
        $dbData = [
            'types' => [
                '_types' => [
                    'string_l' => 'text',
                    'string_b' => 'binary',
                    'string_lb' => 'blob',
                    'date' => 'date',
                    'datetime' => 'datetime',
                    'time' => 'time',
                    'json' => 'json',
                ],
                [
                    'string' => 'foo',
                    'string_l' => str_repeat('kůň 2', 1_000),
                    'string_b' => "\xff ",
                    'string_lb' => str_repeat('kůň ' . "\xff", 1_000),
                    'string - %' => 'bar',
                    'date' => new \DateTime('2013-02-20 UTC'),
                    'datetime' => new \DateTime('2013-02-20 20:00:12 UTC'),
                    'time' => new \DateTime('1970-01-01 12:00:50 UTC'),
                    'boolean' => true,
                    'integer' => 2940,
                    'integer2' => \PHP_INT_MAX,
                    'integer3' => \PHP_INT_MIN,
                    'money' => 8.2,
                    'float' => 8.20234376757473,
                    'json' => [1, 2, 3],
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('string_l', ['type' => 'text']);
        $m->addField('string_b', ['type' => 'binary']);
        $m->addField('string_lb', ['type' => 'blob']);
        $m->addField('string - %', ['type' => 'string']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('integer2', ['type' => 'bigint']);
        $m->addField('integer3', ['type' => 'bigint']);
        $m->addField('json', ['type' => 'json']);
        $mm = $m->load(1);

        self::assertSame('foo', $mm->get('string'));
        self::assertSame($dbData['types'][0]['string_l'], $mm->get('string_l'));
        self::assertSame($dbData['types'][0]['string_b'], $mm->get('string_b'));
        self::assertSame($dbData['types'][0]['string_lb'], $mm->get('string_lb'));
        self::assertSame($dbData['types'][0]['string - %'], $mm->get('string - %'));
        self::assertTrue($mm->get('boolean'));
        self::assertSame(8.20, $mm->get('money'));
        self::{'assertEquals'}(new \DateTime('2013-02-20 UTC'), $mm->get('date'));
        self::{'assertEquals'}(new \DateTime('2013-02-20 20:00:12 UTC'), $mm->get('datetime'));
        self::{'assertEquals'}(new \DateTime('1970-01-01 12:00:50 UTC'), $mm->get('time'));
        self::assertSame(2940, $mm->get('integer'));
        self::assertSame(\PHP_INT_MAX, $mm->get('integer2'));
        self::assertSame(\PHP_INT_MIN, $mm->get('integer3'));
        self::assertSame([1, 2, 3], $mm->get('json'));
        self::assertSame(8.20234376757473, $mm->get('float'));

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();

        $dbData = [
            'types' => [
                1 => array_merge(['id' => 1], $dbData['types'][0]),
                array_merge(['id' => 2], $dbData['types'][0], ['datetime' => clone $dbData['types'][0]['datetime']]),
            ],
        ];
        $dbActual = $this->getDb();
        if (!$this->createMigrator()->canIntrospectJsonType()) {
            foreach ($dbActual['types'] as $k => $v) {
                $dbActual['types'][$k]['json'] = json_decode($v['json'], true);
            }
        }
        if (!Connection::isDbal3x() && $this->getDatabasePlatform() instanceof OraclePlatform) {
            foreach ($dbActual['types'] as $k => $v) {
                $dbActual['types'][$k]['time'] = new \DateTime('1970-1-1 ' . $v['time'] . ' UTC');

                // TODO fix Oracle binary/blob type introspection
                $dbActual['types'][$k]['string_b'] = $dbData['types'][1]['string_b'];
                $dbActual['types'][$k]['string_lb'] = $dbData['types'][1]['string_lb'];
            }
        }
        self::assertSameExportUnordered($dbData, $dbActual);

        [$first, $duplicate] = $m->export();

        unset($first['id']);
        unset($duplicate['id']);

        self::assertSameExportUnordered([$first], [$duplicate]);

        $m->load(2)->set('float', 8.20234376757474)->save();
        self::assertSame(8.20234376757474, $m->load(2)->get('float'));
        $m->load(2)->set('float', 8.202343767574732)->save();
        // pdo_sqlite in truncating float, see https://github.com/php/php-src/issues/8510
        // fixed since PHP 8.1, but if converted in SQL to string explicitly, the result is still rounded to 15 significant digits
        if (!$this->getDatabasePlatform() instanceof SQLitePlatform || \PHP_VERSION_ID >= 8_01_00) {
            self::assertSame(8.202343767574732, $m->load(2)->get('float'));
        }
    }

    public function testEmptyValues(): void
    {
        // Oracle always converts empty string to null
        // https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
        $fixEmptyStringForOracleFx = function ($v) use (&$fixEmptyStringForOracleFx) {
            if (is_array($v)) {
                return array_map($fixEmptyStringForOracleFx, $v);
            } elseif ($v === '' && $this->getDatabasePlatform() instanceof OraclePlatform) {
                return null;
            }

            return $v;
        };

        $dbData = [
            'types' => [
                1 => $row = [
                    'id' => 1,
                    'string' => '',
                    'text' => '',
                    'date' => '',
                    'datetime' => '',
                    'time' => '',
                    'boolean' => '',
                    'smallint' => '',
                    'integer' => '',
                    'bigint' => '',
                    'money' => '',
                    'float' => '',
                    'decimal' => '',
                    'json' => '',
                    'local-object' => '',
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('string', ['type' => 'string']);
        $m->addField('text', ['type' => 'text']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('boolean', ['type' => 'boolean']);
        $m->addField('smallint', ['type' => 'smallint']);
        $m->addField('integer', ['type' => 'integer']);
        $m->addField('bigint', ['type' => 'bigint']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('decimal', ['type' => 'decimal']);
        $m->addField('json', ['type' => 'json']);
        $m->addField('local-object', ['type' => 'atk4_local_object']);
        $mm = $m->load(1);

        self::assertSame($fixEmptyStringForOracleFx(''), $mm->get('string'));
        self::assertSame($fixEmptyStringForOracleFx(''), $mm->get('text'));
        self::assertNull($mm->get('date'));
        self::assertNull($mm->get('datetime'));
        self::assertNull($mm->get('time'));
        self::assertNull($mm->get('boolean'));
        self::assertNull($mm->get('smallint'));
        self::assertNull($mm->get('integer'));
        self::assertNull($mm->get('bigint'));
        self::assertNull($mm->get('money'));
        self::assertNull($mm->get('float'));
        self::assertNull($mm->get('decimal'));
        self::assertNull($mm->get('json'));
        self::assertNull($mm->get('local-object'));

        unset($row['id']);
        $row['json'] = null;
        $row['local-object'] = null;
        $mm->setMulti($row);

        self::assertSame($fixEmptyStringForOracleFx(''), $mm->get('string'));
        self::assertSame($fixEmptyStringForOracleFx(''), $mm->get('text'));
        self::assertNull($mm->get('date'));
        self::assertNull($mm->get('datetime'));
        self::assertNull($mm->get('time'));
        self::assertNull($mm->get('boolean'));
        self::assertNull($mm->get('smallint'));
        self::assertNull($mm->get('integer'));
        self::assertNull($mm->get('bigint'));
        self::assertNull($mm->get('money'));
        self::assertNull($mm->get('float'));
        self::assertNull($mm->get('decimal'));
        self::assertNull($mm->get('json'));
        self::assertNull($mm->get('local-object'));

        self::assertSame([], $mm->getDirtyRef());

        $mm->save();

        $dbData['types'][1]['id'] = '1';
        $dbData['types'] = [$dbData['types'][1]];
        self::assertSame($fixEmptyStringForOracleFx($dbData['types']), $m->export(null, null, false));

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();
        $dbData['types'][1] = [
            'id' => '2',
            'string' => '',
            'text' => '',
            'date' => null,
            'datetime' => null,
            'time' => null,
            'boolean' => null,
            'smallint' => null,
            'integer' => null,
            'bigint' => null,
            'money' => null,
            'float' => null,
            'decimal' => null,
            'json' => null,
            'local-object' => null,
        ];
        self::assertSame($fixEmptyStringForOracleFx($dbData['types']), $m->export(null, null, false));
    }

    public function testTypecastNull(): void
    {
        $dbData = [
            'test' => [
                1 => $row = ['id' => 1, 'a' => '1', 'b' => '', 'c' => null],
            ],
        ];
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'test']);
        $m->addField('a');
        $m->addField('b');
        $m->addField('c');
        $m = $m->createEntity();

        unset($row['id']);
        $m->setMulti($row);
        $m->save();

        $dbData['test'][2] = array_merge(['id' => 2], $row);

        // TODO Oracle always converts empty string to null
        // https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $dbData['test'][1]['b'] = null;
            $dbData['test'][2]['b'] = null;
        }

        self::assertSame($dbData, $this->getDb());
    }

    /**
     * @param \Closure(): void $fx
     */
    protected function executeFxWithTemporaryType(string $name, DbalTypes\Type $type, \Closure $fx): void
    {
        $typeRegistry = DbalTypes\Type::getTypeRegistry();

        $typeRegistry->register($name, $type);
        try {
            $fx();
        } finally {
            \Closure::bind(static function () use ($typeRegistry, $name) {
                unset($typeRegistry->instancesReverseIndex[spl_object_id($typeRegistry->instances[$name])]);
                unset($typeRegistry->instances[$name]);
            }, null, DbalTypes\TypeRegistry::class)();
        }
    }

    public function testSaveFieldUnexpectedScalarException(): void
    {
        $this->executeFxWithTemporaryType('bad-datetime', new class extends DbalTypes\Type {
            /**
             * @deprecated remove once DBAL 3.x support is dropped
             */
            public function getName(): string
            {
                return self::class;
            }

            #[\Override]
            public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
            {
                return '';
            }

            #[\Override]
            public function convertToDatabaseValue($value, AbstractPlatform $platform): ?\DateTime
            {
                return $value;
            }

            #[\Override]
            public function convertToPHPValue($value, AbstractPlatform $platform): ?\DateTime
            {
                return null;
            }
        }, function () {
            $this->expectException(\TypeError::class);
            $this->expectExceptionMessage('Unexpected non-scalar value');
            $this->db->typecastSaveField(new Field(['type' => 'bad-datetime']), new \DateTime());
        });
    }

    public function testLoadFieldUnexpectedScalarException(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Unexpected non-scalar value');
        $this->db->typecastLoadField(new Field(['type' => 'datetime']), new \DateTime()); // @phpstan-ignore argument.type
    }

    public function testSaveFieldConvertedWarningNotWrappedException(): void
    {
        $this->executeFxWithTemporaryType('with-warning', new class extends DbalTypes\IntegerType {
            #[\Override]
            public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
            {
                throw new \ErrorException('Converted PHP warning');
            }
        }, function () {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Converted PHP warning');
            $this->db->typecastSaveField(new Field(['type' => 'with-warning']), 1);
        });
    }

    public function testLoadFieldConvertedWarningNotWrappedException(): void
    {
        $this->executeFxWithTemporaryType('with-warning', new class extends DbalTypes\IntegerType {
            #[\Override]
            public function convertToPHPValue($value, AbstractPlatform $platform): ?int
            {
                throw new \ErrorException('Converted PHP warning');
            }
        }, function () {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Converted PHP warning');
            $this->db->typecastLoadField(new Field(['type' => 'with-warning']), 1);
        });
    }

    public function testNormalizeConvertedWarningNotWrappedException(): void
    {
        $this->executeFxWithTemporaryType('with-warning', new class extends DbalTypes\IntegerType {
            #[\Override]
            public function convertToDatabaseValue($value, AbstractPlatform $platform): ?int
            {
                throw new \ErrorException('Converted PHP warning');
            }
        }, function () {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Converted PHP warning');
            (new Field(['type' => 'with-warning']))->normalize(1);
        });
    }

    public function testTypeCustom1(): void
    {
        $dbData = [
            'types' => [
                '_types' => ['date' => 'date', 'datetime' => 'datetime', 'time' => 'time'],
                $row = [
                    'date' => new \DateTime('2013-02-20 UTC'),
                    'datetime' => new \DateTime('2013-02-20 20:00:12.235689 UTC'),
                    'time' => new \DateTime('1970-01-01  12:00:50.235689 UTC'),
                    'b1' => true,
                    'b2' => false,
                    'integer' => '2940',
                    'money' => 8.2,
                    'float' => 8.20234376757473,
                ],
            ],
        ];
        $this->setDb($dbData);

        date_default_timezone_set('Asia/Seoul');

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date']);
        $m->addField('datetime', ['type' => 'datetime']);
        $m->addField('time', ['type' => 'time']);
        $m->addField('b1', ['type' => 'boolean']);
        $m->addField('b2', ['type' => 'boolean']);
        $m->addField('money', ['type' => 'atk4_money']);
        $m->addField('float', ['type' => 'float']);
        $m->addField('integer', ['type' => 'integer']);

        $mm = $m->load(1);

        self::assertSame(1, $mm->getId());
        self::assertSame(1, $mm->get('id'));
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform // TODO create DBAL PR to create DB column with microseconds support by default https://github.com/doctrine/dbal/blob/4.0.2/src/Platforms/AbstractMySQLPlatform.php#L171
            || $this->getDatabasePlatform() instanceof PostgreSQLPlatform
            || $this->getDatabasePlatform() instanceof SQLServerPlatform
            || $this->getDatabasePlatform() instanceof OraclePlatform
        ) {
            $this->expectException(ExpectationFailedException::class);
        }
        self::assertSame('2013-02-21 05:00:12.235689', $mm->get('datetime')->format('Y-m-d H:i:s.u'));
        self::assertSame('2013-02-20', $mm->get('date')->format('Y-m-d'));
        self::assertSame('12:00:50.235689', $mm->get('time')->format('H:i:s.u'));

        self::assertTrue($mm->get('b1'));
        self::assertFalse($mm->get('b2'));

        $m->createEntity()->setMulti(array_diff_key($mm->get(), ['id' => true]))->save();
        $m->delete(1);

        unset($dbData['types']['_types']);
        unset($dbData['types'][0]);
        $row['money'] = 8.2;
        $dbData['types'][2] = array_merge(['id' => 2], $row);

        self::{'assertEquals'}($dbData, $this->getDb());
    }

    public function testJsonSerialize(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'json']);

        self::assertSame(
            ['data' => ($this->getDatabasePlatform() instanceof PostgreSQLPlatform ? "atk4_explicit_cast\ru5f8mzx4vsm8g2c9\rjson\r" : '') . '{"foo":"bar"}'],
            $this->db->typecastSaveRow(
                $m,
                ['data' => ['foo' => 'bar']]
            )
        );
        self::assertSame(
            ['data' => ['foo' => 'bar']],
            $this->db->typecastLoadRow(
                $m,
                ['data' => '{"foo":"bar"}']
            )
        );
    }

    public function testJsonSerializeException(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'json']);

        $this->expectException(Exception::class);
        $this->db->typecastLoadRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    public function testJsonSerializeException2(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'json']);

        // recursive array - json can't encode that
        $dbData = [];
        $dbData[] = &$dbData;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            Connection::isDbal3x()
                ? 'Could not convert PHP type \'array\' to \'json\', as an \'Recursion detected\''
                : 'Could not convert PHP type "array" to "json". An error was triggered by the serialization: Recursion detected'
        );
        $this->db->typecastSaveRow($m, ['data' => ['foo' => 'bar', 'recursive' => $dbData]]);
    }

    public function testLoad(): void
    {
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'date'],
                ['date' => new \DateTime('2013-02-20')],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date']);

        $m = $m->load(1);
        self::assertInstanceOf(\DateTime::class, $m->get('date'));
    }

    public function testLoadAny(): void
    {
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'date'],
                ['date' => new \DateTime('2013-02-20')],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date']);

        $m = $m->loadAny();
        self::assertInstanceOf(\DateTime::class, $m->get('date'));
    }

    public function testLoadBy(): void
    {
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'date'],
                ['date' => new \DateTime('2013-02-20')],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('date', ['type' => 'date']);

        $m2 = $m->loadOne();
        self::assertTrue($m2->isLoaded());
        $d = $m2->get('date');
        self::assertInstanceOf(\DateTime::class, $d);

        $m->insert(['date' => new \DateTime()]);

        $m2 = $m->loadBy('date', $d);
        self::assertTrue($m2->isLoaded());

        $m2 = $m->loadBy([['date', $d], ['date', '>=', $d], ['date', '<=', $d]]);
        self::assertTrue($m2->isLoaded());

        $m2 = $m->addCondition('date', $d)->loadOne();
        self::assertTrue($m2->isLoaded());
    }

    public function testDateTimeLoadFromString(): void
    {
        $this->setDb([
            'types' => [
                ['dt' => '2016-10-25 11:44:08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('dt', ['type' => 'datetime']);
        $m = $m->loadOne();

        self::{'assertEquals'}(new \DateTime('2016-10-25 11:44:08 UTC'), $m->get('dt'));
    }

    public function testDateTimeLoadFromStringInvalidException(): void
    {
        $this->setDb([
            'types' => [
                ['dt' => '20blah16-10-25 11:44:08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('dt', ['type' => 'datetime']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Typecast parse error');
        $m->loadOne();
    }

    public function testDirtyDateTime(): void
    {
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'datetime'],
                ['date' => new \DateTime('2016-10-25 11:44:08')],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'datetime']);
        $m = $m->loadOne();
        $m->set('ts', clone $m->get('ts'));

        self::assertFalse($m->isDirty('ts'));
    }

    public function testDateSave(): void
    {
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'date'],
                ['date' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'date']);
        $m = $m->loadOne();
        $m->set('ts', new \DateTime('2012-02-30'));
        $m->save();

        self::assertSameExportUnordered([
            'types' => [
                1 => ['id' => 1, 'date' => new \DateTime('2012-02-30 UTC')],
            ],
        ], $this->getDb());
    }

    public function testIntegerSave(): void
    {
        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('i', ['type' => 'integer']);
        $m = $m->createEntity();

        $m->getDataRef()['i'] = 1;
        self::assertSame([], $m->getDirtyRef());

        $m->set('i', '1');
        self::assertSame([], $m->getDirtyRef());

        $m->set('i', '2');
        self::assertSame(['i' => 1], $m->getDirtyRef());

        $m->set('i', '1');
        self::assertSame([], $m->getDirtyRef());

        // same test without type integer
        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('i');
        $m = $m->createEntity();

        $m->getDataRef()['i'] = 1;
        self::assertSame([], $m->getDirtyRef());

        $m->set('i', '1');
        self::assertSame([], $m->getDirtyRef());

        $m->set('i', '2');
        self::assertSame(['i' => 1], $m->getDirtyRef());

        $m->set('i', '1');
        self::assertSame([], $m->getDirtyRef());

        $m->set('i', 1);
        self::assertSame([], $m->getDirtyRef());
    }

    public function testDirtyTime(): void
    {
        $sqlTime = new \DateTime('11:44:08 GMT');
        $sqlTimeNew = new \DateTime('12:34:56 GMT');
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'time'],
                ['date' => $sqlTime],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m = $m->loadOne();

        $m->set('ts', $sqlTimeNew);
        self::assertTrue($m->isDirty('ts'));

        $m->set('ts', $sqlTime);
        self::assertFalse($m->isDirty('ts'));

        $m->set('ts', $sqlTimeNew);
        self::assertTrue($m->isDirty('ts'));
    }

    public function testDirtyTimeAfterSave(): void
    {
        $sqlTime = new \DateTime('11:44:08 GMT');
        $sqlTimeNew = new \DateTime('12:34:56 GMT');
        $this->setDb([
            'types' => [
                '_types' => ['date' => 'time'],
                ['date' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'types']);
        $m->addField('ts', ['actual' => 'date', 'type' => 'time']);
        $m = $m->loadOne();

        $m->set('ts', $sqlTime);
        self::assertTrue($m->isDirty('ts'));

        $m->save();
        $m->reload();

        $m->set('ts', $sqlTime);
        self::assertFalse($m->isDirty('ts'));

        $m->set('ts', $sqlTimeNew);
        self::assertTrue($m->isDirty('ts'));
    }
}
