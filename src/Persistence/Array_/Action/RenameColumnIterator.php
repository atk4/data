<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Action;

use Atk4\Data\Exception;

/**
 * @internal
 *
 * @phpstan-extends \IteratorIterator<int, array<string, mixed>, \Traversable<array<string, mixed>>>
 */
final class RenameColumnIterator extends \IteratorIterator
{
    /** @var string */
    protected $origName;
    /** @var string */
    protected $newName;

    /**
     * @param \Traversable<array<string, mixed>> $iterator
     */
    public function __construct(\Traversable $iterator, string $origName, string $newName)
    {
        parent::__construct($iterator);

        $this->origName = $origName;
        $this->newName = $newName;
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $row = parent::current();

        $keys = array_keys($row);
        $index = array_search($this->origName, $keys, true);
        if ($index === false) {
            throw (new Exception('Column not found'))
                ->addMoreInfo('orig_name', $this->origName);
        }
        $keys[$index] = $this->newName;

        return array_combine($keys, $row);
    }
}
