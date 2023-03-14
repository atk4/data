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
            throw (new Exception('Failed to locate regex delimiter'))
                ->addMoreInfo('regex', $regex);
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
            '~(\\\\.|(\((?R)*+\))|\[(?:\\\\.|[^\]])*\]|[^\\\\()[\]])([?*+]|\{(\d+)(,?)(\d*)\}|)~su',
            $regexWithoutDelimiter,
            $matchesAll,
            \PREG_SET_ORDER
        ) === false || array_sum(array_map(fn ($matches) => strlen($matches[0]), $matchesAll)) !== strlen($regexWithoutDelimiter)) {
            throw (new Exception('Failed to tokenize search regex'))
                ->addMoreInfo('regex', $regexWithoutDelimiter);
        }

        $nodes = [];
        $currentNodes = [];
        foreach ($matchesAll as $matches) {
            if ($matches[0] === '|') {
                $nodes[] = count($currentNodes) === 1
                    ? reset($currentNodes)
                    : new FuzzyRegexNode(false, $currentNodes);
                $currentNodes = [];

                continue;
            }

            if ($matches[2] !== '') {
                $innerNode = $this->parseRegex(substr($matches[2], 1, -1));
                if (!$innerNode->hasQuantifier()) {
                    $innerNodeNodes = $innerNode->getNodes();
                    if ($innerNodeNodes === []) {
                        continue;
                    }
                    if (count($innerNodeNodes) === 1 && is_string(reset($innerNodeNodes))) {
                        $innerNode = reset($innerNodeNodes);
                    }
                }
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
        $nodes[] = count($currentNodes) === 1
            ? reset($currentNodes)
            : new FuzzyRegexNode(false, $currentNodes);

        return count($nodes) === 1 && !is_string(reset($nodes))
            ? reset($nodes)
            : new FuzzyRegexNode(count($nodes) > 1, $nodes);
    }

    /**
     * @param list<string|FuzzyRegexNode> $conjunctiveNodes
     * @param array{int, int} $quantifier
     *
     * @return list<string|FuzzyRegexNode>
     */
    protected function flattenConjunctionsShallow(array $conjunctiveNodes, array $quantifier): array
    {
        $leftNodesNodes = [[]];
        foreach ($conjunctiveNodes as $conjunctiveNode) {
            $innerNodes = !is_string($conjunctiveNode) && !$conjunctiveNode->hasQuantifier()
                ? $conjunctiveNode->getNodes()
                : [$conjunctiveNode];

            $leftNodesNodesOrig = $leftNodesNodes;
            $leftNodesNodes = [];
            foreach ($leftNodesNodesOrig as $leftNodeNodes) {
                foreach ($innerNodes as $innerNode) {
                    $nodes = $leftNodeNodes;
                    if (!is_string($innerNode) && !$innerNode->hasQuantifier()) {
                        foreach ($innerNode->getNodes() as $innerNodeNode) {
                            $nodes[] = $innerNodeNode;
                        }
                    } else {
                        $nodes[] = $innerNode;
                    }
                    $leftNodesNodes[] = $nodes;
                }
            }
        }

        $res = [];
        foreach ($leftNodesNodes as $nodes) {
            $res[] = count($nodes) === 1 && $quantifier === [1, 1]
                ? reset($nodes)
                : new FuzzyRegexNode(false, $nodes, ...$quantifier);
        }

        return $res;
    }

    /**
     * @return list<string|FuzzyRegexNode>
     *
     * WARNING: string nodes are assumed to be conjunctive, this is true when the regex tree
     *          was created using self::parseRegex() method
     */
    public function expandRegexToConjunctions(FuzzyRegexNode $node): array
    {
        $conjunctiveNodes = [];
        foreach ($node->getNodes() as $innerNode) {
            if (is_string($innerNode)) {
                $conjunctiveNodes[] = $innerNode;
            } else {
                $innerNodes = $this->expandRegexToConjunctions($innerNode);
                if ($node->isDisjunctive()) {
                    foreach ($innerNodes as $conjunctiveNode) {
                        $conjunctiveNodes[] = $conjunctiveNode;
                    }
                } else {
                    $conjunctiveNodes[] = new FuzzyRegexNode(true, $innerNodes);
                }
            }
        }

        if ($node->isDisjunctive()) {
            if ($node->hasQuantifier()) {
                $conjunctiveNodesOrig = $conjunctiveNodes;
                $conjunctiveNodes = [];
                foreach ($conjunctiveNodesOrig as $conjunctiveNode) {
                    $conjunctiveNodes[] = new FuzzyRegexNode(false, [$conjunctiveNode], ...$node->getQuantifier());
                }
            }

            return $conjunctiveNodes;
        }

        $res = $this->flattenConjunctionsShallow($conjunctiveNodes, $node->getQuantifier());

        return $res;
    }

    /**
     * @return list<string|FuzzyRegexNode>
     */
    protected function expandConjunctionsForOneTypoSingle(FuzzyRegexNode $conjunctiveNode, int $unquantifiedCount): array
    {
        if ($unquantifiedCount <= 0) {
            return [$conjunctiveNode];
        }

        $conjunctiveNodes = [];
        foreach ($conjunctiveNode->getNodes() as $innerNode) {
            if (is_string($innerNode)) {
                $conjunctiveNodes[] = $innerNode;
            } else {
                $conjunctiveNodes[] = new FuzzyRegexNode(true, $this->expandConjunctionsForOneTypoSingle($innerNode, $unquantifiedCount));
            }
        }

        $conjunctiveNodes = $this->flattenConjunctionsShallow($conjunctiveNodes, [1, 1]);

        if (!$conjunctiveNode->hasQuantifier()) {
            return $conjunctiveNodes;
        }

        [$quantifierMin, $quantifierMax] = $conjunctiveNode->getQuantifier();

        $res = [];
        $skipLoop = $quantifierMax < 2 + $unquantifiedCount;
        foreach ($conjunctiveNodes as $node) {
            $nodeNodes = [];
            if (!is_string($node) && !$node->hasQuantifier()) {
                foreach ($node->getNodes() as $nodeNode) {
                    $nodeNodes[] = $nodeNode;
                }
            } else {
                $nodeNodes[] = $node;
            }

            for ($i = 0; $i <= $quantifierMax - $unquantifiedCount && !$skipLoop; ++$i) {
                $quantifierLeftMin = max(0, $quantifierMin - $i - $unquantifiedCount);
                $quantifierLeftMax = $quantifierMax === \PHP_INT_MAX ? \PHP_INT_MAX : max(0, $quantifierMax - $i - $unquantifiedCount);
                $quantifierRightMin = max(0, $quantifierMin - $quantifierLeftMin - $unquantifiedCount);
                $quantifierRightMax = $quantifierMax === \PHP_INT_MAX ? \PHP_INT_MAX : $quantifierMax - $quantifierLeftMax - $unquantifiedCount;

                $innerNodes = [];
                if ($quantifierLeftMax !== 0) {
                    if ($quantifierLeftMin === 1 && $quantifierLeftMax === 1) {
                        foreach ($nodeNodes as $nodeNode) {
                            $innerNodes[] = $nodeNode;
                        }
                    } else {
                        $innerNodes[] = new FuzzyRegexNode(false, $nodeNodes, $quantifierLeftMin, $quantifierLeftMax);
                    }
                }
                for ($j = 0; $j < $unquantifiedCount; ++$j) {
                    foreach ($nodeNodes as $nodeNode) {
                        $innerNodes[] = $nodeNode;
                    }
                }
                if ($quantifierRightMax !== 0) {
                    if ($quantifierRightMin === 1 && $quantifierRightMax === 1) {
                        foreach ($nodeNodes as $nodeNode) {
                            $innerNodes[] = $nodeNode;
                        }
                    } else {
                        $innerNodes[] = new FuzzyRegexNode(false, $nodeNodes, $quantifierRightMin, $quantifierRightMax);
                    }
                }
                $res[] = new FuzzyRegexNode(false, $innerNodes);

                if ($quantifierMax === \PHP_INT_MAX && $i >= $quantifierMin - $unquantifiedCount) {
                    break;
                }
            }

            for ($i = $quantifierMax === \PHP_INT_MAX || !$skipLoop ? $unquantifiedCount - 1 : $quantifierMax; $i >= $quantifierMin; --$i) {
                $innerNodes = [];
                for ($j = 0; $j < $i; ++$j) {
                    foreach ($nodeNodes as $nodeNode) {
                        $innerNodes[] = $nodeNode;
                    }
                }
                $res[] = count($innerNodes) === 1
                    ? reset($innerNodes)
                    : new FuzzyRegexNode(false, $innerNodes);
            }
        }

        return $res;
    }

    /**
     * Expand nodes with quantifiers such exactly one typo can be detected if allowed in exactly
     * one node without quantifiers.
     *
     * @param list<string|FuzzyRegexNode> $conjunctiveNodes
     *
     * @return list<string|FuzzyRegexNode>
     */
    protected function expandConjunctionsForOneTypo(array $conjunctiveNodes, int $unquantifiedCount): array
    {
        $res = [];
        foreach ($conjunctiveNodes as $conjunctiveNode) {
            if (is_string($conjunctiveNode)) {
                $res[] = $conjunctiveNode;
            } else {
                foreach ($this->expandConjunctionsForOneTypoSingle($conjunctiveNode, $unquantifiedCount) as $conjunctiveNodeNode) {
                    $res[] = $conjunctiveNodeNode;
                }
            }
        }

        return $res;
    }
}
