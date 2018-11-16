<?php

namespace ComposerTestScenarios;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use ComposerTestScenarios\Commands\CreateScenarioCommand;
use ComposerTestScenarios\Commands\ScenarioCommand;
use ComposerTestScenarios\Commands\UpdateScenarioCommand;
use ComposerTestScenarios\Commands\UpdateLockCommand;

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
            new CreateScenarioCommand(),
            new ScenarioCommand(),
            new UpdateScenarioCommand(),
            new UpdateLockCommand(),
        ];
    }
}
