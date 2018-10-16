<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 10/10/18
 * Time: 12:00
 */

namespace App\Table;


use App\DataSource\PdoDataSource;
use App\Faker\FakerAccessor;
use Faker\Generator;

class TableInsertInto
{

    /** @var string */
    private $name;
    /**  @var array */
    private $rules;
    /** @var \PDOStatement[] */
    private $insertIntoValuesStmtCollection;
    /** @var string */
    private $insertIntoBeginningString;
    /** @var PdoDataSource */
    private $dataSource;
    /** @var array */
    private $columns;
    /** @var FakerAccessor */
    private $fakerAccessor;
    /** @var string[] */
    private $insertIntoValuesSqlCollection;

    public function __construct(string $name, array $rules, PdoDataSource $dataSource, FakerAccessor $fakerAccessor)
    {
        $this->name = $name;
        $this->rules = $rules;
        $this->dataSource = $dataSource;
        $this->fakerAccessor = $fakerAccessor;

        $this->initInsertInto();
    }


    private function initInsertInto()
    {
        $describeStatement = $this->dataSource->getPdo()->query("DESCRIBE `$this->name`");
        $this->columns = [
            'escaped' => [],
            'raw' => [],
        ];

        while ($row = $describeStatement->fetch(\PDO::FETCH_ASSOC)) {
            $this->columns['escaped'][]   = "`{$row['Field']}`";
            $this->columns['raw'][]       = $row['Field'];
        }

        $this->insertIntoBeginningString = sprintf("INSERT INTO `%s` (%s) VALUES ", $this->name, implode(',', $this->columns['escaped']));

        if ($this->rules['preserve_filter'] ?? false) {
            $this->insertIntoValuesSqlCollection = [
                'preserved' => sprintf("SELECT %s FROM `%s` WHERE %s",       $this->getInsertIntoSelectedColumns(true),  $this->name, $this->rules['preserve_filter']['preserve']),
                'faked' => sprintf("SELECT %s FROM `%s` WHERE %s",  $this->getInsertIntoSelectedColumns(false), $this->name, $this->rules['preserve_filter']['reverse']),
            ];
        } else {
            $this->insertIntoValuesSqlCollection = [
                'faked' =>  sprintf("SELECT %s FROM `%s`", $this->getInsertIntoSelectedColumns(), $this->name),
            ];
        }

        foreach ($this->insertIntoValuesSqlCollection as $collectionName => $sql) {
            $this->insertIntoValuesStmtCollection[$collectionName] = $this->dataSource->getPdo()->query($sql);
        }
    }



    private function getColumnRule(string $column)
    {
        return $this->rules['columns'][$column] ?? null;
    }

    private function getInsertIntoSelectedColumns(bool $preserverAll = false, ?string $implodeStr = ',')
    {
        $columnsSelected = [];
        foreach ($this->columns['raw'] as $column) {
            if ($preserverAll) {
                $columnsSelected[] = "`$column`";
                continue;
            }

            $columnRule = $this->getColumnRule($column);
            if ($columnRule['empty'] ?? false) {
                continue;
            }
            if ($columnRule['faker'] ?? false) {
                continue;
            }

            $columnsSelected[] = "`$column`";
        }

        if (empty($implodeStr)) {
            return $columnsSelected;
        }

        return implode($implodeStr, $columnsSelected);
    }


    /**
     * @return string
     */
    public function getInsertIntoBeginningString(): string
    {
        return $this->insertIntoBeginningString;
    }


    /**
     * @return bool|string
     */
    public function nextPreservedValue()
    {
        if (!isset($this->insertIntoValuesStmtCollection['preserved'])) {
            return false;
        }
        $preservedRow = [];
        $row = $this->insertIntoValuesStmtCollection['preserved']->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            unset($this->insertIntoValuesStmtCollection['preserved']);
            return false;
        }

        foreach ($this->columns['raw'] as $columnName) {
            if (null === $row[$columnName]) {
                $preservedRow[$columnName] = 'NULL';
            } else {
                $preservedRow[$columnName] = $this->dataSource->getPdo()->quote($row[$columnName]);
            }
        }

        return sprintf('(%s)', implode(',', $preservedRow));
    }

    /**
     * @return bool|string
     */
    public function nextFakedValue()
    {
        $fakedRow = [];
        $row = $this->insertIntoValuesStmtCollection['faked']->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        foreach ($this->columns['raw'] as $columnName) {
            $columnRule = $this->getColumnRule($columnName);

            if ($columnRule['empty'] ?? false) {
                $fakedRow[$columnName] = '""';
                continue;
            }
            if ($columnRule['faker'] ?? false) {
                $fakedRow[$columnName] =  $this->dataSource->getPdo()->quote(
                    $this->fakerAccessor->get($columnRule['faker'])
                );
                foreach ($columnRule['alias'] ?? [] as $alias) {
                    $fakedRow[$alias] = $fakedRow[$columnName];
                }
                continue;
            }
            if (isset($fakedRow[$columnName])) {
                continue;
            }

            if (null === $row[$columnName]) {
                $fakedRow[$columnName] = 'NULL';
            } else {
                $fakedRow[$columnName] = $this->dataSource->getPdo()->quote($row[$columnName]);
            }
        }

        return sprintf('(%s)', implode(',', $fakedRow));
    }
}