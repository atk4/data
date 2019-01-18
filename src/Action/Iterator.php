<?php

namespace atk4\data\Action;

use Guzzle\Iterator\FilterIterator;

/**
 * Class Array_ is returned by $model->action(). Compatible with DSQL to a certain point as it implements
 * specific actions such as getOne() or get().
 */
class Iterator
{
    /**
     * @var \ArrayIterator
     */
    public $generator;

    /**
     * Iterator constructor.
     *
     * @param $generator
     */
    public function __construct(array $generator)
    {
        $this->generator = new \ArrayIterator($generator);
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value.
     *
     * @param $field
     * @param $value
     *
     * @return $this
     */
    public function where($field, $value)
    {
        $this->generator = new \CallbackFilterIterator($this->generator, function ($row) use ($field, $value) {

            // skip row. does not have field at all
            if (!isset($row[$field])) {
                return false;
            }

            // has row and it matches
            if ($row[$field] == $value) {
                return true;
            }

            return false;
        });

        return $this;
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     *
     * @return $this
     */
    public function count()
    {
        $this->generator = new \ArrayIterator([[iterator_count($this->generator)]]);

        return $this;
    }

    /**
     * Return all data inside array.
     *
     * @return array
     */
    public function get()
    {
        return iterator_to_array($this->generator, true);
    }

    /**
     * Return one row of data.
     *
     * @return array
     */
    public function getRow()
    {
        $row = $this->generator->current();
        $this->generator->next();

        return $row;
    }

    /**
     * Return one value from one row of data.
     *
     * @return mixed
     */
    public function getOne()
    {
        $data = $this->getRow();

        return array_shift($data);
    }
}
