<?php

namespace ComposerTestScenarios;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;
use Composer\Plugin\CommandEvent;
use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Semver\Semver;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;
use Composer\Factory;

/**
 * Core class of the plugin, contains all logic which files should be fetched.
 */
class Handler
{

    const DEFINE_SCENARIOS_CMD = 'define-scenarios-cmd';

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * @var bool
     *
     * A boolean indicating if progress should be displayed.
     */
    protected $progress;

    /**
     * @var string
     */
    protected $composer_home;

    /**
     * Handler constructor.
     *
     * @param \Composer\Composer $composer
     * @param \Composer\IO\IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->progress = true;
        $this->composer_home = getenv('COMPOSER_HOME');
    }

    /**
     * Call any 'define-scenario-cmd' that may be installed.
     */
    public function callDefineScenariosCmd()
    {
        $dispatcher = new EventDispatcher($this->composer, $this->io);
        $dispatcher->dispatch(self::DEFINE_SCENARIOS_CMD);
    }

    /**
     * Get the command options.
     *
     * @param \Composer\Plugin\CommandEvent $event
     */
    public function onCmdBeginsEvent(CommandEvent $event)
    {
        if ($event->getInput()->hasOption('no-progress')) {
            $this->progress = !($event->getInput()->getOption('no-progress'));
        } else {
            $this->progress = true;
        }
    }

    /**
     * Marks scaffolding to be processed after an install or update command.
     *
     * @param \Composer\Installer\PackageEvent $event
     */
    public function onPostPackageEvent(PackageEvent $event)
    {
        $package = $this->getCorePackage($event->getOperation());
        if ($package) {
            // By explicitly setting the core package, the onPostCmdEvent() will
            // process the scaffolding automatically.
            $this->drupalCorePackage = $package;
        }
    }

    /**
     * Do our scenarios work.
     *
     * @param \Composer\Script\Event $event
     */
    public function scenariosEvent(Event $event)
    {
        $dir = getcwd();
        $this->updateScenarios($dir);
    }

    public function updateScenarios($dir)
    {
        // Save data in vendor that might be overwritten by scenario creation
        $save = $this->saveVendorState($dir);

        try {
            $this->scenariosFromExtra($dir);
            $this->callDefineScenariosCmd();
        } catch (\Exception $e) {
            $this->restoreVendorState($dir, $save);
            throw $e;
        }

        // Restore saved data in vendor
        $this->restoreVendorState($dir, $save);
    }

    protected function saveVendorState($dir)
    {
        $path = $this->pathToInstalled($dir);
        if (file_exists($path)) {
            return file_get_contents($path);
        }
    }

    protected function restoreVendorState($dir, $save)
    {
        if (empty($save)) {
            return;
        }
        $path = $this->pathToInstalled($dir);
        if (file_exists($path)) {
            return file_put_contents($path, $save);
        }
    }

    protected function pathToInstalled($dir)
    {
        return "$dir/vendor/composer/installed.json";
    }

    public function installScenario($scenario, $dependencies, $dir)
    {
        $scenarioDir = static::scenarioLockDir($scenario, $dir);
        if (!is_dir($scenarioDir)) {
            throw new \Exception("The scenario '$scenario' does not exist.");
        }
        list($scenarioCommand, $extraOptions) = $this->determineDependenciesCommand($dependencies);

        // print("composer -n --working-dir=$scenarioDir validate --no-check-all --ansi\n");
        passthru("composer -n --working-dir=$scenarioDir validate --no-check-all --ansi", $status);
        // print("composer -n --working-dir=$scenarioDir $scenarioCommand $extraOptions --prefer-dist --no-scripts\n");
        if ($status != 0) {
            return $status;
        }
        passthru("composer -n --working-dir=$scenarioDir $scenarioCommand $extraOptions --prefer-dist --no-scripts", $status);
        return $status;
    }

