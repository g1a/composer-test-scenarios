<?php
namespace ComposerTestScenarios;

use PHPUnit\Framework\TestCase;

class SimpleProjectTest extends TestCase
{
    use Fixtures;
    use RunComposer;

    /**
     * testExampleA starts with an example project that contains a default
     * scenario and one other scenario, and ensures that the alternate scenario
     * can be created and installed.
     */
    public function testSimpleProject()
    {
        // Create the project directory to work with
        $testProjectDir = $this->createTestProject('simple-project');

        // Run 'composer update' to build the scenario directories
        list($output, $status) = $this->composer('update', $testProjectDir);
        $this->assertEquals(0, $status);

        // Test scenario 'semver10'

        $scenarioDir = \ComposerTestScenarios\Handler::scenarioLockDir('semver10', $testProjectDir);
        $this->assertDirectoryExists($scenarioDir);

        list($output, $status) = $this->composer('scenario', $testProjectDir, ['semver10']);
        $this->assertEquals(0, $status);

        list($output, $status) = $this->composer('info', $testProjectDir);
        $this->assertEquals(0, $status);
        $this->assertRegExp('#^composer/semver *1.0.0#', $output);

        // Return to the 'default' scenario

        $scenarioDir = \ComposerTestScenarios\Handler::scenarioLockDir('default', $testProjectDir);
        $this->assertDirectoryExists($scenarioDir);

        list($output, $status) = $this->composer('scenario', $testProjectDir, ['default']);
        $this->assertEquals(0, $status);

        list($output, $status) = $this->composer('info', $testProjectDir);
        $this->assertEquals(0, $status);
        $this->assertRegExp('#^composer/semver *1.4.2#', $output);

        // Try to load a scenario that does not exist

        list($output, $status) = $this->composer('scenario', $testProjectDir, ['no-such-scenario']);
        $this->assertEquals(1, $status);
    }
}
