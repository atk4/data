<?php

declare(strict_types=1);

namespace Atk4\Data\Search;

use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Exception;

class FuzzyRegexNode
{
    use WarnDynamicPropertyTrait;

    private bool $isDisjunctive;

    /** @var list<string|self> */
    private array $nodes;

    private int $quantifierMin;

    private int $quantifierMax;

    /**
     * @param list<string|self> $nodes
     */
    public function __construct(bool $isDisjunctive, array $nodes, int $quantifierMin = 1, int $quantifierMax = 1)
    {
        foreach ($nodes as $node) {
            if (!is_string($node) && !$node instanceof self) { // @phpstan-ignore-line
                throw (new Exception('Invalid node type'))
                    ->addMoreInfo('node', $node);
            }
        }

        if ($quantifierMin < 0 || $quantifierMax <= 0 || $quantifierMin > $quantifierMax) {
            throw (new Exception('Invalid quantifier'))
                ->addMoreInfo('quantifierMin', $quantifierMin)
                ->addMoreInfo('quantifierMax', $quantifierMax);
        }

        $this->isDisjunctive = $isDisjunctive;
        $this->nodes = $nodes;
        $this->quantifierMin = $quantifierMin;
        $this->quantifierMax = $quantifierMax;
    }

    public function isDisjunctive(): bool
    {
        return $this->isDisjunctive;
    }

    /**
     * @return list<string|self>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function hasQuantifier(): bool
    {
        return $this->quantifierMin !== 1 || $this->quantifierMax !== 1;
    }

    /**
     * @return array{int, int}
     */
    public function getQuantifier(): array
    {
        return [$this->quantifierMin, $this->quantifierMax];
    }
}
