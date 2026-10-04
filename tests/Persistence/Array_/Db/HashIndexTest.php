<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Array_\Db;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Array_\Db\HashIndex;
use PHPUnit\Framework\Attributes\DataProvider;

class HashIndexTest extends TestCase
{
    public function testBasic(): void
    {
        $index = new HashIndex();

        \Closure::bind(static function () use ($index) {
            TestCase::assertSame([], $index->data);

            $index->addRow(0, 'foo');
            TestCase::assertSame(['foo' => [0 => true]], $index->data);
            TestCase::assertSame([0], $index->findPossibleRowIndexes('foo'));

            $index->addRow(10, 1);
            $index->addRow(20, '1');
            TestCase::assertSame(['foo' => [0 => true], 1 => [10 => true, 20 => true]], $index->data);
            TestCase::assertSame([0], $index->findPossibleRowIndexes('foo'));
            TestCase::assertSame([10, 20], $index->findPossibleRowIndexes(1));

            $index->deleteRow(10, 1);
            TestCase::assertSame(['foo' => [0 => true], 1 => [20 => true]], $index->data);
            TestCase::assertSame([0], $index->findPossibleRowIndexes('foo'));
            TestCase::assertSame([20], $index->findPossibleRowIndexes(1));

            $index->deleteRow(20, 1);
            TestCase::assertSame(['foo' => [0 => true]], $index->data);
            TestCase::assertSame([0], $index->findPossibleRowIndexes('foo'));
            TestCase::assertSame([], $index->findPossibleRowIndexes(1));
        }, null, HashIndex::class)();
    }

    /**
     * @param scalar|null $value
     * @param int|string  $expectedKey
     *
     * @dataProvider provideMakeKeyFromValueCases
     */
    #[DataProvider('provideMakeKeyFromValueCases')]
    public function testMakeKeyFromValue($value, $expectedKey): void
    {
        $index = new HashIndex();

        self::assertSame(
            $expectedKey,
            \Closure::bind(static fn () => $index->makeKeyFromValue($value), null, HashIndex::class)()
        );
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideMakeKeyFromValueCases(): iterable
    {
        yield ['', ''];
        yield [null, ''];
        yield ['foo', 'foo'];
        yield [false, 0];
        yield [true, 1];
        yield [0, 0];
        yield [1, 1];
        yield [\PHP_INT_MIN, \PHP_INT_MIN];
        yield [\PHP_INT_MAX, \PHP_INT_MAX];
        yield ['1', 1];
        yield [(string) \PHP_INT_MIN, \PHP_INT_MIN];
        yield [(string) \PHP_INT_MAX, \PHP_INT_MAX];
        yield [0.0, 0];
        yield [1.0, 1];
        yield [0.5, '0.5'];
        yield [-0.5, '-0.5'];
        yield [-1e300, '-1.0E+300'];
        yield [1e300, '1.0E+300'];
        yield [-\INF, '-INF'];
        yield [\INF, 'INF'];
        yield [\NAN, 'NAN'];
    }
}
