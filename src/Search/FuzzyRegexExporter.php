<?php

declare(strict_types=1);

namespace Atk4\Data\Search;

use Atk4\Core\WarnDynamicPropertyTrait;

class FuzzyRegexExporter
{
    use WarnDynamicPropertyTrait;

    /**
     * @return list<string>
     */
    protected function exportNodes(FuzzyRegexNode $node): array
    {
        $res = [];
        foreach ($node->getNodes() as $node) {
            $res[] = is_string($node) ? $node : $this->export($node);
        }

        return $res;
    }

    protected function exportQuantifier(FuzzyRegexNode $node): ?string
    {
        if (!$node->hasQuantifier()) {
            return null;
        }

        [$min, $max] = $node->getQuantifier();

        if ($max === 1) {
            return '?';
        } elseif ($max === \PHP_INT_MAX) {
            if ($min <= 1) {
                return $min === 1 ? '+' : '*';
            }

            return '{' . $min . ',}';
        } elseif ($min === $max) {
            return '{' . $min . '}';
        }

        return '{' . $min . ',' . $max . '}';
    }

    public function export(FuzzyRegexNode $node): string
    {
        $resNodes = $this->exportNodes($node);
        $resQuantifier = $this->exportQuantifier($node);

        return '(' . implode($node->isDisjunctive() ? '|' : '', $resNodes) . ')' . $resQuantifier;
    }
}
