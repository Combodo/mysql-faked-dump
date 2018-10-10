<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 10/10/18
 * Time: 09:21
 */

namespace App\Loader;


use App\DataSource\PdoDataSource;
use App\Table\Table;

class TablesLoader
{

    /** @var PdoDataSource */
    private $dataSource;

    public function setDataSource(PdoDataSource $dataSource)
    {
        $this->dataSource = $dataSource;
    }

    /**
     * @param array $config
     *
     * @return Table[]
     */
    public function getTableCollection(array $config)
    {
        $tables = [];
        $rowSet = $this->dataSource->getPdo()->query('SHOW TABLES', \PDO::FETCH_NUM);

        foreach ($rowSet as $row) {
            $tables[$row[0]] = new Table($row[0], $config, $this->dataSource);
        }

        return $tables;
    }


}