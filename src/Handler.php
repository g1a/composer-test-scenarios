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
        $this->progress = TRUE;
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
      }
      else {
          $this->progress = TRUE;
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
        $this->scenariosFromExtra($dir);
        $this->callDefineScenariosCmd();
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

        if ($create_lockfile) {
            $this->composer('config', $scenarioDir, ['vendor-dir', 'vendor']);
            putenv("COMPOSER_HOME=$dir");
            putenv("COMPOSER_HTACCESS_PROTECT=0");
            putenv("COMPOSER_CACHE_DIR={$this->composer_home}/cache");
            $this->composer('update:lock', $scenarioDir, []);
        }
        else {
            $gitignore[] = 'composer.lock';
        }
        $this->composer('config', $scenarioDir, ['vendor-dir', '../../vendor']);

        file_put_contents($scenarioDir . '/.gitignore', implode("\n", $gitignore));
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

        $scenarioDir = $dir . '/.scenarios.lock';
        $fs->mkdir($scenarioDir);
        $scenarioDir .= "/$scenario";
        $fs->mkdir($scenarioDir);

        return $scenarioDir;
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
        $escapedArgs = implode(' ', array_map(function ($item) { return escapeshellarg($item); }, $args));
        $cmd = "composer -n $command --working-dir=$dir " . $escapedArgs;

        if ($this->io->isVerbose()) {
            $this->io->write(">> composer $command $escapedArgs");
        }

        $output = '';
        if ($this->io->isVeryVerbose()) {
            passthru($cmd, $status);
        }
        else {
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

    protected function getScenarioDefinitions() {
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
