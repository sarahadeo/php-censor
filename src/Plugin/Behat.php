<?php

namespace PHPCensor\Plugin;

use PHPCensor\Builder;
use PHPCensor\Model\Build;
use PHPCensor\Model\BuildError;
use PHPCensor\Plugin;

/**
 * Behat BDD Plugin
 *
 * @author Dan Cryer <dan@block8.co.uk>
 */
class Behat extends Plugin
{

  /**
    * @var string
    */
    protected $features;

    /**
     * @var string
     */
    protected $executable;

    /**
     * @return string
     */
    public static function pluginName()
    {
        return 'behat';
    }

    /**
     * {@inheritdoc}
     */
    public function __construct(Builder $builder, Build $build, array $options = [])
    {
        parent::__construct($builder, $build, $options);
        
        if (isset($options['executable'])) {
          $this->executable = $this->builder->interpolate($options['executable']);
        } else {
          $this->executable = $this->findBinary('behat');
        }

        $this->features = '';

        if (!empty($options['features'])) {
            $this->features = $options['features'];
        }
    }

    /**
     * Runs Behat tests.
     */
    public function execute()
    {
        $currentDir = getcwd();
        chdir($this->builder->buildPath);

        if (!$this->executable) {
            $this->builder->logFailure(sprintf('Could not find %s', 'behat'));

            return false;
        }

        $success = $this->builder->executeCommand($this->executable . ' %s', $this->features);
        chdir($currentDir);

        list($errorCount, $data) = $this->parseBehatOutput();

        $this->build->storeMeta((self::pluginName() . '-warnings'), $errorCount);
        $this->build->storeMeta((self::pluginName() . '-data'), $data);

        return $success;
    }

    /**
     * Parse the behat output and return details on failures
     *
     * @return array
     */
    public function parseBehatOutput()
    {
        $output = $this->builder->getLastOutput();

        $parts = explode('---', $output);

        if (count($parts) <= 1) {
            return [0, []];
        }

        $lines = explode(PHP_EOL, $parts[1]);

        $storeFailures = false;
        $data          = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line == 'Failed scenarios:') {
                $storeFailures = true;
                continue;
            }

            if (strpos($line, ':') === false) {
                $storeFailures = false;
            }

            if ($storeFailures) {
                $lineParts = explode(':', $line);
                $data[]    = [
                    'file' => $lineParts[0],
                    'line' => $lineParts[1]
                ];

                $this->build->reportError(
                    $this->builder,
                    self::pluginName(),
                    'Behat scenario failed.',
                    BuildError::SEVERITY_HIGH,
                    $lineParts[0],
                    $lineParts[1]
                );
            }
        }

        $errorCount = count($data);

        return [$errorCount, $data];
    }
}
