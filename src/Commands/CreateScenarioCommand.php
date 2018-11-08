<?php
namespace ComposerTestScenarios\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The "scenario:create" command class.
 *
 * Writes a 'scenario' directory to disk. This happens once per scenario
 * as part of the `scenario:update` process.
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

        $composer = $this->getComposer();
        $target = getcwd();

        $output->writeln('Target is ' . $target);


        // $handler = new Handler($this->getComposer(), $this->getIO());
    }
}
