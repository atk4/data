<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Implements persistence driver that can save data and load from CSV file.
 * This basic driver only offers the load/save. It does not offer conditions or
 * id-specific operations. You can only use a single persistence object with
 * a single file.
 *
 * $p = new Persistence\CSV('file.csv');
 * $m = new MyModel($p);
 * $data = $m->export();
 *
 * Alternatively you can write into a file. First operation you perform on
 * the persistence will determine the mode.
 *
 * $p = new Persistence\CSV('file.csv');
 * $m = new MyModel($p);
 * $m->import($data);
 */
class CSV extends Persistence
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
     * Mode of operation. 'r' for reading and 'w' for writing.
     * If you manually set this operation, it will be used
     * for file opening.
     *
     * @var string
     */
    public $mode = null;

    /**
     * Delimiter in CSV file.
     *
     * @var string
     */
    public $delimiter = ',';

    /**
     * Enclosure in CSV file.
     *
     * @var string
     */
    public $enclosure = '"';

    /**
     * Escape character in CSV file.
     *
     * @var string
     */
    public $escape_char = '\\';

    /**
     * Array of field names.
     *
     * @var array
     */
    public $header = [];

    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array &$data
     * @param array $defaults
     */
    public function __construct($file, $defaults = [])
    {
        $this->file = $file;
        $this->setDefaults($defaults);
    }

    /**
     * Destructor. close files correctly.
     */
    public function __destruct()
    {
        $this->closeFile();
    }

    /**
     * Open CSV file.
     *
     * Override this method and open handle yourself if you want to
     * reposition or load some extra columns on the top.
     *
     * @param string $mode 'r' or 'w'
     */
    public function openFile($mode = 'r')
    {
        if (!$this->handle) {
            $this->handle = fopen($this->file, $mode);
            if ($this->handle === false) {
                throw new Exception(['Can not open CSV file.', 'file' => $this->file, 'mode' => $mode]);
            }
        }
    }

    /**
     * Close CSV file.
     */
    public function closeFile()
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /**
     * Returns one line of CSV file as array.
     *
     * @return array
     */
    public function getLine()
    {
        $data = fgetcsv($this->handle, 0, $this->delimiter, $this->enclosure, $this->escape_char);
        if ($data) {
            $this->line++;
        }

        return $data;
    }

    /**
     * Writes array as one record to CSV file.
     *
     * @param array
     */
    public function putLine($data)
    {
        $ok = fputcsv($this->handle, $data, $this->delimiter, $this->enclosure, $this->escape_char);
        if ($ok === false) {
            throw new Exception(['Can not write to CSV file.']);
        }
    }

    /**
     * When load operation starts, this will open file and read
     * the first line. This line is then used to identify columns.
     */
    public function loadHeader()
    {
        $this->openFile('r');

        $header = $this->getLine();
        $this->line--; // because we don't want to count header line

        $this->initializeHeader($header);
    }

    /**
     * When load operation starts, this will open file and read
     * the first line. This line is then used to identify columns.
     *
     * @param Model $m
     */
    public function saveHeader(Model $m)
    {
        $this->openFile('w');

        $header = [];
        foreach ($m->getFields() as $name=>$field) {
            if ($name == $m->id_field) {
                continue;
            }

            $header[] = $name;
        }

        $this->putLine($header);

        $this->initializeHeader($header);
    }

    /**
     * Remembers $this->header so that the data can be
     * easier mapped.
     *
     * @param array
     */
    public function initializeHeader($header)
    {
        // removes forbidden symbols from header (field names)
        $this->header = array_map(function ($name) {
            return preg_replace('/[^a-z0-9_-]+/i', '_', $name);
        }, $header);
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
        $id = null;
        if (isset($row[$m->id_field])) {
            // temporary remove id field
            $id = $row[$m->id_field];
            unset($row[$m->id_field]);
        } else {
            $id = null;
        }
        $row = array_combine($this->header, $row);
        if (isset($id)) {
            $row[$m->id_field] = $id;
        }

        foreach ($row as $key => &$value) {
            if ($value === null) {
                continue;
            }

            if ($f = $m->hasField($key)) {
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

        $data = $this->getLine();
        if (!$data) {
            return;
        }

        $data = $this->typecastLoadRow($m, $data);
        $data['id'] = $this->line;

        return $data;
    }

    /**
     * Prepare iterator.
     *
     * @param Model $m
     *
     * @return array
     */
    public function prepareIterator(Model $m)
    {
        if (!$this->mode) {
            $this->mode = 'r';
        } elseif ($this->mode == 'w') {
            throw new Exception(['Currently writing records, so loading is not possible.']);
        }

        if (!$this->handle) {
            $this->loadHeader();
        }

        while (true) {
            $data = $this->getLine();
            if (!$data) {
                break;
            }
            $data = $this->typecastLoadRow($m, $data);
            $data[$m->id_field] = $this->line;

            yield $data;
        }
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
                'model' => $m,
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
            $this->saveHeader($m);
        }

        $line = [];

        foreach ($this->header as $name) {
            $line[] = $data[$name];
        }

        $this->putLine($line);
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param array  $data
     * @param string $table
     */
    public function update(Model $m, $id, $data, $table = null)
    {
        throw new Exception(['Updating records is not supported in CSV persistence.']);
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
        throw new Exception(['Deleting records is not supported in CSV persistence.']);
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

        $type = $m->getField($m->id_field)->type;

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

    /**
     * Export all DataSet.
     *
     * @param Model      $m
     * @param array|null $fields
     *
     * @return array
     */
    public function export(Model $m, $fields = null)
    {
        $data = [];

        foreach ($m as $junk) {
            $data[] = $fields ? array_intersect_key($m->get(), array_flip($fields)) : $m->get();
        }

        // need to close file otherwise file pointer is at the end of file
        $this->closeFile();

        return $data;
    }
}
