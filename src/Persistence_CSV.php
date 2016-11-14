<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Implements persistence driver that can save data and load from CSV file.
 * This basic driver only offers the load/save. It does not offer conditions or
 * id-specific operations. You can only use a single persistence object with
 * a single file.
 *
 * $p = new Persistence_CSV('file.csv');
 * $m = new MyModel($p);
 * $data = $m->export();
 *
 * Alternatively you can write into a file. First operation you perform on
 * the persistence will determine the mode.
 *
 * $p = new Persistence_CSV('file.csv');
 * $m = new MyModel($p);
 * $m->import($data);
 */
class Persistence_CSV extends Persistence
{
    /**
     * Name of the file.
     *
     * @var string
     */
    public $file;

    /**
     * Line in CSV file.
     *
     * @var int
     */
    public $line = 0;

    /**
     * File handle, when the $file is opened.
     *
     * @var resource
     */
    public $handle = null;

    /**
     * Mode of opeation. 'r' for reading and 'w' for writing.
     * If you manually set this operation, it will be used
     * for file opening.
     *
     * @var string
     */
    public $mode = null;

    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array &$data
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Returns one line of CSV file as array.
     *
     * @return array
     */
    public function getLine()
    {
        $this->line++;

        return fgetcsv($this->handle);
    }

    /**
     * When load operation starts, this will open file and read
     * the first line. This line is then used to identify columns.
     */
    public function loadHeader()
    {
        // Override this method and open handle yourself if you want to
        // reposition or load some extra columns on the top.
        if (!$this->handle) {
            $this->handle = fopen($this->file, 'r');
        }

        $header = $this->getLine();

        $this->initializeHeader($header);
    }

    /**
     * Remembers $this->header so that the data can be
     * easier mapped.
     */
    public function initializeHeader($header)
    {
        $this->header = $header;
        $this->header_reverse = array_flip($header);
    }

    /**
     * Typecasting when load data row.
     *
     * @param Model $m
     * @param array $row
     *
     * @return array
     */
    public function typecastLoadRow(Model $m, $row)
    {
        $row = array_combine($this->header, $row);

        foreach ($row as $key => &$value) {
            if ($value === null) {
                continue;
            }

            if ($f = $m->hasElement($key)) {
                $value = $this->typecastLoadField($f, $value);
            }
        }

        return $row;
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param Model $m
     *
     * @return array|null
     */
    public function tryLoadAny(Model $m)
    {
        if (!$this->mode) {
            $this->mode = 'r';
        } elseif ($this->mode == 'w') {
            throw new Exception(['Currently writing records, so loading is not possible.']);
        }

        if (!$this->handle) {
            $this->loadHeader();
        }

        $data = fgetcsv($this->handle);
        if (!$data) {
            return;
        }

        $data = $this->typecastLoadRow($m, $data);
        $data['id'] = $this->line;

        return $data;
    }

    /**
     * Loads any one record.
     *
     * @param Model $m
     *
     * @return array
     */
    public function loadAny(Model $m)
    {
        $data = $this->tryLoadAny($m);

        if (!$data) {
            throw new Exception([
                'No more records',
                'model'      => $m,
            ], 404);
        }

        return $data;
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @param Model  $m
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function insert(Model $m, $data)
    {
        if (!$this->mode) {
            $this->mode = 'w';
        } elseif ($this->mode == 'r') {
            throw new Exception(['Currently reading records, so writing is not possible.']);
        }

        if (!$this->handle) {
            $this->saveHeader();
        }

        $id = $this->generateNewID($m, $table);
        $data[$m->id_field] = $id;
        $this->data[$table][$id] = $data;

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function update(Model $m, $id, $data, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $this->data[$table][$id] =
            array_merge(
                isset($this->data[$table][$id]) ? $this->data[$table][$id] : [],
                $data
            );

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     */
    public function delete(Model $m, $id, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        unset($this->data[$table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @param Model  $m
     * @param string $table
     *
     * @return string
     */
    public function generateNewID($m, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $ids = array_keys($this->data[$table]);

        $type = $m->getElement($m->id_field)->type;

        switch ($type) {
            case 'integer':
                return count($ids) === 0 ? 1 : (max($ids) + 1);
            case 'string':
                return uniqid();
            default:
                throw new Exception([
                    'Unsupported id field type. Array supports type=integer or type=string only',
                    'type' => $type,
                ]);
        }
    }
}
