<?php
namespace ComposerTestScenarios\Commands;

use Composer\Composer;
use Composer\Installer;
use Composer\IO\IOInterface;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Composer\Command\BaseCommand;

use Composer\Installer\NoopInstaller;

/**
 * UpdateLockCommand updates the dependencies in a composer.lock file
 * without downloading them. This is different than `composer update --lock`,
 * which only rewrites the hash without updating dependencies.
 */
class UpdateLockCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('update:lock')
            ->setDescription('Upgrades your dependencies to the latest version according to composer.json without downloading any of them; only updates the composer.lock file.')
            ->setDefinition(array(
                new InputArgument('packages', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Packages that should be updated, if not provided all packages are.'),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Outputs the operations but will not execute anything (implicitly enables --verbose).'),
                new InputOption('no-dev', null, InputOption::VALUE_NONE, 'Disables installation of require-dev packages.'),
                new InputOption('with-dependencies', null, InputOption::VALUE_NONE, 'Add also dependencies of whitelisted packages to the whitelist, except those defined in root package.'),
                new InputOption('with-all-dependencies', null, InputOption::VALUE_NONE, 'Add also all dependencies of whitelisted packages to the whitelist, including those defined in root package.'),
                new InputOption('verbose', 'v|vv|vvv', InputOption::VALUE_NONE, 'Shows more details including new commits pulled in when updating packages.'),
                new InputOption('ignore-platform-reqs', null, InputOption::VALUE_NONE, 'Ignore platform requirements (php & ext- packages).'),
                new InputOption('prefer-stable', null, InputOption::VALUE_NONE, 'Prefer stable versions of dependencies.'),
                new InputOption('prefer-lowest', null, InputOption::VALUE_NONE, 'Prefer lowest versions of dependencies.'),
                new InputOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive interface with autocompletion to select the packages to update.'),
                new InputOption('root-reqs', null, InputOption::VALUE_NONE, 'Restricts the update to your first degree dependencies.'),
            ))
            ->setHelp(
                <<<EOT
The <info>update:lock</info> command reads the composer.json file from the
current directory, processes it, and updates all the dependencies, recording
the results in the composer.lock file.

<info>php composer.phar update:lock</info>

Note that runnning this command may bring your composer.lock file out of date
with your vendor directory, analogous to the situation where you pull a new
composer.lock file from a version control system. Run <info>composer install</info>
to bring your vendor directory up-to-date with your composer.lock file.

Note, however, that the typical use-case for this command are situations where
the vendor directory does not exist and is not needed.

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = $this->getIO();

        $composer = $this->getComposer(true, $input->getOption('no-plugins'));

        $packages = $input->getArgument('packages');

        if ($input->getOption('root-reqs')) {
            $require = array_keys($composer->getPackage()->getRequires());
            if (!$input->getOption('no-dev')) {
                $requireDev = array_keys($composer->getPackage()->getDevRequires());
                $require = array_merge($require, $requireDev);
            }

            if (!empty($packages)) {
                $packages = array_intersect($packages, $require);
            } else {
                $packages = $require;
            }
        }

        $noop = new NoopInstaller();

        try
        {
            $composer->getInstallationManager()->addInstaller($noop);

            $install = Installer::create($io, $composer);

            $install
                ->setDryRun($input->getOption('dry-run'))
                ->setVerbose($input->getOption('verbose'))
                ->setPreferSource(false)
                ->setPreferDist(true)
                ->setDevMode(!$input->getOption('no-dev'))
                ->setDumpAutoloader(false)
                ->setRunScripts(false)
                ->setSkipSuggest(true)
                ->setOptimizeAutoloader(false)
                ->setClassMapAuthoritative(false)
                ->setApcuAutoloader(false)
                ->setUpdate(true)
                ->setUpdateWhitelist($packages)
                ->setWhitelistTransitiveDependencies($input->getOption('with-dependencies'))
                ->setWhitelistAllDependencies($input->getOption('with-all-dependencies'))
                ->setIgnorePlatformRequirements($input->getOption('ignore-platform-reqs'))
                ->setPreferStable($input->getOption('prefer-stable'))
                ->setPreferLowest($input->getOption('prefer-lowest'))
                ->disablePlugins()
                ->setExecuteOperations(false)
                ->setWriteLock(true)
            ;

            $result = $install->run();
        } catch(Exception $e) {
            throw $e;
        } finally {
            $composer->getInstallationManager()->removeInstaller($noop);
        }
        return $result;
    }
}
