<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Search;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Search\FuzzyRegexBuilder;
use Atk4\Data\Search\FuzzyRegexExporter;
use Atk4\Data\Search\FuzzyRegexNode;

class FuzzyRegexTest extends TestCase
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
        $parsedTree = $builder->parseRegex($regexWithoutDelimiter);
        $this->assertSameRegexTree($expectedRegex, $parsedTree);

        $exporter = new FuzzyRegexExporter();
        $exportedRegex = $exporter->export($parsedTree);
        $reparsedTree = $builder->parseRegex($exportedRegex);
        $this->assertSameRegexTree($exportedRegex, $reparsedTree);
    }

    /**
     * @return \Traversable<int, array{string, string}>
     */
    public function provideParseRegexData(): \Traversable
    {
        yield ['()', ''];
        yield ['(a)', 'a'];
        yield ['(ab)', 'ab'];
        yield ['(a[bc])', 'a[bc]'];
        yield ['((ab)|c)', 'ab|c'];
        yield ['(()|a)', '(((()))()?|(((a))))'];
        yield ['(((a)?)b((c)*)(((cd))+)(((e|f))?))', 'a?bc*(cd)+(e|f)?'];
        yield ['(((a){2})b((c)?)(((cd)){1,2})(((e|f)){2,}))', 'a{2}bc{0,1}(cd){1,2}(e|f){2,}'];
        yield ['(\\\\((\d)+))', '\\\\\d+'];
    }

    /**
     * @dataProvider provideExpandRegexToConjunctionsData
     */
    public function testExpandRegexToConjunctions(string $expectedRegex, string $regexWithoutDelimiter): void
    {
        $builder = new FuzzyRegexBuilder();
        $parsedTree = $builder->parseRegex($regexWithoutDelimiter);
        $conjunctiveTrees = $builder->expandRegexToConjunctions($parsedTree);
        $this->assertSameRegexTree($expectedRegex, new FuzzyRegexNode(true, $conjunctiveTrees));

        $conjunctiveTrees2 = $builder->expandRegexToConjunctions(new FuzzyRegexNode(true, $conjunctiveTrees));
        $this->assertSameRegexTree($expectedRegex, new FuzzyRegexNode(true, $conjunctiveTrees2));
    }

    /**
     * @return \Traversable<int, array{string, string}>
     */
    public function provideExpandRegexToConjunctionsData(): \Traversable
    {
        yield ['(())', ''];
        yield ['(a)', 'a'];
        yield ['((abc))', 'a(bc)'];
        yield ['((ab[cd]))', 'ab[cd]'];
        yield ['((ab)|c)', 'ab|c'];
        yield ['((ab)|())', 'ab|'];
        yield ['((ab)|(cd))', 'ab|cd'];
        yield ['((ab)|c|d)', 'ab|(c|d)'];
        yield ['((abc)|(abd))', 'ab(c|d)'];
        yield ['((ab)|(ac))', '(((a)))((((((b)))|(((c))))))((()))'];
        yield ['((axce)|(axcf)|(axcg)|(axde)|(axdf)|(axdg)|(byce)|(bycf)|(bycg)|(byde)|(bydf)|(bydg))', '(ax|by)(c|d)(e|f|g)'];
        yield ['((ab)|(acd)|a)', 'a((b|cd)|)'];

        yield ['(((a)+))', 'a+'];
        yield ['((((a)+)b))', 'a+b'];
        yield ['(((a)+)|b)', 'a+|b'];
        yield ['(((a)+)|((bc)+))', 'a+|(bc)+'];
        yield ['(((a)+)|((bc)+))', '(a|bc)+'];
        yield ['(((((a)+)b)+))', '(a+b)+'];
        yield ['(((((a)+)d)+)|((((bc)+)d)+))', '((a|bc)+d)+'];
    }

    /**
     * @dataProvider provideExpandConjunctionsForOneTypoData
     */
    public function testExpandConjunctionsForOneTypo(string $expectedRegex, string $regexWithoutDelimiter): void
    {
        $builder = new FuzzyRegexBuilder();
        $parsedTree = $builder->parseRegex($regexWithoutDelimiter);
        $conjunctiveTrees = $builder->expandRegexToConjunctions($parsedTree);
        $conjunctiveForOneTypoTrees = \Closure::bind(fn () => $builder->expandConjunctionsForOneTypo($conjunctiveTrees), null, FuzzyRegexBuilder::class)();
        $this->assertSameRegexTree($expectedRegex, new FuzzyRegexNode(true, $conjunctiveForOneTypoTrees));
    }

    /**
     * @return \Traversable<int, array{string, string}>
     */
    public function provideExpandConjunctionsForOneTypoData(): \Traversable
    {
        foreach ($this->provideExpandRegexToConjunctionsData() as $args) {
            if (!preg_match('~[?*+{]~', $args[0])) {
                yield $args;
            }
        }

        yield ['(a|())', 'a?'];
        yield ['((aa)|a|())', 'a{0,2}'];
        yield ['((aa)|a)', 'a{1,2}'];
        yield ['((aa))', 'a{2}'];
        yield ['((((a)*)a((a)*))|())', 'a*'];
        yield ['((((a)*)a((a)*)))', 'a+'];
        yield ['((((a)+)a((a)*))|(((a)*)a((a)+)))', 'a{2,}'];
        yield ['((((a){2,})a((a)*))|(((a)+)a((a)+))|(((a)*)a((a){2,})))', 'a{3,}'];
        yield ['((((a){3,})a((a)*))|(((a){2,})a((a)+))|(((a)+)a((a){2,}))|(((a)*)a((a){3,})))', 'a{4,}'];
        yield ['((((a){0,2})a)|(((a)?)a((a)?))|(a((a){0,2}))|())', 'a{0,3}'];
        yield ['((((a){0,2})a)|(((a)?)a((a)?))|(a((a){0,2})))', 'a{1,3}'];
        yield ['((((a){1,2})a)|(((a)?)aa)|(a((a){1,2})))', 'a{2,3}'];
        yield ['((((a){2})a)|(aaa)|(a((a){2})))', 'a{3}'];
        yield ['((((a){0,3})a)|(((a){0,2})a((a)?))|(((a)?)a((a){0,2}))|(a((a){0,3}))|())', 'a{0,4}'];
        yield ['((((a){0,3})a)|(((a){0,2})a((a)?))|(((a)?)a((a){0,2}))|(a((a){0,3})))', 'a{1,4}'];
        yield ['((((a){1,3})a)|(((a){0,2})aa)|(((a)?)a((a){1,2}))|(a((a){1,3})))', 'a{2,4}'];
        yield ['((((a){2,3})a)|(((a){1,2})aa)|(((a)?)a((a){2}))|(a((a){2,3})))', 'a{3,4}'];
        yield ['((((a){3})a)|(((a){2})aa)|(aa((a){2}))|(a((a){3})))', 'a{4}'];
        yield ['((((a){0,5})a)|(((a){0,4})a((a)?))|(((a){0,3})a((a){0,2}))|(((a){0,2})a((a){0,3}))|(((a)?)a((a){0,4}))|(a((a){0,5})))', 'a{1,6}'];
        yield ['((abab)|(ab)|())', '(ab){0,2}'];
        yield ['((((ab)*)ab((ab)*))|())', '(ab)*'];
        yield ['((((ab)*)ab((ab)*)))', '(ab)+'];
        yield ['((((ab){2})ab)|(ababab)|(ab((ab){2})))', '(ab){3}'];
        yield ['((ab)|b)', '(a?b)'];
        yield ['((abc)|c)', '((ab)?c)'];
        yield ['((((((ab)*)ab((ab)*)c)*)((ab)*)ab((ab)*)c((((ab)*)ab((ab)*)c)*)))', '((ab)+c)+'];
    }
}
