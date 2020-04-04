<?php

namespace Yerofey\Replicator;

/**
 * Replicator
 */
class Replicator
{
    private $connections;
    private $debug = false;
    private $helper;
    private $log_file = '';

    /**
     * Replicator constructor
     *
     * @param array $params
     */
    public function __construct(array $connections, ReplicatorHelper $helper, bool $debug = false, string $log_file = '')
    {
        $this->connections = $connections;
        $this->helper = $helper;
        $this->debug = $debug;
        $this->log_file = $log_file;
    }

    /**
     * @param array $primary_array
     * @param array $secondary_array
     * @param boolean $check_keys_order
     * @return array
     */
    public function findDifferentValues(array $primary_array, array $secondary_array, bool $check_keys_order = false): array
    {
        $changed_columns = $primary_array;
        if ($check_keys_order) {
            $i = 0;
            foreach ($primary_array as $column_name => $column_data) {
                if (isset($secondary_array[$column_name])) {
                    if ($secondary_array[$column_name] === $column_data) {
                        $secondary_array_column_index = array_search($column_name, array_keys($secondary_array));

                        if ($i == $secondary_array_column_index) {
                            unset($changed_columns[$column_name]);
                        }
                    }
                } else {
                    unset($changed_columns[$column_name]);
                }

                $i++;
            }
        } else {
            foreach ($primary_array as $column_name => $column_data) {
                if (isset($secondary_array[$column_name])) {
                    if ($secondary_array[$column_name] == $column_data) {
                        unset($changed_columns[$column_name]);
                    }
                } else {
                    unset($changed_columns[$column_name]);
                }
            }
        }

        $missing_columns = $primary_array;
        foreach ($primary_array as $column_name => $column_data) {
            if (isset($secondary_array[$column_name])) {
                unset($missing_columns[$column_name]);
            }
        }

        $removed_columns = $secondary_array;
        foreach ($primary_array as $column_name => $column_data) {
            if (isset($removed_columns[$column_name])) {
                unset($removed_columns[$column_name]);
            }
        }

        return [
            'changed' => $changed_columns,
            'missing' => $missing_columns,
            'removed' => $removed_columns,
        ];
    }

