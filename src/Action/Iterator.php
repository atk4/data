<?php


namespace atk4\data\Action;


use Guzzle\Iterator\FilterIterator;

/**
 * Class Array_ is returned by $model->action(). Compatible with DSQL to a certain point as it implements
 * specific actions such as getOne() or get().
 *
 * @package atk4\data\Action
 */
class Iterator
{
    /**
     * @var array
     */
    public $generator;

    /**
     * Array_ constructor.
     *
     * @param $generator
     */
    function __construct(array $generator)
    {
        $this->generator = new \ArrayIterator($generator);
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value
     *
     * @param $field
     * @param $value
     *
     * @return $this
     */
    function where($field, $value) {
        $this->generator = new \CallbackFilterIterator($this->generator, function($row) use ($field, $value) {

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
     * Counts number of rows and replaces our generator with just a single number
     *
     * @return $this
     */
    function count()
    {
        $this->generator = new \ArrayIterator([[iterator_count($this->generator)]]);

        return $this;
    }


    /**
     * @return array get all data inside array
     */
    function get()
    {
        return iterator_to_array($this->generator, true);
    }

    function getRow(){

        $row = $this->generator->current();
        $this->generator->next();

        return $row;
    }

    function getOne(){
        $data = $this->getRow();
        return array_shift($data);
    }
}