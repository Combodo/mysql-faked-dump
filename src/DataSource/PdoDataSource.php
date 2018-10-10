<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 10/10/18
 * Time: 09:37
 */

namespace App\DataSource;


class PdoDataSource
{
    /** @var \PDO */
    private $pdo;

    public function setConfig(array $data_source_config)
    {
        $this->pdo = new \PDO($data_source_config['dsn'], $data_source_config['user'], $data_source_config['password'], $data_source_config['options']);
    }

    public function getPdo()
    {
        return $this->pdo;
    }
}