    protected function determineDependenciesCommand($dependencies)
    {
        $dependency_map = [
            'highest' => ['update', ''],
            'lowest' => ['update', '--prefer-lowest'],
            'default' => ['install', ''],
        ];

        if (($dependencies == 'install') || ($dependencies == 'lock')) {
            $dependencies = 'default';
        }

        if (!array_key_exists($dependencies, $dependency_map)) {
            throw new \Exception("The dependencies option $dependencies is not valid.");
        }

        return $dependency_map[$dependencies];
    }

    /**
     * scenariosFromExtra pulls our scenario definitions from the 'extras'
     * section of the composer.json file, and create a scenario for each
     * one defined therein.
     */
    protected function scenariosFromExtra($dir)
    {
        $scenarios = $this->getScenarioDefinitions();
        if (empty($scenarios)) {
            $this->io->write("No scenarios in 'extra' section.");
            return;
        }

        $composerJsonData = $this->readComposerJson($dir);

        // Create each scenaro
        foreach ($scenarios as $scenario => $scenarioData) {
            $this->createScenario($scenario, $scenarioData, $composerJsonData, $dir);
        }

        $this->copyInstallScenarioScript($dir);
    }

    protected function createScenario($scenario, $scenarioData, $composerJsonData, $dir)
    {
        $this->io->write("Create scenario '$scenario'.");
        list($scenarioData, $scenarioOptions) = $this->applyScenarioData($composerJsonData, $scenarioData);

        $scenarioDir = $this->createScenarioDir($scenario, $dir);

        $scenarioData = $this->adjustPaths($scenarioData);
        $this->writeComposerData($scenarioData, $scenarioDir);
        $this->createScenarioLockfile($scenarioDir, $scenarioOptions['create-lockfile'], $dir);
    }

    protected function createScenarioLockfile($scenarioDir, $create_lockfile, $dir)
    {
        $gitignore = ['vendor'];

        $this->composer('config', $scenarioDir, ['vendor-dir', '../../vendor']);

        if ($create_lockfile) {
            putenv("COMPOSER_HOME=$dir");
            putenv("COMPOSER_HTACCESS_PROTECT=0");
            if (!empty($this->composer_home)) {
                putenv("COMPOSER_CACHE_DIR={$this->composer_home}/cache");
            }
            $this->composer('update:lock', $scenarioDir, []);

            // $this->composer('update', $scenarioDir, []);
        } else {
            $gitignore[] = 'composer.lock';
        }

        file_put_contents($scenarioDir . '/.gitignore', implode("\n", $gitignore));
    }

    protected function copyInstallScenarioScript($dir)
    {
        $installScenarioScript = file_get_contents(__DIR__ . '/../scripts/install-scenario');
        file_put_contents($dir . '/install', $installScenarioScript);
    }

    protected function setupScenario($scenario, $scenarioData, $dir)
    {
        $composerJsonData = $this->readComposerJson($dir);
        list($scenarioData, $scenarioOptions) = $this->applyScenarioData($composerJsonData, $scenarioData);
        $scenarioDir = $this->createScenarioDir($scenario, $dir);
        $scenarioData = $this->adjustPaths($scenarioData);
        $this->writeComposerData($scenarioData, $scenarioDir);
    }

    protected function createScenarioDir($scenario, $dir)
    {
        $fs = new SymfonyFilesystem();

        $scenarioDir = static::scenarioLockDir($scenario, $dir);
        $fs->mkdir($scenarioDir);

        return $scenarioDir;
    }

    public static function scenarioLockDir($scenario, $dir)
    {
        if ($scenario == 'default') {
            return $dir;
        }
        return "$dir/.scenarios.lock/$scenario";
    }

    protected function adjustPaths($composerData)
    {
        if (isset($composerData['autoload'])) {
            $composerData['autoload'] = $this->fixAutoloadPaths($composerData['autoload']);
        }
        if (isset($composerData['autoload-dev'])) {
            $composerData['autoload-dev'] = $this->fixAutoloadPaths($composerData['autoload-dev']);
        }

        if (isset($composerData['extra']['installer-paths'])) {
            $composerData['extra']['installer-paths'] = $this->fixInstallerPaths($composerData['extra']['installer-paths']);
        }

        return $composerData;
    }

