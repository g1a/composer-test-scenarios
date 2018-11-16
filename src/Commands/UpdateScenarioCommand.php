<?php
namespace ComposerTestScenarios\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ComposerTestScenarios\Handler;

/**
 * The "scenarios:update" command class.
 *
 * Updates all scenarios. This action happens automatically after
 * every run of `composer update`.
 */
class UpdateScenarioCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
          ->setName('scenario:update')
          ->setDescription('Update all scenarios.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = getcwd();
        $handler = new Handler($this->getComposer(), $this->getIO());
        $handler->updateScenarios($dir);
    }
}
