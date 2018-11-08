<?php
namespace ComposerTestScenarios\Downloader;

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Downloader\DownloadManager;

use Composer\Package\PackageInterface;
use Composer\IO\IOInterface;
use Composer\Downloader\DownloaderInterface;

/**
 * NullDownloadManager returns a NullDownloader for all package types
 */
class NullDownloadManager extends DownloadManager
{
    private $io;
    private $nullDownloader;

    /**
     * @inheritdoc
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
        $this->nullDownloader = new NullDownloader();

        parent::__construct($io);
    }

    /**
     * @inheritdoc
     */
    public function setPreferSource($preferSource)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setPreferDist($preferDist)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setPreferences(array $preferences)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setOutputProgress($outputProgress)
    {
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setDownloader($type, DownloaderInterface $downloader)
    {
    }

    /**
     * @inheritdoc
     */
    public function getDownloader($type)
    {
        return $this->nullDownloader;
    }

    /**
     * @inheritdoc
     */
    public function getDownloaderForInstalledPackage(PackageInterface $package)
    {
        return $this->nullDownloader;
    }

    /**
     * @inheritdoc
     */
    public function download(PackageInterface $package, $targetDir, $preferSource = null)
    {
    }

    /**
     * @inheritdoc
     */
    public function update(PackageInterface $initial, PackageInterface $target, $targetDir)
    {
    }

    /**
     * @inheritdoc
     */
    public function remove(PackageInterface $package, $targetDir)
    {
    }
}