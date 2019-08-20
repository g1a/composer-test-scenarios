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
        if (empty($this->composer_home)) {
            $this->composer_home = getenv('HOME') . '/.composer';
        }
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

    public function dependencyLicenses($dir)
    {
        $dependencyLicenses = new DependencyLicenses();

        // Ignore errors
        $dependencyLicenses->update($dir);
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
        $scenarioDir = static::scenarioLockDir($dir, $scenario);
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
        passthru("composer -n --working-dir=$scenarioDir $scenarioCommand $extraOptions --prefer-dist", $status);
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
        $this->autoUpgrade($dir);
        $scenarios = $this->getScenarioDefinitions();
        $scenarioOptions = $this->getScenarioOptions();
        if (empty($scenarios)) {
            $this->io->write("No scenarios in 'extra' section.");
            return;
        }

        if (empty($scenarioOptions['no-install-script'])) {
            $this->copyInstallScenarioScript($dir);
        }

        $composerJsonData = $this->readComposerJson($dir);

        // Create each scenaro
        foreach ($scenarios as $scenario => $scenarioData) {
            $this->createScenario($scenario, $scenarioData + ['scenario-options' => $scenarioOptions], $composerJsonData, $dir);
        }

        if (!empty($scenarioOptions['dependency-licenses'])) {
            $this->dependencyLicenses($dir);
        }
    }

    /**
     * autoUpgrade will convert from an older version of Composer Test Scenarios
     * to a 3.x version. At the moment, only minor adjustments are made. The
     * main work -- converting from create-scenario scripts to scenario data
     * is not done yet.
     */
    protected function autoUpgrade($dir)
    {
        if (!is_dir("$dir/scenarios")) {
            return;
        }

        $fs = new SymfonyFilesystem();
        $fs->remove("$dir/scenarios");

        $travisYmlPath = "$dir/.travis.yml";
        if (!file_exists($travisYmlPath)) {
            return;
        }
        $travisContents = file_get_contents($travisYmlPath);
        $travisContents = str_replace('scenarios/install', '.scenarios.lock/install', $travisContents);
        file_put_contents($travisYmlPath, $travisContents);
    }

    protected function createScenario($scenario, $scenarioData, $composerJsonData, $dir)
    {
        $this->io->write("Create scenario '$scenario'.");
        list($scenarioData, $scenarioOptions) = $this->applyScenarioData($composerJsonData, $scenarioData);

        $scenarioDir = $this->createScenarioDir($dir, $scenario);

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
            putenv("COMPOSER_CACHE_DIR={$this->composer_home}/cache");
            $this->composer('update:lock', $scenarioDir, []);
        } else {
            $gitignore[] = 'composer.lock';
        }

        file_put_contents($scenarioDir . '/.gitignore', implode("\n", $gitignore));
    }

    protected function copyInstallScenarioScript($dir)
    {
        $scenarioDir = $this->createScenarioDir($dir);
        $installScriptPath = "$scenarioDir/install";
        $installScenarioScript = file_get_contents(__DIR__ . '/../scripts/install-scenario');
        file_put_contents($installScriptPath, $installScenarioScript);
        chmod($installScriptPath, 0755);
    }

    protected function setupScenario($scenario, $scenarioData, $dir)
    {
        $composerJsonData = $this->readComposerJson($dir);
        list($scenarioData, $scenarioOptions) = $this->applyScenarioData($composerJsonData, $scenarioData);
        $scenarioDir = $this->createScenarioDir($dir, $scenario);
        $scenarioData = $this->adjustPaths($scenarioData);
        $this->writeComposerData($scenarioData, $scenarioDir);
    }

    protected function createScenarioDir($dir, $scenario = '')
    {
        $fs = new SymfonyFilesystem();

        $scenarioDir = static::scenarioLockDir($dir, $scenario);
        if (!file_exists($scenarioDir)) {
            $fs->mkdir($scenarioDir);
        }

        return $scenarioDir;
    }

    public static function scenarioLockDir($dir, $scenario = '')
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

        if (isset($composerData['extra']['patches'])) {
            $composerData['extra']['patches'] = $this->fixPatchesPaths($composerData['extra']['patches']);
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
        $fixAutoloadFn = function ($path) {
            return "../../$path";
        };

        foreach (['psr-4', 'files', 'classmap'] as $key) {
            if (isset($autoloadData[$key])) {
                $autoloadData[$key] = array_map($fixAutoloadFn, $autoloadData[$key]);
            }
        }

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

    /**
     *   "patches": {
     *       "drupal/field_collection": {
     *           "HTTP patch": "https://www.drupal.org/files/issues/field_collection-null-values-on-editing-2662210-2-7.x-1.x.patch",
     *           "Local patch": "patches/field_collectiion.patch"
     *       }
     *   }
     */
    protected function fixPatchesPaths($patchesPathData)
    {
        $result = [];

        foreach ($patchesPathData as $package => $patches) {
            foreach ($patches as $info => $patch) {
                if (filter_var($patch, FILTER_VALIDATE_URL)) {
                    $result['package'][$info] = $path;
                }
                else {
                    $result['package'][$info] = "../../$path";
                }
            }
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

    protected function getScenarioOptions()
    {
        $extra = $this->composer->getPackage()->getExtra() + ['scenario-options' => []];
        return array_filter($extra['scenario-options'] + $this->defaultGlobalOptions());
    }

    protected function defaultGlobalOptions()
    {
        return [
            'no-install-script' => false,
            'dependency-licenses' => true,
        ];
    }

    protected function readComposerJson($dir)
    {
        $path = $dir . '/' . Factory::getComposerFile();

        $contents = file_get_contents($path);
        $data = json_decode($contents, true);

        return $data;
    }
}
