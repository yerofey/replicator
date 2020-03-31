<?php

namespace Yerofey\Replicator;

class ReplicatorHelper
{
    // TODO: cache some results
    private $cache = [];

    /**
     * @param array $mysql_config
     * @return void
     */
    public function createConnection(array $mysql_config = [])
    {
        if (empty($mysql_config) || !isset($mysql_config['hostname']) || !isset($mysql_config['database']) || !isset($mysql_config['username']) || !isset($mysql_config['password'])) {
            throw new ReplicatorException('DB Connection Error: config data is empty.');
            return false;
        }

        try {
            $dbh = new \PDO('mysql:host=' . $mysql_config['hostname'] . ';dbname=' . $mysql_config['database'] . ';charset=UTF8', $mysql_config['username'], $mysql_config['password']);
            $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return false;
        }

        return $dbh;
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @return boolean
     */
    public function doesTableExists(\PDO $dbh, string $table_name): bool
    {
        $stmt = $dbh->prepare("SHOW TABLES LIKE '{$table_name}';");

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return false;
        }

        if (!$stmt->rowCount()) {
            return false;
        }

        return true;
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @return void
     */
    public function getTableChecksum(\PDO $dbh, string $table_name)
    {
        $stmt = $dbh->prepare("CHECKSUM TABLE `{$table_name}`;");
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return false;
        }

        if (!$stmt->rowCount()) {
            return false;
        }

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['Checksum'];
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @return void
     */
    public function getTableCreationQuery(\PDO $dbh, string $table_name)
    {
        $stmt = $dbh->prepare("SHOW CREATE TABLE `{$table_name}`;");
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return false;
        }

        if (!$stmt->rowCount()) {
            return false;
        }

        // [Table, Create Table]
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row['Create Table'];
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @return array
     */
    public function getTableIndexes(\PDO $dbh, string $table_name): array
    {
        $stmt = $dbh->prepare("SHOW INDEXES FROM `{$table_name}`;");
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return [];
        }

        if (!$stmt->rowCount()) {
            return [];
        }

        $result = [];
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $result[$row['Key_name']]['is_unique'] = ($row['Non_unique'] == 1) ? 0 : 1;
            $result[$row['Key_name']]['columns'][] = $row['Column_name'];
        }

        return $result;
    }

    /**
     * @param \PDO $dbh
     * @param string $table_name
     * @return array
     */
    public function getTableStructure(\PDO $dbh, string $table_name): array
    {
        $stmt = $dbh->prepare("SHOW FULL COLUMNS FROM `{$table_name}`;");
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return false;
        }

        if (!$stmt->rowCount()) {
            return [];
        }

        $previous_column_name = '';
        $result = [];
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            // $row_collation = $row['Collation'] ?? '';
            // $row_charset = '';
            // if (!empty($row_collation)) {
            //     $temp = explode('_', $row_collation);
            //     // "utf8_general_ci" -> "utf8"
            //     $row_charset = $temp[0];
            // }

            $result[$row['Field']] = [
                'type'      => $row['Type'],
                'null'      => $row['Null'] == 'NO' ? 0 : 1,
                'default'   => $row['Default'],
                'extra'     => $row['Extra'],
                // TODO
                //'collation' => $row_collation,
                //'charset'   => $row_charset,
                'after'     => $previous_column_name,
            ];

            $previous_column_name = $row['Field'];
        }

        return $result;
    }

    /**
     * @param \PDO $dbh
     * @return array
     */
    public function getTables(\PDO $dbh): array
    {
        $stmt = $dbh->prepare("SHOW FULL TABLES;");
        
        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            throw new ReplicatorException('PDO Error: ' . $e->getMessage());
            return [];
        }

        if (!$stmt->rowCount()) {
            return [];
        }

        $tables = [];
        
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $key => $data) {
            $values = array_values($data);
            $table = $values[0];
            $type = $values[1];

            $tables[] = $table;
        }

        return $tables;
    }

    /**
     * @param \PDO $dbh
     * @param string $query
     * @param array $params
     * @return boolean
     */
    public function sqlQueryStatus(\PDO $dbh, string $query, array $params = []): bool
    {
        $stmt = $dbh->prepare($query);

        try {
            $stmt->execute();
        } catch (\PDOException $e) {
            return false;
        }

        if ($stmt->rowCount()) {
            return true;
        }

        return true;
    }
}
