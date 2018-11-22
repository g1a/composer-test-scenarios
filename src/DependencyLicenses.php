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
 * DependencyLicenses takes the information about the specific version
 * and license of each from the project's dependences using the
 * `composer licenses` command and writes it into the LICENSE file.
 */
class DependencyLicenses
{
    public function update($dir, $licenseFile = 'LICENSE')
    {
        $licensePath = "$dir/$licenseFile";

        if (!file_exists($licensePath)) {
            return false;
        }

        $licenseContents = file_get_contents($licensePath);

        $licenseContents = $this->updateLicenses($licenseContents, $dir);
        $licenseContents = $this->updateCopyright($licenseContents);

        file_put_contents($licensePath, $licenseContents);

        return true;
    }

    protected function updateLicenses($licenseContents, $dir)
    {
        exec("composer -n --working-dir=$dir licenses --no-dev", $output, $status);
        if ($status != 0) {
            return $licenseContents;
        }

        // Remove existing 'DEPENDENCY LISCENSES' block, if any
        $licenseContents = rtrim(preg_replace('#\n*DEPENDENCY LICENSES.*#ms', '', $licenseContents));

        $dependencyLicenseInfo = implode("\n", $output);
        $dependencyLicenseInfo = preg_replace("#^.*\n\n#s", '', $dependencyLicenseInfo);

        // Append information about dependency licenses
        $licenseContents .= rtrim("\n\nDEPENDENCY LICENSES:\n\n$dependencyLicenseInfo");

        return $licenseContents;
    }

    protected function updateCopyright($licenseContents)
    {
        $year = date('Y');

        // Replace 'Copyright nnnn' or 'Copyright nnnn-mmmm' with 'Copyright nnnn-YEAR'
        $licenseContents = preg_replace('#(^ *Copyright [^0-9]*[0-9][0-9][0-9][0-9])([0-9-]*)#m', "\$1-$year", $licenseContents);

        // Replace 'Copyright YEAR-YEAR' with 'Copyright YEAR'
        $licenseContents = preg_replace("#(^ *Copyright [^0-9]*$year)(-$year)#m", "\$1", $licenseContents);

        return $licenseContents;
    }
}
