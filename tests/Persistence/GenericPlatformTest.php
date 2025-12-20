<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\GenericPlatform;
use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\DateIntervalUnit;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;
use PHPUnit\Framework\Attributes\DataProvider;

class GenericPlatformTest extends TestCase
{
    public function testGetName(): void
    {
        $genericPlatform = new GenericPlatform();
        self::assertSame('atk4_data_generic', $genericPlatform->getName()); // @phpstan-ignore method.deprecated
    }

    public function testInitializeDoctrineTypeMappings(): void
    {
        $genericPlatform = new GenericPlatform();
        self::assertFalse($genericPlatform->hasDoctrineTypeMappingFor('foo'));
    }

    /**
     * @dataProvider provideNotSupportedExceptionCases
     *
     * @param list<mixed> $args
     */
    #[DataProvider('provideNotSupportedExceptionCases')]
    public function testNotSupportedException(string $methodName, array $args): void
    {
        $genericPlatform = new GenericPlatform();

        $this->expectException(DbalException::class);
        $this->expectExceptionMessage('Operation ' . (Connection::isDbal3x() ? '\'SQL\'' : '"SQL"') . ' is not supported by platform.');
        \Closure::bind(static fn () => $genericPlatform->{$methodName}(...$args), null, GenericPlatform::class)();
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideNotSupportedExceptionCases(): iterable
    {
        yield ['getBooleanTypeDeclarationSQL', [[]]];
        yield ['getIntegerTypeDeclarationSQL', [[]]];
        yield ['getBigIntTypeDeclarationSQL', [[]]];
        yield ['getSmallIntTypeDeclarationSQL', [[]]];
        yield ['_getCommonIntegerTypeDeclarationSQL', [[]]];
        yield ['getClobTypeDeclarationSQL', [[]]];
        yield ['getBlobTypeDeclarationSQL', [[]]];
        yield ['getCurrentDatabaseExpression', []];
        if (!Connection::isDbal3x()) {
            yield ['getLocateExpression', ['', '']];
            yield ['getDateDiffExpression', ['', '']];
            yield ['getDateArithmeticIntervalExpression', ['', '', '', DateIntervalUnit::SECOND]];
            yield ['getAlterTableSQL', [(new \ReflectionClass(TableDiff::class))->newInstanceWithoutConstructor()]];
            yield ['getListViewsSQL', ['']];
            yield ['getSetTransactionIsolationSQL', [TransactionIsolationLevel::READ_COMMITTED]];
            yield ['getDateTimeTypeDeclarationSQL', [[]]];
            yield ['getDateTypeDeclarationSQL', [[]]];
            yield ['getTimeTypeDeclarationSQL', [[]]];
            yield ['createReservedKeywordsList', []];
            yield ['createSchemaManager', [(new \ReflectionClass(DbalConnection::class))->newInstanceWithoutConstructor()]];
        }
    }
}
