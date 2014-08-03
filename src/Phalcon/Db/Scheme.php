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

    public function getScheme()
    {

        $connection = $this->getConnection();

        $tables = array();

        $tablesList = $connection->listTables();

        foreach ($tablesList as $table) {

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