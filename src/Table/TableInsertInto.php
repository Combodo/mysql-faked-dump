<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 10/10/18
 * Time: 12:00
 */

namespace App\Table;


use App\DataSource\PdoDataSource;
use Faker\Generator;

class TableInsertInto
{

    /** @var string */
    private $name;
    /**  @var array */
    private $rules;
    /** @var \PDOStatement */
    private $insertIntoValuesStmt;
    /** @var string */
    private $insertIntoBeginningString;
    /** @var PdoDataSource */
    private $dataSource;
    /** @var array */
    private $columns;
    /**
     * @var Generator
     */
    private $fakerGenerator;

    public function __construct(string $name, array $rules, PdoDataSource $dataSource, Generator $fakerGenerator)
    {
        $this->name = $name;
        $this->rules = $rules;
        $this->dataSource = $dataSource;
        $this->fakerGenerator = $fakerGenerator;

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

        $this->insertIntoValuesStmt = $this->dataSource->getPdo()->query(
            sprintf(
                "SELECT %s FROM `%s`",
                implode(
                    ',',
                    $this->getInsertIntoSelectedColumns()
                ),
                $this->name
            )
        );
    }



    private function getColumnRule(string $column)
    {
        return $this->rules['columns'][$column] ?? null;
    }

    private function getInsertIntoSelectedColumns()
    {
        $columnsSelected = [];
        foreach ($this->columns['raw'] as $column) {
            $columnRule = $this->getColumnRule($column);

            if ($columnRule['empty'] ?? false) {
                continue;
            }
            if ($columnRule['faker'] ?? false) {
                continue;
            }

            $columnsSelected[] = "`$column`";
        }

        return $columnsSelected;
    }


    /**
     * @return string
     */
    public function getInsertIntoBeginningString(): string
    {
        return $this->insertIntoBeginningString;
    }

    public function nextValue(): ?string
    {
        $fakedRow = [];
        $row = $this->insertIntoValuesStmt->fetch(\PDO::FETCH_ASSOC);
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
                    $this->fakerGenerator->{$columnRule['faker']}
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