<?php
namespace ComposerTestScenarios;

use PHPUnit\Framework\TestCase;

class ScenarioCommandsTest extends TestCase
{
    use Fixtures;

    public function scenarioCommandsTestValues()
    {
        return [
            [
                'expected',
                'example-a',
                [
                    'scenario:create',
                ],
            ],
        ];
    }

    /**
     * @dataProvider scenarioCommandsTestValues
     */
    public function testScenarioCommands(
        $expected,
        $projectTemplateName,
        $args)
    {
        $testProjectDir = $this->createTestProject($projectTemplateName);
        $this->assertEquals('', $testProjectDir);
    }
}
