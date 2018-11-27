<?php
namespace ComposerTestScenarios;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

/**
 * TmpDir contains a collection of methods to help tests manage their
 * temporary directories.
 */
class TmpDir
{
    protected static $tmpDirs = [];
    protected static $retain = true;

    /**
     * create will create a new empty temporary directory. This directory
     * will automatically be deleted when the program exist.
     *
     * @param string $tmpName Prefix for the temp directory name.
     * @return string full path to the temporary directory.
     */
    public static function create($tmpName = 'tmp-test-dir', $baseDir = false)
    {
        $fs = new Filesystem();
        if (!$baseDir) {
            $baseDir = sys_get_temp_dir();
        }
        $tmpDir = tempnam($baseDir, $tmpName);
        $fs->remove($tmpDir);
        $fs->mkdir($tmpDir);
        if (empty(static::$tmpDirs)) {
            register_shutdown_function(['\ComposerTestScenarios\TmpDir', 'cleanup']);
        }
        static::$tmpDirs[] = $tmpDir;
        return $tmpDir;
    }

    /**
     * retain controls whether the temporary directories created by tests
     * should be retained ($value == true) or deleted ($value == false).
     */
    public static function retain($value = true)
    {
        static::$retain = $value;
    }

    /**
     * cleanup removes all of the temporary directories that were created
     * by this class. It is called automatically via the shutdown handler.
     */
    public static function cleanup()
    {
        // If someone requested that we retain our temp data,
        // then omit removing our tmp directories
        if (static::$retain) {
            return;
        }

        $fs = new Filesystem();
        foreach (static::$tmpDirs as $tmpDir) {
            $fs->remove($tmpDir);
        }
        static::$tmpDirs = [];
    }
}
