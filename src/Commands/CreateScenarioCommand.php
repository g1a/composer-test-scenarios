<?php

namespace ComposerTestScenarios\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "drupal:scaffold" command class.
 *
 * Downloads scaffold files and generates the autoload.php file.
 */
class CreateScenarioCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure() {
        parent::configure();
        $this
          ->setName('scenario:create')
          ->setDescription('Create a scenario.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('scenario:create ready to be implemented! :P');

        // $handler = new Handler($this->getComposer(), $this->getIO());
    }
}
