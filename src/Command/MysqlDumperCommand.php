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
use App\Table\Table;
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
            ->addOption('skip-drop-table', null, InputOption::VALUE_NONE, 'remove drop table statements from the dump')
            ->addOption('skip-create-table', null, InputOption::VALUE_NONE, 'remove create table statements from the dump')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->getErrorStyle()->writeln("\n\nStarting.\n");

        $config = $this->yamlConfigLoader->load(
            $input->getArgument('config')
        );

        $dataSource = new PdoDataSource();
        $dataSource->setConfig($config['data_source']);
        $this->tablesLoader->setDataSource($dataSource);

        $tablesCollection = $this->tablesLoader->getTableCollection($config);

        $output->writeln("
/** mysql-faked-dump generated this file on ".date('F jS Y \\a\\t G:ia')." */        
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;");

        foreach ($tablesCollection as $table) {

            if ($table->isExcluded()) {
                $io->getErrorStyle()->writeln("- skipping {$table->getName()}");
                $output->write("\n/**  skipping excluded {$table->getName()}  */\n\n");
                continue;
            }

            $io->getErrorStyle()->write(str_pad("+ dumping {$table->getName()}", 55, ' '));

            $output->write("\n\n/** begin TABLE {$table->getName()}  */\n");

            if (! $input->getOption('skip-drop-table')) {
                $output->writeln($table->getDropTable());
            } else {
                $output->write("/**  skip-drop-table {$table->getName()}  */\n");
            }

            if (! $input->getOption('skip-create-table')) {
                $output->writeln($table->getCreateTable());
            } else {
                $output->write("/**  skip-create-table {$table->getName()}  */\n");
            }


            $rowCountPreserved = $this->writeInsertInto(false, $table, $output);
            $rowCountFaked = $this->writeInsertInto(true, $table, $output);

            $rowCount = $rowCountPreserved + $rowCountFaked;

            if ($rowCountPreserved > 0) {
                $io->getErrorStyle()->writeln("| $rowCount rows (including $rowCountPreserved preserved)");
            } else {
                $io->getErrorStyle()->writeln("| $rowCount rows");
            }


        }

        $output->writeln("
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;");

        $io->getErrorStyle()->writeln("\nDone.\n\n");
    }

    private function writeInsertInto(bool $getFakedValues, Table $table, OutputInterface $output)
    {
        if ($getFakedValues) {
            $getter = "nextFakedValue";
        } else {
            $getter = "nextPreservedValue";
        }

        /** @var TableInsertInto $insertInto */
        $insertInto = $table->getInsertInto();

        $rowCount = 0;
        $maxBulkSize = 100;
        while ($value = $insertInto->$getter()) {
            if ($rowCount % $maxBulkSize != 0) {
                $output->write(",$value");
            } else {
                if ($rowCount > 0) {
                    $output->write(";\n/* split {$table->getName()} at {$rowCount}th row because maxBulkSize $maxBulkSize   */\n");
                }
                $output->write($insertInto->getInsertIntoBeginningString());
                $output->write($value);
            }
            $rowCount++;
        }
        if ($rowCount > 0) {
            $output->write(";\n");
        }
        $output->write("/** finished {$table->getName()} $rowCount rows dumped */\n");

        return $rowCount;
    }
}