    /**
     * Get Database connection
     *
     * @param string $name
     * @return \PDO
     */
    public function getConnection(string $name): \PDO
    {
        return $this->connections[$name];
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @param array $differences_array
     * @return void
     */
    public function modifySecondaryTableColumns(\PDO $dbh, string $table_name, array $differences_array)
    {
        if (count($differences_array, COUNT_RECURSIVE) == 0) {
            return null;
        }

        $helper = $this->helper;

        if (!empty($differences_array['removed'])) {
            foreach ($differences_array['removed'] as $column_name => $column_data) {
                $sql = "ALTER TABLE `{$table_name}` DROP `{$column_name}`;";
                $query_status = $helper->sqlQueryStatus($dbh, $sql);

                if ($query_status) {
                    $this->saveLog('`' . $table_name . '` - successfully droped column `' . $column_name . '`');
                } else {
                    $this->saveLog('`' . $table_name . '` - failed to drop column `' . $column_name . '`');
                }
            }
        }

        if (!empty($differences_array['missing'])) {
            foreach ($differences_array['missing'] as $column_name => $column_data) {
                $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` " . strtoupper($column_data['type']);
                if (!empty($column_data['extra'])) {
                    $sql .= ' ' . strtoupper($column_data['extra']);
                }
                if (!empty($column_data['null'])) {
                    $sql .= ' NULL';
                }
                if (!empty($column_data['default'])) {
                    $sql .= ' DEFAULT ' . $column_data['default'];
                }
                if (!empty($column_data['charset'])) {
                    $sql .= ' CHARACTER SET ' . $column_data['charset'];
                }
                if (!empty($column_data['collation'])) {
                    $sql .= ' COLLATE ' . $column_data['collation'];
                }
                if (!empty($column_data['after'])) {
                    $sql .= ' AFTER `' . $column_data['after'] . '`';
                }
                $sql .= ';';
                $query_status = $helper->sqlQueryStatus($dbh, $sql);

                if ($query_status) {
                    $this->saveLog('`' . $table_name . '` - successfully added column `' . $column_name . '`');
                } else {
                    $this->saveLog('`' . $table_name . '` - failed to add column `' . $column_name . '`');
                }
            }
        }

        if (!empty($differences_array['changed'])) {
            foreach ($differences_array['changed'] as $column_name => $column_data) {
                $sql = "ALTER TABLE `{$table_name}` MODIFY `{$column_name}` " . strtoupper($column_data['type']);
                if (!empty($column_data['extra'])) {
                    $sql .= ' ' . strtoupper($column_data['extra']);
                }
                if (!empty($column_data['null'])) {
                    $sql .= ' NULL';
                }
                if (!empty($column_data['default'])) {
                    $sql .= ' DEFAULT ' . $column_data['default'];
                }
                if (!empty($column_data['charset'])) {
                    $sql .= ' CHARACTER SET ' . $column_data['charset'];
                }
                if (!empty($column_data['collation'])) {
                    $sql .= ' COLLATE ' . $column_data['collation'];
                }
                if (!empty($column_data['after'])) {
                    $sql .= ' AFTER `' . $column_data['after'] . '`';
                }
                $sql .= ';';
                $query_status = $helper->sqlQueryStatus($dbh, $sql);

                if ($query_status) {
                    $this->saveLog('`' . $table_name . '` - successfully modified column `' . $column_name . '`');
                } else {
                    $this->saveLog('`' . $table_name . '` - failed to modify column `' . $column_name . '`');
                }
            }
        }

        return true;
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @param array $differences_array
     * @return void
     */
    public function modifySecondaryTableIndexes(\PDO $dbh, string $table_name, array $differences_array)
    {
        if (count($differences_array, COUNT_RECURSIVE) == 0) {
            return null;
        }

        $helper = $this->helper;
        $sql_queries_array = [];

        foreach ($differences_array as $difference_type => $difference_data) {
            if ($difference_type == 'changed') {
                foreach ($difference_data as $index_name => $index_data) {
                    $sql = "DROP INDEX {$index_name}, ADD ";
                    if ($index_name == 'PRIMARY') {
                        $sql .= "PRIMARY ";
                    } else {
                        if (!empty($index_data['is_unique'])) {
                            $sql .= "UNIQUE ";
                        }
                    }
                    $sql .= "INDEX `{$index_name}` ";
                    $sql .= '(`' . implode('`, `', array_values($index_data['columns'])) . '`)';
                    $sql_queries_array[] = $sql;
                }
            } elseif ($difference_type == 'missing') {
                foreach ($difference_data as $index_name => $index_data) {
                    $sql = "ADD ";
                    if ($index_name == 'PRIMARY') {
                        $sql .= "PRIMARY ";
                    } else {
                        if (!empty($index_data['is_unique'])) {
                            $sql .= "UNIQUE ";
                        }
                    }
                    $sql .= "INDEX `{$index_name}` ";
                    $sql .= '(`' . implode('`, `', array_values($index_data['columns'])) . '`)';
                    $sql_queries_array[] = $sql;
                }
            } elseif ($difference_type == 'removed') {
                foreach ($difference_data as $index_name => $index_data) {
                    $sql_queries_array[] = "DROP INDEX `{$index_name}`";
                }
            }
        }

        if (empty($sql_queries_array)) {
            return null;
        }

        $sql = "ALTER TABLE `{$table_name}` " . implode(', ', $sql_queries_array) . ';';

        $this->saveLog('`' . $table_name . '` - found differences in indexes, SQL: "' . $sql . '"');

        return $helper->sqlQueryStatus($dbh, $sql);
    }

    /**
     * @param array $dbh_array
     * @param array $indexes
     * @param string $table_name
     * @param boolean $check_if_exists
     * @return void
     */
    public function updateSecondaryTableData(array $dbh_array, array $indexes, string $table_name, bool $check_if_exists = false)
    {
        $dbh_primary = $dbh_array['primary'] ?? false;
        $dbh_secondary = $dbh_array['secondary'] ?? false;

        if ($dbh_primary === false || $dbh_secondary === false) {
            return false;
        }

        $primary_key = $indexes['PRIMARY']['columns'][0] ?? [];
        if (empty($primary_key)) {
            return false;
        }

        $helper = $this->helper;

        $i = 0;
        $run = true;
        while ($run) {
            $stmt = $dbh_primary->prepare("SELECT * FROM `{$table_name}` LIMIT $i, 100");

            try {
                $stmt->execute();
            } catch (\PDOException $e) {
                $run = false;
                throw new ReplicatorException('PDO Error: ' . $e->getMessage());
                break;
            }

            if (!$stmt->rowCount()) {
                $run = false;
                break;
            }

            $primary_rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($primary_rows as $primary_row) {
                if (empty($primary_row[$primary_key])) {
                    $run = false;
                    break 2;
                }

                if ($check_if_exists) {
                    $stmt = $dbh_secondary->prepare("SELECT * FROM `{$table_name}` WHERE `{$primary_key}` = '{$primary_row[$primary_key]}'");
                    
                    try {
                        $stmt->execute();
                    } catch (\PDOException $e) {
                        $run = false;
                        throw new ReplicatorException('PDO Error: ' . $e->getMessage());
                        break 2;
                    }

                    if ($stmt->rowCount()) {
                        $secondary_row = $stmt->fetch(\PDO::FETCH_ASSOC);

                        if ($primary_row !== $secondary_row) {
                            $sql_queries_array = [];
                            foreach ($primary_row as $key => $value) {
                                if (!array_key_exists($key, $secondary_row)) {
                                    continue;
                                }

                                if ($value === null) {
                                    $sql_queries_array[] = "`{$key}` = NULL";
                                } else {
                                    $sql_queries_array[] = "`{$key}` = '{$value}'";
                                }
                            }

                            $this->saveLog('`' . $table_name . '` - found differences in row #' . $primary_row[$primary_key]);

                            $sql = "UPDATE `{$table_name}` SET " . implode(', ', $sql_queries_array) . " WHERE `{$primary_key}` = '{$primary_row[$primary_key]}';";
                            $query_status = $helper->sqlQueryStatus($dbh_secondary, $sql);

                            if ($query_status) {
                                $this->saveLog('`' . $table_name . '` - successfully updated row #' . $primary_row[$primary_key]);
                            } else {
                                $this->saveLog('`' . $table_name . '` - failed to update row #' . $primary_row[$primary_key]);
                            }
                        }
                    } else {
                        $sql_queries_array = [];
                        foreach ($primary_row as $key => $value) {
                            if ($value === null) {
                                $sql_queries_array[] = "`{$key}` = NULL";
                            } else {
                                $sql_queries_array[] = "`{$key}` = '{$value}'";
                            }
                        }

                        $this->saveLog('`' . $table_name . '` - found new row #' . $primary_row[$primary_key]);

                        $sql = "INSERT INTO `{$table_name}` SET " . implode(', ', $sql_queries_array) . ';';
                        $query_status = $helper->sqlQueryStatus($dbh_secondary, $sql);

                        if ($query_status) {
                            $this->saveLog('`' . $table_name . '` - successfully added new row #' . $primary_row[$primary_key]);
                        } else {
                            $this->saveLog('`' . $table_name . '` - failed to add row #' . $primary_row[$primary_key]);
                        }
                    }
                } else {
                    $sql_queries_array = [];
                    foreach ($primary_row as $key => $value) {
                        if ($value === null) {
                            $sql_queries_array[] = "`{$key}` = NULL";
                        } else {
                            $sql_queries_array[] = "`{$key}` = '{$value}'";
                        }
                    }

                    $this->saveLog('`' . $table_name . '` - found new row #' . $primary_row[$primary_key]);

                    $sql = "INSERT INTO `{$table_name}` SET " . implode(', ', $sql_queries_array) . ';';
                    $query_status = $helper->sqlQueryStatus($dbh_secondary, $sql);

                    if ($query_status) {
                        $this->saveLog('`' . $table_name . '` - successfully added new row #' . $primary_row[$primary_key]);
                    } else {
                        $this->saveLog('`' . $table_name . '` - failed to add row #' . $primary_row[$primary_key]);
                    }
                }
            }

            $i += 100;
        }

        $primary_table_checksum = $helper->getTableChecksum($dbh_primary, $table_name);
        $secondary_table_checksum = $helper->getTableChecksum($dbh_secondary, $table_name);

        if ($primary_table_checksum == $secondary_table_checksum) {
            return true;
        }

        if ($check_if_exists) {
            $i = 0;
            $run = true;
            while ($run) {
                $stmt = $dbh_secondary->prepare("SELECT * FROM `{$table_name}` LIMIT $i, 100");
                
                try {
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $run = false;
                    throw new ReplicatorException('PDO Error: ' . $e->getMessage());
                    break;
                }

                if (!$stmt->rowCount()) {
                    $run = false;
                    break;
                }

                $secondary_rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($secondary_rows as $secondary_row) {
                    if (empty($secondary_row[$primary_key])) {
                        $run = false;
                        break;
                    }

                    $stmt = $dbh_primary->prepare("SELECT * FROM `{$table_name}` WHERE `{$primary_key}` = '{$secondary_row[$primary_key]}'");
                    
                    try {
                        $stmt->execute();
                    } catch (\PDOException $e) {
                        $run = false;
                        throw new ReplicatorException('PDO Error: ' . $e->getMessage());
                        break 2;
                    }

                    if (!$stmt->rowCount()) {
                        $this->saveLog('`' . $table_name . '` - found deleted row #' . $secondary_row[$primary_key]);

                        $sql = "DELETE FROM `{$table_name}` WHERE `{$primary_key}` = '{$secondary_row[$primary_key]}';";
                        $query_status = $helper->sqlQueryStatus($dbh_secondary, $sql);

                        if ($query_status) {
                            $this->saveLog('`' . $table_name . '` - successfully copied row #' . $secondary_row[$primary_key]);
                        } else {
                            $this->saveLog('`' . $table_name . '` - failed to copy row #' . $secondary_row[$primary_key]);
                        }
                    }
                }

                $i += 100;
            }
        }

        return true;
    }

    /**
     * @param string $message
     * @return boolean
     */
    public function saveLog(string $message = ''): bool
    {
        if (!$this->debug) {
            return false;
        }

        return file_put_contents($this->log_file, date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * Run the Replicator
     *
     * @param array $watch_tables
     * @return void
     */
    public function run(array $watch_tables = [])
    {
        if (empty($watch_tables)) {
            throw new ReplicatorException('Error: there are no tables to watch.');
            return false;
        }

        $connections = $this->connections;
        $helper = $this->helper;

        // watch all tables
        if (count($watch_tables) == 1 && $watch_tables[0] == '*') {
            $primary_db_tables = $helper->getTables($connections['primary']);
            $secondary_db_tables = $helper->getTables($connections['secondary']);

            foreach ($secondary_db_tables as $table_name) {
                if (!in_array($table_name, $primary_db_tables)) {
                    // drop table (on secondary)
                    $drop_table_status = $helper->sqlQueryStatus($connections['secondary'], "DROP TABLE `{$table_name}`;");

                    if ($drop_table_status) {
                        $this->saveLog('`' . $table_name . '` - dropped');
                    } else {
                        $this->saveLog('`' . $table_name . '` - was not dropped');
                    }
                }
            }

            $watch_tables = $primary_db_tables;
        }

        foreach ($watch_tables as $table_name) {
            // check if table exists (on primary)
            if ($helper->doesTableExists($connections['primary'], $table_name)) {
                // check  if table exists (on secondary)
                if ($helper->doesTableExists($connections['secondary'], $table_name)) {
                    // compare structures
                    $primary_table_structure = $helper->getTableStructure($connections['primary'], $table_name);
                    $secondary_table_structure = $helper->getTableStructure($connections['secondary'], $table_name);

                    // find differences and apply changes
                    $tables_columns_diff = $this->findDifferentValues($primary_table_structure, $secondary_table_structure, true);

                    $secondary_table_columns_update = $this->modifySecondaryTableColumns($connections['secondary'], $table_name, $tables_columns_diff);

                    // compare indexes
                    $primary_table_indexes = $helper->getTableIndexes($connections['primary'], $table_name);
                    $secondary_table_indexes = $helper->getTableIndexes($connections['secondary'], $table_name);
                    $tables_indexes_diff = $this->findDifferentValues($primary_table_indexes, $secondary_table_indexes);
                    $secondary_table_indexes_update = $this->modifySecondaryTableIndexes($connections['secondary'], $table_name, $tables_indexes_diff);

                    // checksums (content)
                    $primary_table_checksum = $helper->getTableChecksum($connections['primary'], $table_name);
                    $secondary_table_checksum = $helper->getTableChecksum($connections['secondary'], $table_name);

                    if ($primary_table_checksum === $secondary_table_checksum) {
                        continue;
                    }

                    // update data
                    $secondary_table_rows_update = $this->updateSecondaryTableData($connections, $primary_table_indexes, $table_name, true);
                } else {
                    // create table (on secondary)
                    $primary_table_indexes = $helper->getTableIndexes($connections['primary'], $table_name);
                    $primary_table_structure_sql = $helper->getTableCreationQuery($connections['primary'], $table_name);
                    $create_table_status = $helper->sqlQueryStatus($connections['secondary'], $primary_table_structure_sql);

                    if ($create_table_status) {
                        $this->saveLog('`' . $table_name . '` - created');
                    } else {
                        $this->saveLog('`' . $table_name . '` - create failed');
                        continue;
                    }

                    // update data
                    $secondary_table_rows_update = $this->updateSecondaryTableData($connections, $primary_table_indexes, $table_name);
                }
            } else {
                if ($helper->doesTableExists($connections['secondary'], $table_name)) {
                    // drop table (on secondary)
                    $drop_table_status = $helper->sqlQueryStatus($connections['secondary'], "DROP TABLE `{$table_name}`;");

                    if ($drop_table_status) {
                        $this->saveLog('`' . $table_name . '` - dropped');
                    } else {
                        $this->saveLog('`' . $table_name . '` - was not dropped');
                    }
                }
            }
        }
    }
}
