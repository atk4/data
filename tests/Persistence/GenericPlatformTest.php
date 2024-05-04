<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\GenericPlatform;
use Doctrine\DBAL\Exception as DbalException;
use PHPUnit\Framework\Attributes\DataProvider;

class GenericPlatformTest extends TestCase
{
    public function testGetName(): void
    {
        $genericPlatform = new GenericPlatform();
        self::assertSame('atk4_data_generic', $genericPlatform->getName()); // @phpstan-ignore-line
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
        $this->expectExceptionMessage('Operation \'SQL\' is not supported by platform.');
        \Closure::bind(static fn () => $genericPlatform->{$methodName}(...$args), null, GenericPlatform::class)();
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideNotSupportedExceptionCases(): iterable
    {
        yield ['_getCommonIntegerTypeDeclarationSQL', [[]]];
        yield ['getBigIntTypeDeclarationSQL', [[]]];
        yield ['getBlobTypeDeclarationSQL', [[]]];
        yield ['getBooleanTypeDeclarationSQL', [[]]];
        yield ['getClobTypeDeclarationSQL', [[]]];
        yield ['getIntegerTypeDeclarationSQL', [[]]];
        yield ['getSmallIntTypeDeclarationSQL', [[]]];
        yield ['getCurrentDatabaseExpression', []];
    }
}
