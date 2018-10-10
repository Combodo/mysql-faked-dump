<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 10/10/18
 * Time: 09:51
 */

namespace App\Table;


use App\DataSource\PdoDataSource;
use Faker\Generator;

class Table
{
    /** @var string */
    private $name;
    /** @var array  */
    private $rules;
    /**
     * @var PdoDataSource
     */
    private $dataSource;
    /**
     * @var Generator|null
     */
    private $fakerGenerator;
    /**
     * @var TableInsertInto
     */
    private $insertInto;

    public function __construct(string $name, array $config, PdoDataSource $dataSource, ?Generator $fakerGenerator = null, ?TableInsertInto $insertInto = null)
    {
        $this->name = $name;
        $this->rules = $this->extractRulesFromConfig($config);
        $this->dataSource = $dataSource;


        $this->fakerGenerator = $fakerGenerator;
        if (! $this->fakerGenerator instanceof Generator) {
            $this->fakerGenerator = \Faker\Factory::create();
        }


        $this->insertInto = $insertInto;
        if (!$this->insertInto instanceof TableInsertInto) {
            $this->insertInto = new  TableInsertInto($this->name, $this->rules, $this->dataSource, $this->fakerGenerator);
        }

    }

    private function extractRulesFromConfig(array $config)
    {
        if (array_key_exists($this->name, $config['tables'])) {
            return $config['tables'][$this->name];
        }

        foreach ($config as $rule) {
            if (! array_key_exists('pattern', $rule)) {
                continue;
            }

            if (preg_match($rule['pattern'], $this->name)) {
                return $rule;
            }
        }

        return [
            'exclude' => $config['options']['exclude_missing_tables']
        ];
    }



    public function getCreateTable(): string
    {
        $reateTableStatement = $this->dataSource->getPdo()->query("SHOW CREATE TABLE `$this->name`");
        return $reateTableStatement->fetch(\PDO::FETCH_ASSOC)['Create Table'].";\n";
    }

    public function getInsertInto(): TableInsertInto
    {
        return $this->insertInto;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


}