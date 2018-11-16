<?php
namespace ComposerTestScenarios\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use ComposerTestScenarios\Handler;

/**
 * The "scenarios" command class.
 *
 * Install a scenario. This action is typically done from a test service file.
 */
class ScenarioCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
          ->setName('scenario')
          ->setDescription('Install a scenario.')
          ->setDefinition(
              new InputDefinition([
                    new InputArgument('scenario', InputArgument::OPTIONAL),
                    new InputArgument('dependencies', InputArgument::OPTIONAL),
                ])
          );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $scenario_name = $input->getArgument('scenario') ?: 'default';
        $dependencies = $input->getArgument('dependencies') ?: 'install';
        $dir = getcwd();
        $handler = new Handler($this->getComposer(), $this->getIO());
        $status = $handler->installScenario($scenario_name, $dependencies, $dir);

        if ($status != 0) {
            return $status;
        }

        // If called from a CI context, print out some extra information about
        // what we just installed.
        if (getenv("CI")) {
            passthru("composer -n --working-dir=$dir info");
        }
    }
}
