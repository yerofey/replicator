<?php

namespace yerofey;

class Replicator
{
    /**
     * @param  array   $primary_array    [description]
     * @param  array   $secondary_array  [description]
     * @param  boolean $check_keys_order [description]
     * @return array                     [description]
     */
    public static function findDifferentValues(array $primary_array, array $secondary_array, bool $check_keys_order = false): array
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
     * @param  \PDO   $dbh               [description]
     * @param  string $table_name        [description]
     * @param  array  $differences_array [description]
     * @return bool                      [description]
     */
    public static function modifySecondaryTableColumns(\PDO $dbh, string $table_name, array $differences_array)
    {
        if (count($differences_array, COUNT_RECURSIVE) == 0) {
            return null;
        }

        if (!empty($differences_array['removed'])) {
            foreach ($differences_array['removed'] as $column_name => $column_data) {
                $sql = "ALTER TABLE `{$table_name}` DROP IF EXISTS `{$column_name}`;";
                $query_status = ReplicatorHelpers::sqlQueryStatus($dbh, $sql);

                if ($query_status) {
                    self::saveLog('`' . $table_name . '` - successfully droped column `' . $column_name . '`');
                } else {
                    self::saveLog('`' . $table_name . '` - failed to drop column `' . $column_name . '`');
                }
            }
        }

        if (!empty($differences_array['missing'])) {
            foreach ($differences_array['missing'] as $column_name => $column_data) {
                $sql = "ALTER TABLE `{$table_name}` ADD COLUMN IF NOT EXISTS `{$column_name}` " . strtoupper($column_data['type']);
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
                $query_status = ReplicatorHelpers::sqlQueryStatus($dbh, $sql);

                if ($query_status) {
                    self::saveLog('`' . $table_name . '` - successfully added column `' . $column_name . '`');
                } else {
                    self::saveLog('`' . $table_name . '` - failed to add column `' . $column_name . '`');
                }
            }
        }

        if (!empty($differences_array['changed'])) {
            foreach ($differences_array['changed'] as $column_name => $column_data) {
                $sql = "ALTER TABLE `{$table_name}` MODIFY IF EXISTS `{$column_name}` " . strtoupper($column_data['type']);
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
                $query_status = ReplicatorHelpers::sqlQueryStatus($dbh, $sql);

                if ($query_status) {
                    self::saveLog('`' . $table_name . '` - successfully modified column `' . $column_name . '`');
                } else {
                    self::saveLog('`' . $table_name . '` - failed to modify column `' . $column_name . '`');
                }
            }
        }

        return true;
    }

    /**
     * @param  \PDO   $dbh               [description]
     * @param  string $table_name        [description]
     * @param  array  $differences_array [description]
     * @return bool                      [description]
     */
    public static function modifySecondaryTableIndexes(\PDO $dbh, string $table_name, array $differences_array)
    {
        if (count($differences_array, COUNT_RECURSIVE) == 0) {
            return null;
        }

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

        self::saveLog('`' . $table_name . '` - found differences in indexes, SQL: "' . $sql . '"');

        return ReplicatorHelpers::sqlQueryStatus($dbh, $sql);
    }

    /**
     * @param  array   $dbh_array       [description]
     * @param  array   $indexes         [description]
     * @param  string  $table_name      [description]
     * @param  boolean $check_if_exists [description]
     * @return [type]                   [description]
     */
    public static function updateSecondaryTableData(array $dbh_array, array $indexes, string $table_name, bool $check_if_exists = false)
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

                            self::saveLog('`' . $table_name . '` - found differences in row #' . $primary_row[$primary_key]);

                            $sql = "UPDATE `{$table_name}` SET " . implode(', ', $sql_queries_array) . " WHERE `{$primary_key}` = '{$primary_row[$primary_key]}';";
                            $query_status = ReplicatorHelpers::sqlQueryStatus($dbh_secondary, $sql);

                            if ($query_status) {
                                self::saveLog('`' . $table_name . '` - successfully updated row #' . $primary_row[$primary_key]);
                            } else {
                                self::saveLog('`' . $table_name . '` - failed to update row #' . $primary_row[$primary_key]);
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

                        self::saveLog('`' . $table_name . '` - found new row #' . $primary_row[$primary_key]);

                        $sql = "INSERT INTO `{$table_name}` SET " . implode(', ', $sql_queries_array) . ';';
                        $query_status = ReplicatorHelpers::sqlQueryStatus($dbh_secondary, $sql);

                        if ($query_status) {
                            self::saveLog('`' . $table_name . '` - successfully added new row #' . $primary_row[$primary_key]);
                        } else {
                            self::saveLog('`' . $table_name . '` - failed to add row #' . $primary_row[$primary_key]);
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

                    self::saveLog('`' . $table_name . '` - found new row #' . $primary_row[$primary_key]);

                    $sql = "INSERT INTO `{$table_name}` SET " . implode(', ', $sql_queries_array) . ';';
                    $query_status = ReplicatorHelpers::sqlQueryStatus($dbh_secondary, $sql);

                    if ($query_status) {
                        self::saveLog('`' . $table_name . '` - successfully added new row #' . $primary_row[$primary_key]);
                    } else {
                        self::saveLog('`' . $table_name . '` - failed to add row #' . $primary_row[$primary_key]);
                    }
                }
            }

