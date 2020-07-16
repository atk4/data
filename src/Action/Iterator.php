<?php

declare(strict_types=1);

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
            if (!array_key_exists($field, $row)) {
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
     * Applies FilterIterator condition imitating the sql LIKE operator - $field LIKE %$value% | $value% | %$value.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function like($field, $value)
    {
        $this->generator = new \CallbackFilterIterator($this->generator, function ($row) use ($field, $value) {
            // skip row. does not have field at all
            if (!array_key_exists($field, $row)) {
                return false;
            }

            $fieldValStr = (string) $row[$field];

            $value = trim($value);
            $clean_value = trim($value, '%');
            // the row field exists check the position of the "%"(s)
            switch ($value) {
                // case "%str%"
                case substr($value, -1, 1) === '%' && substr($value, 0, 1) === '%':
                    return strpos($fieldValStr, $clean_value) !== false;

                    break;
                // case "str%"
                case substr($value, -1, 1) === '%':
                    return substr($fieldValStr, 0, strlen($clean_value)) === $clean_value;

                    break;
                // case "%str"
                case substr($value, 0, 1) === '%':
                    return substr($fieldValStr, -strlen($clean_value)) === $clean_value;

                    break;
                // full match
                default:
                    return $fieldValStr == $clean_value;
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
        foreach ($fields as [$field, $desc]) {
            $args[] = array_column($data, $field);
            $args[] = $desc ? SORT_DESC : SORT_ASC;
            //$args[] = SORT_STRING; // SORT_STRING | SORT_NUMERIC | SORT_REGULAR
        }
        $args[] = &$data;

        // call sorting
        array_multisort(...$args);

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
     */
    public function get(): array
    {
        return iterator_to_array($this->generator, true);
    }

    /**
     * Return one row of data.
     */
    public function getRow(): ?array
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

        return $data === null ? null : reset($data);
    }
}
