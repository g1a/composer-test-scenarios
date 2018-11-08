<?php
namespace ComposerTestScenarios\Downloader;

use Composer\Installer\InstallerInterface;

use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use InvalidArgumentException;

class NullInstaller implements InstallerInterface
{
    /**
     * @inheritdoc
     */
    public function supports($packageType)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
    }

    /**
     * @inheritdoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
    }

    /**
     * @inheritdoc
     */
    public function getInstallPath(PackageInterface $package)
    {
        // TODO: Confirm this isn't a problem. Hopefully never called if
        // we claim all packages are not installed.
        return getcwd();
    }
}
