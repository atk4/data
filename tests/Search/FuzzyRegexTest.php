<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Search;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Search\FuzzyRegexBuilder;
use Atk4\Data\Search\FuzzyRegexExporter;
use Atk4\Data\Search\FuzzyRegexNode;

class FuzzyRegexBuilderTest extends TestCase
{
    public function testStripRegexDelimiter(): void
    {
        $builder = new FuzzyRegexBuilder();
        static::assertSame('', $builder->stripRegexDelimiter('~~'));
        static::assertSame('', $builder->stripRegexDelimiter('~~u'));
        static::assertSame('\~', $builder->stripRegexDelimiter('~\~~u'));
        static::assertSame('a[\~]b', $builder->stripRegexDelimiter('~a[\~]b~u'));
        static::assertSame('~\#', $builder->stripRegexDelimiter('~\~#~u', '#'));
    }

    protected function assertSameRegexTree(string $expectedRegex, FuzzyRegexNode $actualRegexTree): void
    {
        $exporter = new FuzzyRegexExporter();
        static::assertSame($expectedRegex, $exporter->export($actualRegexTree));
    }

    public function testRegexTreeExport(): void
    {
        $this->assertSameRegexTree('()', new FuzzyRegexNode(false, []));
        $this->assertSameRegexTree('(a)', new FuzzyRegexNode(false, ['a']));
        $this->assertSameRegexTree('((ax))', new FuzzyRegexNode(false, ['ax']));
        $this->assertSameRegexTree('([ay])', new FuzzyRegexNode(false, ['[ay]']));
        $this->assertSameRegexTree('(\d)', new FuzzyRegexNode(false, ['\d']));
        $this->assertSameRegexTree('(ab)', new FuzzyRegexNode(false, ['a', 'b']));
        $this->assertSameRegexTree('()', new FuzzyRegexNode(true, []));
        $this->assertSameRegexTree('(a)', new FuzzyRegexNode(true, ['a']));
        $this->assertSameRegexTree('(a|b)', new FuzzyRegexNode(true, ['a', 'b']));
        $this->assertSameRegexTree('((ax)|(bx))', new FuzzyRegexNode(true, ['ax', 'bx']));

        $this->assertSameRegexTree('((a)?)', new FuzzyRegexNode(true, ['a'], 0, 1));
        $this->assertSameRegexTree('(a)', new FuzzyRegexNode(true, ['a'], 1, 1));
        $this->assertSameRegexTree('((a){1,2})', new FuzzyRegexNode(true, ['a'], 1, 2));
        $this->assertSameRegexTree('((a){2})', new FuzzyRegexNode(true, ['a'], 2, 2));
        $this->assertSameRegexTree('((a)*)', new FuzzyRegexNode(true, ['a'], 0, \PHP_INT_MAX));
        $this->assertSameRegexTree('((a)+)', new FuzzyRegexNode(true, ['a'], 1, \PHP_INT_MAX));
        $this->assertSameRegexTree('((a){2,})', new FuzzyRegexNode(true, ['a'], 2, \PHP_INT_MAX));

        $this->assertSameRegexTree('((((a|b)?)c){2})', new FuzzyRegexNode(false, [new FuzzyRegexNode(true, ['a', 'b'], 0, 1), 'c'], 2, 2));
    }

    public function testReplaceRegexDelimiter(): void
    {
        $builder = new FuzzyRegexBuilder();
        static::assertSame('\~', $builder->replaceRegexDelimiter('\~', '~', '~'));
        static::assertSame('a\~\\\\\~b', $builder->replaceRegexDelimiter('a\~\\\\\~b', '~', '~'));
        static::assertSame('~', $builder->replaceRegexDelimiter('\~', '~', '#'));
        static::assertSame('a~\\\\~b', $builder->replaceRegexDelimiter('a\~\\\\\~b', '~', '#'));
        static::assertSame('a~\#\\\#\\\\\#b', $builder->replaceRegexDelimiter('a\~#\\#\\\\#b', '~', '#'));
    }

    /**
     * @dataProvider provideParseRegexData
     */
    public function testParseRegex(string $expectedRegex, string $regexWithoutDelimiter): void
    {
        $builder = new FuzzyRegexBuilder();
        $this->assertSameRegexTree($expectedRegex, $builder->parseRegex($regexWithoutDelimiter));
    }

    /**
     * @return \Traversable<int, array{string, string}>
     */
    public function provideParseRegexData(): \Traversable
    {
        yield ['(abc)', 'abc'];
        yield ['((ab)|(c))', 'ab|c'];
        yield ['([ab]c)', '[ab]c'];
        yield ['((a)|([bc]))', 'a|[bc]'];
        yield ['()', ''];
        yield ['((())(((()))?))', '()()?'];
        yield ['(((a)?)b((c)*)(((cd))+)((((e)|(f)))?))', 'a?bc*(cd)+(e|f)?'];
        yield ['(((a){2})b((c)?)(((cd)){1,2})((((e)|(f))){2,}))', 'a{2}bc{0,1}(cd){1,2}(e|f){2,}'];
        yield ['(\\\\((\d)+))', '\\\\\d+'];
    }
}
