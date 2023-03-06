<?php

declare(strict_types=1);

namespace Atk4\Data\Search;

use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Exception;

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

    protected function canUnwrapRegexNode(FuzzyRegexNode $node): bool
    {
        return !$node->hasQuantifier();
    }

    public function parseRegex(string $regexWithoutDelimiter): FuzzyRegexNode
    {
        if (preg_match_all(
                '~(\\\\.|(\((?R)*+\))|\[(?:\\\\.|[^\]])*\]|[^\\\\()])([?*+]|\{(\d+)(,?)(\d*)\}|)~su',
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
                $innerNode = $this->parseRegex(substr($matches[2], 1, -1));
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

        return count($disjunctiveNodes) === 1
            ? reset($disjunctiveNodes)
            : new FuzzyRegexNode(count($disjunctiveNodes) > 0, $disjunctiveNodes);
    }

    /**
     * WARNING: string nodes are expected to be single character already, this is true when
     *          the regex tree was created using self::parseRegex() method
     */
    public function expandRegexTreeToDisjunctiveCharacters(FuzzyRegexNode $node): FuzzyRegexNode
    {
        // expand quantifier so start/end characters can be separated
        if ($node->hasQuantifier()) {
            [$quantifierMin, $quantifierMax] = $node->getQuantifier();

            // TODO
            // return here - code below does expect no quantifier
        }

        $innerNodes = [];
        $isUnchanged = true;
        foreach ($node->getNodes() as $innerNode) {
            if (is_string($innerNode)) {
                $innerNodes[] = $innerNode;

                continue;
            }

            $nodeExpanded = $this->expandRegexTreeToDisjunctiveCharacters($innerNode);
            $nodeExpandedNodes = $nodeExpanded->getNodes();
            if (count($nodeExpandedNodes) === 1) { // optimization only
                $nodeExpanded = reset($nodeExpandedNodes);
            }
            $innerNodes[] = $nodeExpanded;
            if ($nodeExpanded !== $innerNode) {
                $isUnchanged = false;
            }
        }

        if ($node->isDisjunctive()) {
            return $isUnchanged
                ? $node
                : new FuzzyRegexNode(true, $innerNodes);
        }

        $leftNodesNodes = [[]];
        foreach ($innerNodes as $innerNode) {
            $innerNodeNodes = is_string($innerNode) || !$innerNode->isDisjunctive()
                ?[$innerNode]
                : $innerNode->getNodes();

            $leftNodesNodesOrig = $leftNodesNodes;
            $leftNodesNodes = [];
            foreach ($leftNodesNodesOrig as $leftNodeNodes) {
                foreach ($innerNodeNodes as $innerNodeNode) {
                    $nodes = $leftNodeNodes;
                    if (!is_string($innerNodeNode) && !$innerNodeNode->isDisjunctive() && $this->canUnwrapRegexNode($innerNodeNode)) { // optimization only
                        foreach ($innerNodeNode->getNodes() as $innerNodeNodeNode) {
                            $nodes[] = $innerNodeNodeNode;
                        }
                    } else {
                        $nodes[] = $innerNodeNode;
                    }
                    $leftNodesNodes[] = $nodes;
                }
            }
        }

        $innerNodes = [];
        foreach ($leftNodesNodes as $nodes) {
            $innerNodes[] = count($nodes) === 1
                ? reset($nodes)
                : new FuzzyRegexNode(false, $nodes);
        }

        return new FuzzyRegexNode(true, $innerNodes);
    }
}
