<?php

namespace Sb\Phalcon\Db;

use Sb\Utils as SbUtils;

class Scheme
{
    private $di = null;

    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Phalcon\Db\Adapter\Pdo\Mysql
     */
    public function getConnection()
    {
        return $this->di->getDb();
    }

    public function __construct($di)
    {
        $this->setDi($di);
    }

    public function getScheme($options = array())
    {
        $connection = $this->getConnection();

        $ignoreTables = array();
        if (array_key_exists('ignore', $options) && is_array($options['ignore'])) {
            $ignoreTables = $options['ignore'];
        }

        $tables = array();

        $tablesList = $connection->listTables();

        foreach ($tablesList as $table) {

            if (in_array($table, $ignoreTables)) {
                continue;
            }

            $columns = array();
            $primary = array();
            $columnsList = $connection->describeColumns($table);

            foreach ($columnsList as $column) {
                $columns[] = $column->getName();
                if ($column->isPrimary()) {
                    $primary[] = $column->getName();
                }
            }

            $tables[$table] = array(
                'name' => $table,
                'model' => SbUtils::wordUnderscoreToCamelCase($table),
                'columns' => $columns,
                'primary' => $primary
            );
        }

        foreach ($tables as $table => &$tableFields) {
            $refs = $connection->describeReferences($table);
            $indexes = $connection->describeIndexes($table);

            foreach ($indexes as $index) {
                $tableFields['indexes'][] = [
                    $index->getName(),
                    $index->getColumns(),
                    $index->getType(),
                ];
            }

            foreach ($refs as $ref) {
                $referencedTable = $ref->getReferencedTable();
                $columns = $ref->getColumns();
                $referencedColumns = $ref->getReferencedColumns();

                $firstReferencedColumn = reset($referencedColumns);
                $firstColumn = reset($columns);

                if (in_array($firstColumn, $tableFields['primary']) && in_array($firstReferencedColumn, $tables[$referencedTable]['primary'])) {

                    $tableFields['ref_one_to_one'][] = array(
                        'column' => $firstColumn,
                        'model' => $tables[$referencedTable]['model'],
                        'ref_column' => $firstReferencedColumn
                    );

                    $tables[$referencedTable]['ref_one_to_one'][] = array(
                        'column' => $firstReferencedColumn,
                        'model' => $tableFields['model'],
                        'ref_column' => $firstColumn
                    );

                } else {

                    $tableFields['ref_many_to_one'][] = array(
                        'column' => $firstColumn,
                        'model' => $tables[$referencedTable]['model'],
                        'ref_column' => $firstReferencedColumn
                    );

                    $tables[$referencedTable]['ref_one_to_many'][] = array(
                        'column' => $firstReferencedColumn,
                        'model' => $tableFields['model'],
                        'ref_column' => $firstColumn
                    );

                }

            }
        }

        return $tables;
    }

} 