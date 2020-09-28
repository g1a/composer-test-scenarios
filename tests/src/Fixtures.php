<?php
namespace ComposerTestScenarios;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

trait Fixtures
{
    protected function fixturesDir()
    {
        return dirname(__DIR__) . '/fixtures';
    }

    protected function homeDir()
    {
        return $this->fixturesDir() . '/home';
    }

    /**
     * Return the location of the System-Under-Test (this project)
     */
    protected function sut()
    {
        return dirname(dirname(__DIR__));
    }

    protected function replacements()
    {
        $branch = exec('git rev-parse --abbrev-ref HEAD');
        if ($branch == 'HEAD') {
            $branch = 'main';
        }

        return [
            '__SUT__' => $this->sut(),
            '__BRANCH__' => $branch,
        ];
    }

    protected function replaceFileContents($path, $replacements)
    {
        $contents = file_get_contents($path);
        $contents = strtr($contents, $replacements);
        file_put_contents($path, $contents);
    }

    protected function projectTemplaceDir($projectTemplateName)
    {
        return $this->fixturesDir() . '/projects/' . $projectTemplateName;
    }

    protected function createTestProject($projectTemplateName)
    {
        $projectTemplateDir = $this->projectTemplaceDir($projectTemplateName);
        $testProject = TmpDir::create('scenarios-test');
        $fs = new Filesystem();
        $fs->mirror($projectTemplateDir, $testProject);

        // Replace any replacement patterns found in the composer.json
        $replacements = $this->replacements();
        $this->replaceFileContents("$testProject/composer.json", $replacements);

        return $testProject;
    }
}
