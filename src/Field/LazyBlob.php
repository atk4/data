<?php

namespace atk4\data\Field;

use atk4\core\InitializerTrait;
use atk4\data\BlobValue;
use atk4\data\ValidationException;

/**
 * Lazy BLOB (binary string) field type. Supported by SQL persistence only.
 */
class LazyBlob extends Callback
{
    use InitializerTrait {
        init as _init;
    }

    /* @var bool */
    protected $cacheOnLoad = false;

//    /**
//     * Expressions are always read_only.
//     *
//     * @var bool
//     */
//    public $read_only = true;
//
//    /**
//     * Never persist this field.
//     *
//     * @var bool
//     */
//    public $never_persist = true;

    public function init(): void
    {
        $this->_init();

        $this->ui['table']['sortable'] = false;

        $this->owner->onHook('afterLoad', function ($m) {
            $m->data[$this->short_name] = $this->createBlobValue($m);
        });
    }

    protected function createBlobValue(Model $model, string $id, string $size): BlobValue {

    }


    /**
     * Normalize value to boolean value.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return bool
     */
    public function normalize($value)
    {
        if (is_null($value) || $value === '') {
            return;
        }
        if (is_bool($value)) {
            return $value;
        }

        if ($value === $this->valueTrue) {
            return true;
        }

        if ($value === $this->valueFalse) {
            return false;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        throw new ValidationException([$this->name => 'Must be a boolean value']);
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     */
    public function toString($value = null): string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v === true ? '1' : '0';
    }

    /**
     * Validate if value is allowed for this field.
     *
     * @param mixed $value
     */
    public function validate($value)
    {
        // if value required, then only valueTrue is allowed
        if ($this->required && $value !== $this->valueTrue) {
            throw new ValidationException([$this->name => 'Must be selected']);
        }
    }
}
