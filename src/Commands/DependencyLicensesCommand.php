<?php
namespace ComposerTestScenarios\Commands;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ComposerTestScenarios\DependencyLicenses;

/**
 * The "scenario:create" command class.
 *
 * Writes a 'scenario' directory to disk. This happens once per scenario
 * as part of the `scenario:update` process.
 */
class DependencyLicensesCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $this
          ->setName('dependency-licenses')
          ->setDescription("Add information about the licenses used by project's dependencies to its LICENSE file.");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = getcwd();
        $dependencyLicenses = new DependencyLicenses();

        $result = $dependencyLicenses->update($dir);

        if ($result) {
            $output->writeln('Updated dependency licenses.');
        }
    }
}
