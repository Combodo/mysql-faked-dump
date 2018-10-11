<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 09/10/18
 * Time: 11:26
 */

namespace App\Command;


use App\DataSource\PdoDataSource;
use App\Loader\TablesLoader;
use App\Loader\YamlConfigLoader;
use App\Table\TableInsertInto;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MysqlDumperCommand extends Command
{

    /** @var YamlConfigLoader */
    private $yamlConfigLoader;
    /** @var TablesLoader */
    private $tablesLoader;

    public function __construct(?string $name = null, YamlConfigLoader $yamlConfigLoader, TablesLoader $tablesLoader)
    {
        parent::__construct($name);

        $this->yamlConfigLoader = $yamlConfigLoader;
        $this->tablesLoader = $tablesLoader;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mysql-faked:dump')
            ->setDescription('Fake dump a mysql server using mysql dump')
            ->setHelp('This command allows you to fake dump a mysql server using mysql dump...')
            ->addArgument('config', InputArgument::REQUIRED, 'the path to the yaml config file')
//            ->addOption('config', null, InputOption::VALUE_REQUIRED)
            ->addOption('add-drop-table', null, InputOption::VALUE_NONE, 'add drop table statements to the dump')
            ->addOption('skip-create-table', null, InputOption::VALUE_NONE, 'remove create table statements to the dump')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $config = $this->yamlConfigLoader->load(
            $input->getArgument('config')
        );

        $dataSource = new PdoDataSource();
        $dataSource->setConfig($config['data_source']);
        $this->tablesLoader->setDataSource($dataSource);

        $tablesCollection = $this->tablesLoader->getTableCollection($config);

        foreach ($tablesCollection as $table) {

            if ($table->isExcluded()) {
                $io->getErrorStyle()->warning("skipping table {$table->getName()}");
                $output->write("\n/**  skipping excluded {$table->getName()}  */\n\n");
                continue;
            }

            $io->getErrorStyle()->writeln("dumping table {$table->getName()}");

            $output->write("\n/**  writing table {$table->getName()}  */\n\n");


            if ($input->hasOption('add-drop-table')) {
                $output->writeln($table->getDropTable());
            } else {
                $output->write("\n/**  add-drop-table not set, skipping drop table {$table->getName()}  */\n\n");
            }

            if (! $input->hasOption('skip-create-table')) {
                $output->writeln($table->getCreateTable());
            } else {
                $output->write("\n/**  skip-create-table {$table->getName()}  */\n\n");
            }

            /** @var TableInsertInto $insertInto */
            $insertInto = $table->getInsertInto();

            $first = true;
            while ($value = $insertInto->nextValue()) {
                if (!$first) {
                    $output->write(",$value");
                } else {
                    $output->write($insertInto->getInsertIntoBeginningString());
                    $output->write($value);
                    $first = false;
                }
            }
            $output->write(";\n");
        }

    }
}