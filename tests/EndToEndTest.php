<?php
namespace ComposerTestScenarios;

use PHPUnit\Framework\TestCase;

class EndToEndTest extends TestCase
{
    use Fixtures;
    use RunComposer;

    public function scenarioCommandsTestValues()
    {
        return [
            [
                [
                    'semver10' => ['#^composer/semver *1.0.0#'],
                    'default' => ['#^composer/semver *1.4.2#'],
                ],
                'example-a',
            ],
        ];
    }

    /**
     * @dataProvider scenarioCommandsTestValues
     */
    public function testEndToEnd(
        $expected,
        $projectTemplateName)
    {
        // Create the project directory to work with
        $testProjectDir = $this->createTestProject($projectTemplateName);

        // Run 'composer update' to build the scenario directories
        list($output, $status) = $this->composer('update', $testProjectDir);
        $this->assertEquals(0, $status);

        // Check the expectations for each scenario
        foreach ($expected as $scenario => $expectations) {
            $scenarioDir = \ComposerTestScenarios\Handler::scenarioLockDir($scenario, $testProjectDir);
            $this->assertDirectoryExists($scenarioDir);

            list($output, $status) = $this->composer('scenario', $testProjectDir, [$scenario]);
            $this->assertEquals(0, $status);

            list($output, $status) = $this->composer('info', $testProjectDir);
            $this->assertEquals(0, $status);
            foreach ($expectations as $item) {
                $this->assertRegExp($item, $output);
            }
        }

        // Try to load a scenario that does not exist
        list($output, $status) = $this->composer('scenario', $testProjectDir, ['no-such-scenario']);
        $this->assertEquals(1, $status);
    }
}
