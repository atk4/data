<?php

namespace atk4\data;

/**
 * Lazy BLOB (binary string) value.
 */
class BlobValue
{
    /* @var Field */
    protected $persistField;

    /* @var int -1 for null value */
    protected $size;

    /* @var string|null */
    protected $value;

    /* @var bool */
    protected $cacheOnLoad;

    public function __construct(Field $persistField, int $size, bool $cacheOnLoad = false)
    {
        $this->persistField = $persistField;
        $this->size = $size;
        $this->cacheOnLoad = $cacheOnLoad;
    }

    /**
     * @return int -1 for null value
     */
    public function getSize(): int
    {
        return $this->size;
    }

    public function getValue(): string
    {
        if ($this->value === null) {
            $this->value = $this->loadMulti([$this])[0];
        }

        return $this->value;
    }

    /**
     * @param self[] $blobValues
     */
    protected function loadMulti(array $blobValues): array
    {
        if ($this->getSize() < 0) {
            throw new Exception('BLOB value is NULL');
        }
        // we can load at once probably only iff we use the same model
        elseif ($this->getSize() === 0) {
            return '';
        }

        //   $cacheOnLoad

        $res = $this->persistField->getDSQLExpression(new \atk4\dsql\Expression('[]'))->get(); // and validate iff one row was returned
    }
}
