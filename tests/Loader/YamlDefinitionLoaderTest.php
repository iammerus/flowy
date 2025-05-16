<?php

declare(strict_types=1);

namespace Flowy\Tests\Loader;

use Flowy\Loader\YamlDefinitionLoader;
use Flowy\Model\Data\TransitionDefinition;
use PHPUnit\Framework\TestCase;

class YamlDefinitionLoaderTest extends TestCase
{
    public function testLoadsEventBasedTransitionFromYaml(): void
    {
        $yaml = <<<YAML
id: event_workflow
version: '1.0'
name: Event Workflow
initialStepId: start
steps:
  - id: start
    transitions:
      - target: end
        event: my_event
  - id: end
YAML;
        $file = tempnam(sys_get_temp_dir(), 'flowy_yaml_');
        file_put_contents($file, $yaml);
        $loader = new YamlDefinitionLoader();
        $definition = $loader->loadFromFile($file);
        unlink($file);
        $startStep = $definition->steps['start'] ?? null;
        $this->assertNotNull($startStep, 'Start step should be loaded');
        $this->assertNotEmpty($startStep->transitions, 'Start step should have transitions');
        $transition = $startStep->transitions[0];
        $this->assertInstanceOf(TransitionDefinition::class, $transition);
        $this->assertSame('my_event', $transition->event);
        $this->assertSame('end', $transition->targetStepId);
    }
}
