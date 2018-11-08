<?php
namespace ComposerTestScenarios\Downloader;

use Composer\Downloader\DownloaderInterface;

use Composer\Package\PackageInterface;

/**
 * NullDownloader skips downloading.
 */
class NullDownloader implements DownloaderInterface
{
    /**
     * @inheritdoc
     */
    public function getInstallationSource()
    {
        return 'dist';
    }

    /**
     * @inheritdoc
     */
    public function download(PackageInterface $package, $path)
    {
    }

    /**
     * @inheritdoc
     */
    public function update(PackageInterface $initial, PackageInterface $target, $path)
    {
    }

    /**
     * @inheritdoc
     */
    public function remove(PackageInterface $package, $path)
    {
    }

    /**
     * @inheritdoc
     */
    public function setOutputProgress($outputProgress)
    {
    }
}