            $i += 100;
        }

        $primary_table_checksum = ReplicatorHelpers::getTableChecksum($dbh_primary, $table_name);
        $secondary_table_checksum = ReplicatorHelpers::getTableChecksum($dbh_secondary, $table_name);

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
                        self::saveLog('`' . $table_name . '` - found deleted row #' . $secondary_row[$primary_key]);

                        $sql = "DELETE FROM `{$table_name}` WHERE `{$primary_key}` = '{$secondary_row[$primary_key]}';";
                        $query_status = ReplicatorHelpers::sqlQueryStatus($dbh_secondary, $sql);

                        if ($query_status) {
                            self::saveLog('`' . $table_name . '` - successfully copied row #' . $secondary_row[$primary_key]);
                        } else {
                            self::saveLog('`' . $table_name . '` - failed to copy row #' . $secondary_row[$primary_key]);
                        }
                    }
                }

                $i += 100;
            }
        }

        return true;
    }

    /**
     * [saveLog description]
     * @param  string $message [description]
     * @return bool            [description]
     */
    public static function saveLog(string $message = ''): bool
    {
        if (!REPLICATOR_DEBUG) {
            return false;
        }

        return file_put_contents(REPLICATOR_LOGFILE, date('Y-m-d H:i:s') . ' | ' . $message . PHP_EOL, FILE_APPEND);
    }

    /**
     * [run description]
     * @param  array  $databases    [description]
     * @param  array  $watch_tables [description]
     * @return [type]               [description]
     */
    public static function run(array $databases = [], array $watch_tables = [])
    {
        if (empty($watch_tables)) {
            throw new ReplicatorException('Error: there are no tables to watch.');
            return false;
        }

        foreach ($watch_tables as $table_name) {
            // check if table exists (on primary)
            if (ReplicatorHelpers::doesTableExists($databases['primary'], $table_name)) {
                // check  if table exists (on secondary)
                if (ReplicatorHelpers::doesTableExists($databases['secondary'], $table_name)) {
                    // compare structures
                    $primary_table_structure = ReplicatorHelpers::getTableStructure($databases['primary'], $table_name);
                    $secondary_table_structure = ReplicatorHelpers::getTableStructure($databases['secondary'], $table_name);

                    // find differences and apply changes
                    $tables_columns_diff = self::findDifferentValues($primary_table_structure, $secondary_table_structure, true);

                    $secondary_table_columns_update = self::modifySecondaryTableColumns($databases['secondary'], $table_name, $tables_columns_diff);

                    // compare indexes
                    $primary_table_indexes = ReplicatorHelpers::getTableIndexes($databases['primary'], $table_name);
                    $secondary_table_indexes = ReplicatorHelpers::getTableIndexes($databases['secondary'], $table_name);
                    $tables_indexes_diff = self::findDifferentValues($primary_table_indexes, $secondary_table_indexes);
                    $secondary_table_indexes_update = self::modifySecondaryTableIndexes($databases['secondary'], $table_name, $tables_indexes_diff);

                    // checksums (content)
                    $primary_table_checksum = ReplicatorHelpers::getTableChecksum($databases['primary'], $table_name);
                    $secondary_table_checksum = ReplicatorHelpers::getTableChecksum($databases['secondary'], $table_name);

                    if ($primary_table_checksum === $secondary_table_checksum) {
                        continue;
                    }

                    // update data
                    $secondary_table_rows_update = self::updateSecondaryTableData($databases, $primary_table_indexes, $table_name, true);
                } else {
                    // create table (on secondary)
                    $primary_table_indexes = ReplicatorHelpers::getTableIndexes($databases['primary'], $table_name);
                    $primary_table_structure_sql = ReplicatorHelpers::getTableCreationQuery($databases['primary'], $table_name);
                    $create_table_status = ReplicatorHelpers::sqlQueryStatus($databases['secondary'], $primary_table_structure_sql);

                    if (!$create_table_status) {
                        self::saveLog('`' . $table_name . '` - create failed');
                        continue;
                    }

                    // update data
                    $secondary_table_rows_update = self::updateSecondaryTableData($databases, $primary_table_indexes, $table_name);
                }
            } else {
                if (ReplicatorHelpers::doesTableExists($databases['secondary'], $table_name)) {
                    // drop table (on secondary)
                    $drop_table_status = ReplicatorHelpers::sqlQueryStatus($databases['secondary'], "DROP TABLE `{$table_name}`;");

                    if ($drop_table_status) {
                        self::saveLog('`' . $table_name . '` - dropped');
                    } else {
                        self::saveLog('`' . $table_name . '` - was not dropped');
                    }
                }
            }
        }
    }
}
