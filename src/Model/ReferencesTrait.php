<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference;

/**
 * Provides native Model methods for manipulating model references.
 */
trait ReferencesTrait
{
    /** @var array The seed used by addRef() method. */
    protected $_defaultSeedAddRef = [Reference::class];

    /** @var array The seed used by hasOne() method. */
    protected $_defaultSeedHasOne = [Reference\HasOne::class];

    /** @var array The seed used by hasMany() method. */
    protected $_defaultSeedHasMany = [Reference\HasMany::class];

    /** @var array The seed used by containsOne() method. */
    protected $_defaultSeedContainsOne = [Reference\ContainsOne::class];

    /** @var array The seed used by containsMany() method. */
    protected $_defaultSeedContainsMany = [Reference\ContainsMany::class];

    /**
     * @param array<string, mixed> $defaults Properties which we will pass to Reference object constructor
     */
    protected function _addRef(array $seed, string $link, array $defaults = []): Reference
    {
        $this->assertIsModel();

        $defaults[0] = $link;

        $reference = Reference::fromSeed($seed, $defaults);

        if ($this->hasElement($name = $reference->getDesiredName())) {
            throw (new Exception('Reference with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('link', $link);
        }

        $this->add($reference);

        return $reference;
    }

    /**
     * Add generic relation. Provide your own call-back that will return the model.
     */
    public function addRef(string $link, array $defaults): Reference
    {
        return $this->_addRef($this->_defaultSeedAddRef, $link, $defaults);
    }

    /**
     * Add hasOne reference.
     *
     * @return Reference\HasOne|Reference\HasOneSql
     */
    public function hasOne(string $link, array $defaults = []) // : Reference
    {
        return $this->_addRef($this->_defaultSeedHasOne, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add hasMany reference.
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $link, array $defaults = []) // : Reference
    {
        return $this->_addRef($this->_defaultSeedHasMany, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsOne reference.
     *
     * @return Reference\ContainsOne
     */
    public function containsOne(string $link, array $defaults = []) // : Reference
    {
        return $this->_addRef($this->_defaultSeedContainsOne, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsMany reference.
     *
     * @return Reference\ContainsMany
     */
    public function containsMany(string $link, array $defaults = []) // : Reference
    {
        return $this->_addRef($this->_defaultSeedContainsMany, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Returns true if reference exists.
     */
    public function hasRef(string $link): bool
    {
        return $this->getModel(true)->hasElement('#ref_' . $link);
    }

    /**
     * Returns the reference.
     */
    public function getRef(string $link): Reference
    {
        $this->assertIsModel();

        return $this->getElement('#ref_' . $link);
    }

    /**
     * Returns all references.
     *
     * @return array<string, Reference>
     */
    public function getRefs(): array
    {
        $this->assertIsModel();

        $refs = [];
        foreach (array_keys($this->elements) as $k) {
            if (str_starts_with($k, '#ref_')) {
                $link = substr($k, strlen('#ref_'));
                $refs[$link] = $this->getRef($link);
            }
        }

        return $refs;
    }

    /**
     * Traverse to related model.
     */
    public function ref(string $link, array $defaults = []): Model
    {
        return $this->getModel(true)->getRef($link)->ref($this, $defaults);
    }

    /**
     * Return related model.
     */
    public function refModel(string $link, array $defaults = []): Model
    {
        return $this->getModel(true)->getRef($link)->refModel($this, $defaults);
    }

    /**
     * Returns model that can be used for generating sub-query actions.
     */
    public function refLink(string $link, array $defaults = []): Model
    {
        return $this->getModel(true)->getRef($link)->refLink($this, $defaults);
    }
}
