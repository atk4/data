<?php

declare(strict_types=1);

namespace Atk4\Data\Search;

use Atk4\Core\WarnDynamicPropertyTrait;

class FuzzyRegexBuilder
{
    use WarnDynamicPropertyTrait;

    public function stripRegexDelimiter(string $regex, string $targetDelimiter = '~'): string
    {
        $delimiter = substr($regex, 0, 1);
        $lastPos = strrpos($regex, $delimiter);

        if (strlen($delimiter) !== 1 || $lastPos < 1) {
            throw new Exception('Failed to locate regex delimiter');
        }

        $regexWithoutDelimiter = substr($regex, 1, $lastPos - 1);

        return $this->replaceRegexDelimiter($regexWithoutDelimiter, $delimiter, $targetDelimiter);
    }

    public function replaceRegexDelimiter(string $regexWithoutDelimiter, string $delimiter, string $newDelimiter): string
    {
        if ($delimiter === $newDelimiter) {
            return $regexWithoutDelimiter;
        }

        return str_replace($newDelimiter, '\\' . $newDelimiter, str_replace('\\' . $delimiter, $delimiter, $regexWithoutDelimiter));
    }

    public function parseRegex(string $regexWithoutDelimiter): FuzzyRegexNode
    {
        if (preg_match_all(
                '~(\\\\.|\[(?:\\\\.|[^\]])*\]|\(((?1)*?)\)|.)([?*+]|\{(\d+)(,?)(\d*)\}|)~su',
                $regexWithoutDelimiter,
                $matchesAll,
                \PREG_SET_ORDER
            ) === false || array_sum(array_map(fn ($matches) => strlen($matches[0]), $matchesAll)) !== strlen($regexWithoutDelimiter)) {
            throw new Exception('Failed to tokenize search regex');
        }

        $disjunctiveNodes = [];
        $currentNodes = [];
        foreach ($matchesAll as $matches) {
            if ($matches[0] === '|') {
                $disjunctiveNodes[] = new FuzzyRegexNode(false, $currentNodes);
                $currentNodes = [];

                continue;
            }

            if ($matches[2] !== '') {
                $innerNode = $this->parseRegex($matches[2]);
            } else {
                $innerNode = $matches[1];
            }

            if ($matches[3] !== '') {
                if (($matches[4] ?? '') !== '') {
                    $quantifierMin = (int) $matches[4];
                    if ($matches[5] === '') {
                        $quantifierMax = $quantifierMin;
                    } else {
                        if ($matches[6] !== '') {
                            $quantifierMax = (int) $matches[6];
                        } else {
                            $quantifierMax = \PHP_INT_MAX;
                        }
                    }
                } else {
                    if ($matches[3] === '?') {
                        $quantifierMin = 0;
                        $quantifierMax = 1;
                    } else {
                        $quantifierMin = $matches[3] === '+' ? 1 : 0;
                        $quantifierMax = \PHP_INT_MAX;
                    }
                }
            } else {
                $quantifierMin = 1;
                $quantifierMax = 1;
            }

            $currentNodes[] = $quantifierMin === 1 && $quantifierMax === 1
                ? $innerNode
                : new FuzzyRegexNode(false, [$innerNode], $quantifierMin, $quantifierMax);
        }
        $disjunctiveNodes[] = new FuzzyRegexNode(false, $currentNodes);
        $currentNodes = [];

        return count($disjunctiveNodes) === 1
            ? reset($disjunctiveNodes)
            : new FuzzyRegexNode(count($disjunctiveNodes) > 0, $disjunctiveNodes);
    }
}
