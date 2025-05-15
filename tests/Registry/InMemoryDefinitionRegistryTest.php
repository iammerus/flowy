<?php

declare(strict_types=1);

namespace Flowy\Tests\Registry;

use Flowy\Registry\InMemoryDefinitionRegistry;
use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Model\Data\ActionDefinition;
use Flowy\Model\Data\TransitionDefinition;
use Flowy\Exception\DefinitionAlreadyExistsException;
use Flowy\Exception\DefinitionNotFoundException;
use PHPUnit\Framework\TestCase;
use Flowy\Loader\AttributeDefinitionLoader;
use Flowy\Loader\YamlDefinitionLoader;

class TestAttributeDefinitionLoader extends AttributeDefinitionLoader {
    public function loadFromClass($class) { return $GLOBALS['dummy_definition']; }
}
class TestYamlDefinitionLoader extends YamlDefinitionLoader {
    public function loadFromFile($file) { return $GLOBALS['dummy_definition']; }
}

/**
 * @covers \Flowy\Registry\InMemoryDefinitionRegistry
 */
class InMemoryDefinitionRegistryTest extends TestCase
{
    private function makeDefinition(string $id, string $version): WorkflowDefinition
    {
        $action = new ActionDefinition('Some\\Action');
        $transition = new TransitionDefinition('next-step');
        $step = new StepDefinition('step1', [$action], [$transition], null, null, true);
        return new WorkflowDefinition($id, $version, 'step1', [$step]);
    }

    public function testAddAndGetDefinition(): void
    {
        $reg = new InMemoryDefinitionRegistry();
        $def = $this->makeDefinition('wf', '1.0.0');
        $reg->addDefinition($def);
        $this->assertTrue($reg->hasDefinition('wf', '1.0.0'));
        $this->assertSame($def, $reg->getDefinition('wf', '1.0.0'));
    }

    public function testAddDuplicateThrows(): void
    {
        $reg = new InMemoryDefinitionRegistry();
        $def = $this->makeDefinition('wf', '1.0.0');
        $reg->addDefinition($def);
        $this->expectException(DefinitionAlreadyExistsException::class);
        $reg->addDefinition($def);
    }

    public function testGetLatestVersion(): void
    {
        $reg = new InMemoryDefinitionRegistry();
        $def1 = $this->makeDefinition('wf', '1.0.0');
        $def2 = $this->makeDefinition('wf', '2.0.0');
        $reg->addDefinition($def1);
        $reg->addDefinition($def2);
        $this->assertSame($def2, $reg->getDefinition('wf'));
    }

    public function testFindDefinitions(): void
    {
        $reg = new InMemoryDefinitionRegistry();
        $def1 = $this->makeDefinition('wf', '1.0.0');
        $def2 = $this->makeDefinition('wf', '2.0.0');
        $def3 = $this->makeDefinition('other', '1.0.0');
        $reg->addDefinition($def1);
        $reg->addDefinition($def2);
        $reg->addDefinition($def3);
        $this->assertCount(3, $reg->findDefinitions());
        $this->assertCount(2, $reg->findDefinitions('wf'));
        $this->assertCount(1, $reg->findDefinitions('other'));
        $this->assertCount(0, $reg->findDefinitions('notfound'));
    }

    public function testGetDefinitionNotFoundThrows(): void
    {
        $reg = new InMemoryDefinitionRegistry();
        $this->expectException(DefinitionNotFoundException::class);
        $reg->getDefinition('notfound');
    }

    public function testAddDefinitionFromSourceClass(): void
    {
        $dummyDef = $this->makeDefinition('wf', '1.0.0');
        $GLOBALS['dummy_definition'] = $dummyDef;
        $reg = new InMemoryDefinitionRegistry(new TestAttributeDefinitionLoader(), new TestYamlDefinitionLoader());
        $def = $reg->addDefinitionFromSource('SomeClass', 'class');
        $this->assertSame($dummyDef, $def);
        $this->assertTrue($reg->hasDefinition('wf', '1.0.0'));
    }

    public function testAddDefinitionFromSourceYaml(): void
    {
        $dummyDef = $this->makeDefinition('wf', '1.0.0');
        $GLOBALS['dummy_definition'] = $dummyDef;
        $reg = new InMemoryDefinitionRegistry(new TestAttributeDefinitionLoader(), new TestYamlDefinitionLoader());
        $def = $reg->addDefinitionFromSource('file.yaml', 'yaml');
        $this->assertSame($dummyDef, $def);
        $this->assertTrue($reg->hasDefinition('wf', '1.0.0'));
    }
}