    /**
     *
     *     "autoload": {
     *         "psr-4": {
     *             "ComposerTestScenarios\\": "src/"
     *         }
     *     },
     */
    protected function fixAutoloadPaths($autoloadData)
    {
        // For now only do psr-4
        if (!isset($autoloadData['psr-4'])) {
            return $autoloadData;
        }

        $psr4 = [];

        foreach ($autoloadData['psr-4'] as $namespace => $path) {
            $psr4[$namespace] = "../../$path";
        }

        $autoloadData['psr-4'] = $psr4;

        return $autoloadData;
    }

    /**
     *         "installer-paths": {
     *             "core": ["type:drupal-core"],
     *             "modules/contrib/{$name}": ["drupal/devel"],
     *             ...
     *         }
     */
    protected function fixInstallerPaths($installerPathData)
    {
        $result = [];

        foreach ($installerPathData as $path => $types) {
            $result["../../$path"] = $types;
        }

        return $result;
    }

    protected function writeComposerData($scenarioData, $scenarioDir)
    {
        file_put_contents($scenarioDir . '/composer.json', json_encode($scenarioData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function composer($command, $dir, $args)
    {
        $escapedArgs = implode(' ', array_map(function ($item) {
            return escapeshellarg($item);
        }, $args));
        $cmd = "composer -n $command --working-dir=$dir " . $escapedArgs;

        if ($this->io->isVerbose()) {
            $this->io->write(">> composer $command $escapedArgs");
        }

        $output = '';
        if ($this->io->isVeryVerbose()) {
            passthru($cmd, $status);
        } else {
            exec($cmd . ' 2>&1', $output, $status);
            $output = implode("\n", $output);
        }

        if ($status) {
            throw new \Exception("Error $status from command '$cmd'\n\nOutput:\n\n$output");
        }
    }

    protected function applyScenarioData($composerJsonData, $scenarioData)
    {
        // Look up the scenario options
        $scenarioOptions = isset($scenarioData['scenario-options']) ? $scenarioData['scenario-options'] : [];
        $scenarioOptions = array_map([$this, 'fixScenarioOptions'], $scenarioOptions);

        // Extract the 'remove' and 'remove-dev' elements from the scenario
        $remove = isset($scenarioData['remove']) ? $scenarioData['remove'] : [];
        $removeDev = isset($scenarioData['remove-dev']) ? $scenarioData['remove-dev'] : [];
        unset($scenarioData['remove']);
        unset($scenarioData['remove-dev']);
        unset($scenarioData['scenario-options']);

        $mergeData = $composerJsonData;

        // Remove the things we don't need
        unset($mergeData['extra']['scenarios']);
        foreach ($remove as $item) {
            unset($mergeData['require'][$item]);
        }
        foreach (array_merge($remove, $removeDev) as $item) {
            unset($mergeData['require-dev'][$item]);
        }

        // Combine our scenario data with the original composer.json data
        $result = $mergeData + $scenarioData;
        foreach ($scenarioData as $key => $data) {
            $result[$key] = $this->combine($key, $result, $data);
        }

        return [ array_filter($result), $scenarioOptions + $this->defaultScenarioOptions() ];
    }

    protected function fixScenarioOptions($item)
    {
        if ($item === "true") {
            return true;
        }
        if ($item === "false") {
            return false;
        }
        return $item;
    }

    protected function defaultScenarioOptions()
    {
        return [
            'create-lockfile' => true,
        ];
    }

    protected function combine($key, $result, $data)
    {
        if (!is_array($data) || !isset($result[$key])) {
            return $data;
        }
        return array_filter($data + $result[$key]);
    }

    protected function getScenarioDefinitions()
    {
        $extra = $this->composer->getPackage()->getExtra() + ['scenarios' => []];
        return $extra['scenarios'];
    }

    protected function readComposerJson($dir)
    {
        $path = $dir . '/' . Factory::getComposerFile();

        $contents = file_get_contents($path);
        $data = json_decode($contents, true);

        return $data;
    }
}
