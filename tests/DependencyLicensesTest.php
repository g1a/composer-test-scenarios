<?php
namespace ComposerTestScenarios;

use PHPUnit\Framework\TestCase;

class DependencyLicensesTest extends TestCase
{
    use Fixtures;
    use RunComposer;

    /**
     * testExampleA starts with an example project that contains a default
     * scenario and one other scenario, and ensures that the alternate scenario
     * can be created and installed.
     */
    public function testDependencyLicenses()
    {
        // Create the project directory to work with
        $testProjectDir = $this->createTestProject('with-license');

        // Run 'composer update' to load our dependency-licenses command
        list($output, $status) = $this->composer('update', $testProjectDir);
        $this->assertNotContains('Your requirements could not be resolved to an installable set of packages.', $output);
        $this->assertEquals(0, $status, "Could not update $testProjectDir");

        // Check the scenario directory

        $scenarioDir = \ComposerTestScenarios\Handler::scenarioLockDir($testProjectDir, 'semver30');
        $this->assertTrue(is_dir($scenarioDir));

        // The scenario directory should be different than the base directory
        $this->assertNotEquals($testProjectDir, $scenarioDir);

        // There shouldn't be any composer.lock
        $this->assertTrue(!file_exists($scenarioDir . '/composer.lock'));

        // Read the updated LICENSE file

        $licensePath = "$testProjectDir/LICENSE";
        $this->assertFileExists($licensePath);
        $licenseContents = file_get_contents($licensePath);

        // Dating is hard. After the year 9999, this test will fail. :P
        // Until then, we will detect that the "Copyright (c) 2017" note
        // is converted into "Copyright (c) 2017-2018" or whatever the current
        // year is right now.
        $this->assertRegExp('#Copyright \(c\) 2017-[0-9][0-9][0-9][0-9] #', $licenseContents);

        // Make sure that we have license info for composer/semver too
        $this->assertRegExp('#composer/semver *[0-9.]* *MIT#', $licenseContents);

        // Run the 'dependency-licenses' command explicitly and ensure nothing changes
        list($output, $status) = $this->composer('dependency-licenses', $testProjectDir);
        $this->assertContains('Updated dependency licenses.', $output);
        $this->assertEquals(0, $status);

        $updatedLicenseContents = file_get_contents($licensePath);
        $this->assertEquals($licenseContents, $updatedLicenseContents);
    }
}
