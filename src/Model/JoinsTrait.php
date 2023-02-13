<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;

/**
 * Provides native Model methods for join functionality.
 */
trait JoinsTrait
{
    /** @var array<mixed> The class used by join() method. */
    protected $_defaultSeedJoin = [Join::class];

    /**
     * Creates an objects that describes relationship between multiple tables (or collections).
     *
     * When object is loaded, then instead of pulling all the data from a single table,
     * join will also query $foreignTable in order to find additional fields. When inserting
     * the record will be also added inside $foreignTable and relationship will be maintained.
     *
     * @param array<string, mixed> $defaults
     */
    public function join(string $foreignTable, array $defaults = []): Join
    {
        $this->assertIsModel();

        $defaults[0] = $foreignTable;

        $join = Join::fromSeed($this->_defaultSeedJoin, $defaults);

        $name = $join->getDesiredName();
        if ($this->hasElement($name)) {
            throw (new Exception('Join with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('foreignTable', $foreignTable);
        }

        $this->add($join);

        return $join;
    }

    /**
     * Add left/weak join.
     *
     * @param array<string, mixed> $defaults
     */
    public function leftJoin(string $foreignTable, array $defaults = []): Join
    {
        $defaults['weak'] = true;

        return $this->join($foreignTable, $defaults);
    }

    public function hasJoin(string $link): bool
    {
        return $this->getModel(true)->hasElement('#join-' . $link);
    }

    public function getJoin(string $link): Join
    {
        $this->assertIsModel();

        return $this->getElement('#join-' . $link);
    }

    /**
     * @return array<string, Join>
     */
    public function getJoins(): array
    {
        $this->assertIsModel();

        $res = [];
        foreach ($this->elements as $k => $v) {
            if (str_starts_with($k, '#join-')) {
                $link = substr($k, strlen('#join-'));
                $res[$link] = $this->getJoin($link);
            } elseif ($v instanceof Join) {
                throw new \Error('Unexpected Join index');
            }
        }

        return $res;
    }
}
