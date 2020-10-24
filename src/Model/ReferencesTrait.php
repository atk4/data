<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\data\Exception;
use atk4\data\Reference;

/**
 * Provides native Model methods for manipulating model references.
 */
trait ReferencesTrait
{
    /**
     * The seed used by addRef() method.
     *
     * @var string|array
     */
    public $_default_seed_addRef = [Reference::class];

    /**
     * The seed used by hasOne() method.
     *
     * @var string|array
     */
    public $_default_seed_hasOne = [Reference\HasOne::class];

    /**
     * The seed used by hasMany() method.
     *
     * @var string|array
     */
    public $_default_seed_hasMany = [Reference\HasMany::class];

    /**
     * The seed used by containsOne() method.
     *
     * @var string|array
     */
    public $_default_seed_containsOne = [Reference\ContainsOne::class];

    /**
     * The seed used by containsMany() method.
     *
     * @var string
     */
    public $_default_seed_containsMany = [Reference\ContainsMany::class];

    /**
     * @param object|array   $className Either an object or an array as seed
     * @param string         $link
     * @param array|\Closure $defaults  Properties which we will pass to Reference object constructor
     */
    protected function _hasReference($className, $link, $defaults = []): Reference
    {
        if (!is_array($defaults)) {
            $defaults = ['model' => $defaults ?: 'Model_' . $link];
        } elseif (isset($defaults[0])) {
            $defaults['model'] = $defaults[0];
            unset($defaults[0]);
        }

        $defaults[0] = $link;

        $reference = Reference::fromSeed($className, $defaults);

        // if reference with such name already exists, then throw exception
        if ($this->hasElement($name = $reference->getDesiredName())) {
            throw (new Exception('Reference with such name already exists'))
                ->addMoreInfo('name', $name)
                ->addMoreInfo('link', $link)
                ->addMoreInfo('defaults', $defaults);
        }

        return $this->add($reference);
    }

    /**
     * Add generic relation. Provide your own call-back that will return the model.
     *
     * @param string         $link Link
     * @param array|\Closure $fx   Callback
     */
    public function addRef($link, $fx): Reference
    {
        return $this->_hasReference($this->_default_seed_addRef, $link, $fx);
    }

    /**
     * Add hasOne reference.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\HasOne
     */
    public function hasOne($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_hasOne, $link, $defaults);
    }

    /**
     * Add hasMany reference.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\HasMany
     */
    public function hasMany($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_hasMany, $link, $defaults);
    }

    /**
     * Add containsOne reference.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\ContainsOne
     */
    public function containsOne($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_containsOne, $link, $defaults);
    }

    /**
     * Add containsMany reference.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return Reference\ContainsMany
     */
    public function containsMany($link, $defaults = []): Reference
    {
        return $this->_hasReference($this->_default_seed_containsMany, $link, $defaults);
    }

    /**
     * Traverse to related model.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return \atk4\data\Model
     */
    public function ref($link, $defaults = []): self
    {
        return $this->getRef($link)->ref($defaults);
    }

    /**
     * Return related model.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return \atk4\data\Model
     */
    public function refModel($link, $defaults = []): self
    {
        return $this->getRef($link)->refModel($defaults);
    }

    /**
     * Returns model that can be used for generating sub-query actions.
     *
     * @param string $link
     * @param array  $defaults
     *
     * @return \atk4\data\Model
     */
    public function refLink($link, $defaults = []): self
    {
        return $this->getRef($link)->refLink($defaults);
    }

    /**
     * Returns the reference.
     *
     * @param string $link
     */
    public function getRef($link): Reference
    {
        return $this->getElement('#ref_' . $link);
    }

    /**
     * Returns all references.
     */
    public function getRefs(): array
    {
        $refs = [];
        foreach ($this->elements as $key => $val) {
            if (substr($key, 0, 5) === '#ref_') {
                $refs[substr($key, 5)] = $val;
            }
        }

        return $refs;
    }

    /**
     * Returns true if reference exists.
     *
     * @param string $link
     */
    public function hasRef($link): bool
    {
        return $this->hasElement('#ref_' . $link);
    }
}
