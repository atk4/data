<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;

/**
 * Provides native Model methods for join functionality.
 */
trait JoinsTrait
{
    /**
     * The class used by join() method.
     *
     * @var array
     */
    public $_default_seed_join = [Join::class];

    /**
     * @param array<string, mixed> $defaults
     *
     * @deprecated to be removed in v3.3
     */
    public function join(string $foreignTable, array $defaults = []): Join
    {
        'trigger_error'('Method is deprecated. Use addJoin() instead', \E_USER_DEPRECATED);

        return $this->addJoin($foreignTable, $defaults);
    }

    /**
     * @param array<string, mixed> $defaults
     *
     * @deprecated to be removed in v3.3
     */
    public function leftJoin(string $foreignTable, array $defaults = []): Join
    {
        'trigger_error'('Method is deprecated. Use addLeftJoin() instead', \E_USER_DEPRECATED);

        return $this->addLeftJoin($foreignTable, $defaults);
    }

    /**
     * Creates an objects that describes relationship between multiple tables (or collections).
     *
     * When object is loaded, then instead of pulling all the data from a single table,
     * join will also query $foreignTable in order to find additional fields. When inserting
     * the record will be also added inside $foreignTable and relationship will be maintained.
     *
     * @param array<string, mixed> $defaults
     */
    public function addJoin(string $foreignTable, array $defaults = []): Join
    {
        $this->assertIsModel();

        $defaults[0] = $foreignTable;

        $join = Join::fromSeed($this->_default_seed_join, $defaults);

        if ($this->hasElement($name = $join->getDesiredName())) {
            throw (new Exception('Join with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('foreignTable', $foreignTable);
        }

        return $this->add($join);
    }

    /**
     * Add left/weak join.
     *
     * @see join()
     *
     * @param array<string, mixed> $defaults
     */
    public function addLeftJoin(string $foreignTable, array $defaults = []): Join
    {
        $defaults['weak'] = true;

        return $this->addJoin($foreignTable, $defaults);
    }
}
