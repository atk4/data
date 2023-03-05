<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Search;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Search\FuzzyRegexExporter;
use Atk4\Data\Search\FuzzyRegexNode;

class FuzzyRegexBuilderTest extends TestCase
{
    protected function assertSameRegexTree(string $expectedRegex, FuzzyRegexNode $actualRegexTree): void
    {
        $exporter = new FuzzyRegexExporter();
        static::assertSame($expectedRegex, $exporter->export($actualRegexTree));
    }

    public function testRegexTreeExport(): void
    {
        $this->assertSameRegexTree('()', new FuzzyRegexNode(false, []));
        $this->assertSameRegexTree('(a)', new FuzzyRegexNode(false, ['a']));
        $this->assertSameRegexTree('(ab)', new FuzzyRegexNode(false, ['a', 'b']));
        $this->assertSameRegexTree('()', new FuzzyRegexNode(true, []));
        $this->assertSameRegexTree('(a)', new FuzzyRegexNode(true, ['a']));
        $this->assertSameRegexTree('(a|b)', new FuzzyRegexNode(true, ['a', 'b']));

        $this->assertSameRegexTree('(a)?', new FuzzyRegexNode(true, ['a'], 0, 1));
        $this->assertSameRegexTree('(a)', new FuzzyRegexNode(true, ['a'], 1, 1));
        $this->assertSameRegexTree('(a){1,2}', new FuzzyRegexNode(true, ['a'], 1, 2));
        $this->assertSameRegexTree('(a){2}', new FuzzyRegexNode(true, ['a'], 2, 2));
        $this->assertSameRegexTree('(a)*', new FuzzyRegexNode(true, ['a'], 0, \PHP_INT_MAX));
        $this->assertSameRegexTree('(a)+', new FuzzyRegexNode(true, ['a'], 1, \PHP_INT_MAX));
        $this->assertSameRegexTree('(a){2,}', new FuzzyRegexNode(true, ['a'], 2, \PHP_INT_MAX));

        $this->assertSameRegexTree('((a|b)?c){2}', new FuzzyRegexNode(false, [new FuzzyRegexNode(true, ['a', 'b'], 0, 1), 'c'], 2, 2));
    }
}
