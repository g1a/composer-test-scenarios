<?php

namespace ComposerTestScenarios;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ComposerTestScenarios\Commands\DependencyLicensesCommand;
use ComposerTestScenarios\Commands\ScenarioCommand;
use ComposerTestScenarios\Commands\UpdateLockCommand;
use ComposerTestScenarios\Commands\UpdateScenarioCommand;

/**
 * List of all commands provided by this package.
 */
class CommandProvider implements CommandProviderCapability
{
      /**
       * {@inheritdoc}
       */
    public function getCommands()
    {
        return [
            new DependencyLicensesCommand(),
            new ScenarioCommand(),
            new UpdateLockCommand(),
            new UpdateScenarioCommand(),
        ];
    }
}
