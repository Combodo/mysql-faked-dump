<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 10/10/18
 * Time: 09:51
 */

namespace App\Table;


use App\DataSource\PdoDataSource;
use App\Faker\FakerAccessor;
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
     * @var TableInsertInto
     */
    private $insertInto;
    /**
     * @var FakerAccessor
     */
    private $fakerAccessor;

    public function __construct(string $name, array $config, PdoDataSource $dataSource, FakerAccessor $fakerAccessor, ?TableInsertInto $insertInto = null)
    {
        $this->name = $name;
        $this->rules = $this->extractRulesFromConfig($config);
        $this->dataSource = $dataSource;
        $this->fakerAccessor = $fakerAccessor;

        $this->insertInto = $insertInto;
        if (!$this->insertInto instanceof TableInsertInto) {
            $this->insertInto = new  TableInsertInto($this->name, $this->rules, $this->dataSource, $this->fakerAccessor);
        }


    }

    private function extractRulesFromConfig(array $config)
    {
        if (array_key_exists($this->name, $config['tables'])) {
            return $config['tables'][$this->name];
        }

        foreach ($config['tables'] as $rule) {
            if (! array_key_exists('pattern', $rule)) {
                continue;
            }

            if (preg_match("#{$rule['pattern']}#", $this->name)) {
                return $rule;
            }
        }

        return [
            'exclude' => $config['options']['exclude_missing_tables']
        ];
    }

    public function isExcluded() : bool
    {
        return $this->rules['exclude'] ?? false;
    }

    public function getDropTable(): string
    {
        return "DROP TABLE IF EXISTS `$this->name`;\n";
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