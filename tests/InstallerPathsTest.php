<?php
namespace ComposerTestScenarios;

use PHPUnit\Framework\TestCase;

class InstallerPathsTest extends TestCase
{
    use Fixtures;
    use RunComposer;

    /**
     * testExampleA starts with an example project that contains a default
     * scenario and one other scenario, and ensures that the alternate scenario
     * can be created and installed.
     */
    public function testInstallerPaths()
    {
        // Create the project directory to work with
        $testProjectDir = $this->createTestProject('with-installer-paths');

        // Run 'composer update' to build the scenario directories
        list($output, $status) = $this->composer('update', $testProjectDir);
        $this->assertEquals(0, $status);

        // Check the scenario directory

        $scenarioDir = \ComposerTestScenarios\Handler::scenarioLockDir('semver10', $testProjectDir);
        $this->assertDirectoryExists($scenarioDir);

        // The scenario directory should be different than the base directory
        $this->assertNotEquals($testProjectDir, $scenarioDir);

        // There shouldn't be any composer.lock
        $this->assertTrue(!file_exists($scenarioDir . '/composer.lock'));

        // Read the generated composer.json file

        $generatedComposerFile = file_get_contents($scenarioDir . '/composer.json');

        $expected = '"../../core":';
        $this->assertContains($expected, $generatedComposerFile);
    }
}
