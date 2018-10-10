<?php
/**
 * Created by Bruno DA SILVA, working for Combodo
 * Date: 09/10/18
 * Time: 16:51
 */

namespace App\Loader;


use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

class YamlConfigLoader
{
    const SUPPORTED_FILE_FORMATS = ['yaml', 'yml'];
    /**
     * @var Parser
     */
    private $yamlParser;

    /**
     * load a YAML file.
     *
     * @param string $file
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function load($file)
    {
        if (!stream_is_local($file)) {
            throw new \InvalidArgumentException(sprintf('This is not a local file "%s".', $file));
        }

        if (!file_exists($file)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not exist.', $file));
        }

        if (!$this->supports($file)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" format is not supported. Supported formats are :"%s".', $file, implode('","', self::SUPPORTED_FILE_FORMATS)));
        }

        if (!$this->yamlParser instanceof Parser) {
            $this->yamlParser = new Parser();
        }


        try {
            $configuration = $this->yamlParser->parseFile($file, Yaml::PARSE_CONSTANT | Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException(sprintf('The file "%s" does not contain valid YAML.', $file), 0, $e);
        }

        return $this->validate($configuration, $file);
    }

    public function supports($resource)
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (\in_array(pathinfo($resource, PATHINFO_EXTENSION), self::SUPPORTED_FILE_FORMATS, true)) {
            return true;
        }

    }

    /**
     * Validates a YAML file.
     *
     * @param mixed  $content
     * @param string $file
     *
     * @return array
     *
     * @throws \InvalidArgumentException When service file is not valid
     */
    private function validate($content, string $file)
    {
        if (null === $content) {
            return $content;
        }

        if (!\is_array($content)) {
            throw new InvalidArgumentException(sprintf('The file "%s" is not valid. It should contain an array. Check your YAML syntax.', $file));
        }

        foreach ($content as $namespace => $data) {
            if (! \in_array($namespace, array('data_source', 'options', 'tables'))) {
                throw new \InvalidArgumentException(sprintf('Unsupported entry "%s" found into "%s". Check your configuration.', $namespace,$file));
            }
        }

        $this->validateDataSourceEntries($content, $file);
        $this->validateOptionsEntries($content, $file);
        $this->validateTablesEntries($content, $file);


        return $content;
    }


    private function validateTablesEntries(array $content, string $file)
    {
        if (!\is_array($content['tables'])) {
            throw new \InvalidArgumentException(sprintf('The file "%s" is not valid. The tables section should not be empty. Check your YAML syntax.', $file));
        }

        $MandatoryAndExclusives = array_flip(['columns', 'exclude']);

        foreach ($content['tables'] as $table) {
            $diff = array_diff_key($MandatoryAndExclusives, $table);
            if (count($diff) == 0) {
                throw new \InvalidArgumentException(sprintf('The file "%s" is not valid. The tables section should contain either %s. None found.', $file, implode('" or "', $diff)));
            } elseif (count($diff) > 1) {
                throw new \InvalidArgumentException(sprintf('The file "%s" is not valid. The tables section should contain either %s. Both found.', $file, implode('" or "', $diff)));
            }
        }
    }

    private function validateOptionsEntries(array $content, string $file)
    {
        $expectedEntries = array_flip([
            "exclude_missing_tables",
        ]);

        $this->validateRequireKeys($content['options'], $file, $expectedEntries, 'options');
    }

    private function validateDataSourceEntries(array $content, string $file)
    {
        $expectedEntries = array_flip([
            "dsn",
            "user",
            "password",
            "options",
        ]);

        $this->validateRequireKeys($content['data_source'], $file, $expectedEntries, 'data_source');
    }

    private function validateRequireKeys(array $content, string $file, array $expectedEntries, string $sectionName)
    {
        $diff = array_diff_key(
            $expectedEntries,
            $content
        );
        if (count($diff) > 0) {
            throw new \InvalidArgumentException(sprintf('Missing parameters "%s" found into section %s of "%s". Check your configuration.', implode('","', array_keys($diff)), $sectionName, $file));
        }

        $diff = array_diff_key(
            $content,
            $expectedEntries
        );
        if (count($diff) > 0) {
            throw new \InvalidArgumentException(sprintf('Forbidden parameters "%s" found into section %s of "%s". Authorized values are : "%s". Check your configuration.', implode('","', array_keys($diff)), $sectionName, $file, implode('","', array_flip($expectedEntries))));
        }
    }
}