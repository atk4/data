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
    /** @var array<mixed> The seed used by addReference() method. */
    protected array $_defaultSeedAddReference = [Reference::class];

    /** @var array<mixed> The seed used by hasOne() method. */
    protected array $_defaultSeedHasOne = [Reference\HasOne::class];

    /** @var array<mixed> The seed used by hasMany() method. */
    protected array $_defaultSeedHasMany = [Reference\HasMany::class];

    /** @var array<mixed> The seed used by containsOne() method. */
    protected array $_defaultSeedContainsOne = [Reference\ContainsOne::class];

    /** @var array<mixed> The seed used by containsMany() method. */
    protected array $_defaultSeedContainsMany = [Reference\ContainsMany::class];

    /**
     * @param array<mixed>         $seed
     * @param array<string, mixed> $defaults
     */
    protected function _addReference(array $seed, string $link, array $defaults): Reference
    {
        $this->assertIsModel();

        $defaults[0] = $link;

        $reference = Reference::fromSeed($seed, $defaults);

        $name = $reference->getDesiredName();
        if ($this->hasElement($name)) {
            throw (new Exception('Reference with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('link', $link);
        }

        $this->add($reference);

        return $reference;
    }

    /**
     * Add generic relation. Provide your own call-back that will return the model.
     *
     * @param array<string, mixed> $defaults
     */
    public function addReference(string $link, array $defaults): Reference
    {
        return $this->_addReference($this->_defaultSeedAddReference, $link, $defaults);
    }

    /**
     * Add hasOne reference.
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\HasOne|Reference\HasOneSql
     */
    public function hasOne(string $link, array $defaults): Reference
    {
        return $this->_addReference($this->_defaultSeedHasOne, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add hasMany reference.
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\HasMany
     */
    public function hasMany(string $link, array $defaults): Reference
    {
        return $this->_addReference($this->_defaultSeedHasMany, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsOne reference.
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\ContainsOne
     */
    public function containsOne(string $link, array $defaults): Reference
    {
        return $this->_addReference($this->_defaultSeedContainsOne, $link, $defaults); // @phpstan-ignore-line
    }

    /**
     * Add containsMany reference.
     *
     * @param array<string, mixed> $defaults
     *
     * @return Reference\ContainsMany
     */
    public function containsMany(string $link, array $defaults): Reference
    {
        return $this->_addReference($this->_defaultSeedContainsMany, $link, $defaults); // @phpstan-ignore-line
    }

    public function hasReference(string $link): bool
    {
        return $this->getModel(true)->hasElement('#ref-' . $link);
    }

    public function getReference(string $link): Reference
    {
        $this->assertIsModel();

        return $this->getElement('#ref-' . $link);
    }

    /**
     * @return array<string, Reference>
     */
    public function getReferences(): array
    {
        $this->assertIsModel();

        $res = [];
        foreach ($this->elements as $k => $v) {
            if (str_starts_with($k, '#ref-')) {
                $link = substr($k, strlen('#ref-'));
                $res[$link] = $this->getReference($link);
            } elseif ($v instanceof Reference) {
                throw new \Error('Unexpected Reference index');
            }
        }

        return $res;
    }

    /**
     * Traverse reference and create their model.
     *
     * @param array<string, mixed> $defaults
     */
    public function ref(string $link, array $defaults = []): Model
    {
        $reference = $this->getModel(true)->getReference($link);

        return $reference->ref($this, $defaults);
    }

    /**
     * Traverse reference and create their model but keep reference condition not materialized (for subquery actions).
     *
     * @param array<string, mixed> $defaults
     */
    public function refLink(string $link, array $defaults = []): Model
    {
        $reference = $this->getReference($link);

        return $reference->refLink($defaults);
    }
}
