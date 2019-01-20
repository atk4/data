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
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->generator = new \ArrayIterator($data);
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value.
     *
     * @param string $field
     * @param string $value
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
     * Applies sorting on Iterator.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function order($fields)
    {
        $data = $this->get();

        // prepare arguments for array_multisort()
        $args = [];
        foreach ($fields as list($field, $desc)) {
            $args[] = array_column($data, $field);
            $args[] = $desc ? SORT_DESC : SORT_ASC;
            //$args[] = SORT_STRING; // SORT_STRING | SORT_NUMERIC | SORT_REGULAR
        }
        $args[] = &$data;

        // call sorting
        call_user_func_array('array_multisort', $args);

        // put data back in generator
        $this->generator = new \ArrayIterator(array_pop($args));

        return $this;
    }

    /**
     * Limit Iterator.
     *
     * @param int $cnt
     * @param int $shift
     *
     * @return $this
     */
    public function limit($cnt, $shift = 0)
    {
        $data = array_slice($this->get(), $shift, $cnt, true);

        // put data back in generator
        $this->generator = new \ArrayIterator($data);

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
