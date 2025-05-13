<?php

declare(strict_types=1);

namespace Flowy\Tests\Persistence\Doctrine;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Flowy\Persistence\Doctrine\DoctrinePersistenceAdapter;
use Flowy\Persistence\Doctrine\Entity\WorkflowInstance;

/**
 * @covers \Flowy\Persistence\Doctrine\DoctrinePersistenceAdapter
 */
class DoctrinePersistenceAdapterTest extends TestCase
{
    private EntityManager $em;
    private DoctrinePersistenceAdapter $adapter;

    protected function setUp(): void
    {
        $config = Setup::createAttributeMetadataConfiguration([
            __DIR__ . '/../../../src/Persistence/Doctrine/Entity'
        ], true, null, null, false);
        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
        $this->em = EntityManager::create($conn, $config);
        $schemaTool = new SchemaTool($this->em);
        $classes = [$this->em->getClassMetadata(WorkflowInstance::class)];
        $schemaTool->createSchema($classes);
        $this->adapter = new DoctrinePersistenceAdapter($this->em);
    }

    /**
     * Helper to create a WorkflowInstanceIdInterface implementation.
     */
    private function createWorkflowInstanceId(string $id): \Flowy\Model\WorkflowInstanceIdInterface
    {
        return new class($id) implements \Flowy\Model\WorkflowInstanceIdInterface {
            private string $id;
            public function __construct(string $id) { $this->id = $id; }
            public function __toString(): string { return $this->id; }
        };
    }

    /**
     * Helper to create a WorkflowInstance with sensible defaults.
     */
    private function createWorkflowInstance(
        string $id = 'test-uuid-1',
        string $definitionId = 'order_fulfillment',
        string $definitionVersion = '1.0.0',
        $status = null,
        array $contextData = ['foo' => 'bar', 'count' => 42],
        ?string $businessKey = 'BUSINESS-123',
        ?string $currentStepId = 'step1',
        array $history = [],
        ?string $errorDetails = null,
        int $retryAttempts = 0,
        int $version = 1
    ): \Flowy\Model\WorkflowInstance {
        $statusClass = \Flowy\Model\WorkflowStatus::class;
        $contextClass = \Flowy\Context\WorkflowContext::class;
        $now = new \DateTimeImmutable();
        return new \Flowy\Model\WorkflowInstance(
            $this->createWorkflowInstanceId($id),
            $definitionId,
            $definitionVersion,
            $status ?? $statusClass::PENDING,
            $contextClass::fromArray($contextData),
            $now,
            $now,
            $businessKey,
            $currentStepId,
            $history,
            $errorDetails,
            $retryAttempts,
            $version,
            null,
            null,
            null
        );
    }

    public function testSaveAndFindWorkflowInstance(): void
    {
        // Arrange: create a domain WorkflowInstance with all required value objects
        $idClass = \Flowy\Model\WorkflowInstanceIdInterface::class;
        $contextClass = \Flowy\Context\WorkflowContext::class;
        $statusClass = \Flowy\Model\WorkflowStatus::class;
        $id = new class('test-uuid-1') implements \Flowy\Model\WorkflowInstanceIdInterface {
            private string $id;
            public function __construct(string $id) { $this->id = $id; }
            public function __toString(): string { return $this->id; }
        };
        $context = $contextClass::fromArray(['foo' => 'bar', 'count' => 42]);
        $now = new \DateTimeImmutable();
        $instance = new \Flowy\Model\WorkflowInstance(
            $id,
            'order_fulfillment',
            '1.0.0',
            $statusClass::PENDING,
            $context,
            $now,
            $now,
            'BUSINESS-123',
            'step1',
            [],
            null,
            0,
            1,
            null,
            null,
            null
        );

        // Act: save and then find
        $this->adapter->save($instance);
        $found = $this->adapter->find($id);

        // Assert: all fields match
        $this->assertNotNull($found);
        $this->assertSame((string)$instance->id, (string)$found->id);
        $this->assertSame($instance->definitionId, $found->definitionId);
        $this->assertSame($instance->definitionVersion, $found->definitionVersion);
        $this->assertEquals($instance->status, $found->status);
        $this->assertEquals($instance->context->all(), $found->context->all());
        $this->assertEquals($instance->createdAt->format('c'), $found->createdAt->format('c'));
        $this->assertEquals($instance->updatedAt->format('c'), $found->updatedAt->format('c'));
        $this->assertSame($instance->businessKey, $found->businessKey);
        $this->assertSame($instance->currentStepId, $found->currentStepId);
        $this->assertEquals($instance->history, $found->history);
        $this->assertSame($instance->errorDetails, $found->errorDetails);
        $this->assertSame($instance->retryAttempts, $found->retryAttempts);
        $this->assertSame($instance->version, $found->version);
    }

    public function testFindByBusinessKeyReturnsCorrectInstance(): void
    {
        $instance = $this->createWorkflowInstance('uuid-bk-1', 'def-1', '1.0.0', null, ['x' => 1], 'BK-1');
        $this->adapter->save($instance);
        $found = $this->adapter->findByBusinessKey('def-1', 'BK-1');
        $this->assertNotNull($found);
        $this->assertSame('BK-1', $found->businessKey);
        $this->assertSame('def-1', $found->definitionId);
        $this->assertSame('uuid-bk-1', (string)$found->id);
    }

    public function testFindInstancesByStatusReturnsMatchingInstances(): void
    {
        $pending = $this->createWorkflowInstance('id1', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::PENDING);
        $running = $this->createWorkflowInstance('id2', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::RUNNING);
        $completed = $this->createWorkflowInstance('id3', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::COMPLETED);
        $this->adapter->save($pending);
        $this->adapter->save($running);
        $this->adapter->save($completed);
        $found = $this->adapter->findInstancesByStatus(\Flowy\Model\WorkflowStatus::PENDING);
        $this->assertCount(1, $found);
        $this->assertSame('id1', (string)$found[0]->id);
    }

    public function testFindDueForProcessingReturnsPendingAndDueInstances(): void
    {
        $now = new \DateTimeImmutable();
        $past = $now->modify('-1 hour');
        $future = $now->modify('+1 hour');
        $due = $this->createWorkflowInstance('due', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::PENDING);
        $due->scheduledAt = $past;
        $notDue = $this->createWorkflowInstance('notdue', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::PENDING);
        $notDue->scheduledAt = $future;
        $running = $this->createWorkflowInstance('running', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::RUNNING);
        $running->scheduledAt = $past;
        $completed = $this->createWorkflowInstance('done', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::COMPLETED);
        $completed->scheduledAt = $past;
        $this->adapter->save($due);
        $this->adapter->save($notDue);
        $this->adapter->save($running);
        $this->adapter->save($completed);
        $found = $this->adapter->findDueForProcessing(10);
        $ids = array_map(fn($i) => (string)$i->id, $found);
        $this->assertContains('due', $ids);
        $this->assertContains('running', $ids);
        $this->assertNotContains('notdue', $ids);
        $this->assertNotContains('done', $ids);
    }

    public function testFindReturnsNullIfNotFound(): void
    {
        $notFound = $this->adapter->find($this->createWorkflowInstanceId('does-not-exist'));
        $this->assertNull($notFound);
    }

    public function testDeleteRemovesInstance(): void
    {
        // Arrange
        $instance = $this->createWorkflowInstance('instance-to-delete');
        $this->adapter->save($instance);
        $this->assertNotNull($this->adapter->find($instance->id), "Instance should exist before delete.");

        // Act
        $this->adapter->delete($instance->id);

        // Assert
        $this->assertNull($this->adapter->find($instance->id), "Instance should not exist after delete.");
    }

    public function testSaveUpdatesExistingInstance(): void
    {
        // Arrange: Save an initial instance
        $instance = $this->createWorkflowInstance('instance-to-update', 'def-A', '1.0', \Flowy\Model\WorkflowStatus::PENDING, ['initial' => 'value']);
        $this->adapter->save($instance);

        // Act: Modify and save again
        $instance->status = \Flowy\Model\WorkflowStatus::RUNNING;
        $instance->context = \Flowy\Context\WorkflowContext::fromArray(['updated' => 'newValue']);
        $instance->currentStepId = 'newStep';
        $instance->version++; // Simulate version increment on update
        $this->adapter->save($instance);

        $updatedInstance = $this->adapter->find($instance->id);

        // Assert
        $this->assertNotNull($updatedInstance);
        $this->assertEquals(\Flowy\Model\WorkflowStatus::RUNNING, $updatedInstance->status);
        $this->assertEquals(['updated' => 'newValue'], $updatedInstance->context->all());
        $this->assertSame('newStep', $updatedInstance->currentStepId);
        $this->assertSame(2, $updatedInstance->version);
    }

    public function testFindByBusinessKeyReturnsNullIfNotFound(): void
    {
        $found = $this->adapter->findByBusinessKey('non-existent-def', 'NON-EXISTENT-BK');
        $this->assertNull($found);
    }

    public function testFindInstancesByStatusReturnsEmptyArrayIfNoneMatch(): void
    {
        // Save some instances with other statuses
        $running = $this->createWorkflowInstance('id_r', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::RUNNING);
        $completed = $this->createWorkflowInstance('id_c', 'def', '1.0.0', \Flowy\Model\WorkflowStatus::COMPLETED);
        $this->adapter->save($running);
        $this->adapter->save($completed);

        $found = $this->adapter->findInstancesByStatus(\Flowy\Model\WorkflowStatus::PENDING);
        $this->assertIsArray($found);
        $this->assertCount(0, $found);
    }

    public function testFindFailedReturnsCorrectInstances(): void
    {
        $maxRetries = 3;

        // Instance 1: FAILED, within retry limit
        $failed1 = $this->createWorkflowInstance(
            'failed1', 'defA', '1.0', \Flowy\Model\WorkflowStatus::FAILED, [], null, null, [], 'Error 1', 1
        );
        $this->adapter->save($failed1);

        // Instance 2: FAILED, at retry limit
        $failed2 = $this->createWorkflowInstance(
            'failed2', 'defA', '1.0', \Flowy\Model\WorkflowStatus::FAILED, [], null, null, [], 'Error 2', $maxRetries
        );
        $this->adapter->save($failed2);

        // Instance 3: FAILED, over retry limit
        $failed3 = $this->createWorkflowInstance(
            'failed3', 'defB', '1.0', \Flowy\Model\WorkflowStatus::FAILED, [], null, null, [], 'Error 3', $maxRetries + 1
        );
        $this->adapter->save($failed3);

        // Instance 4: PENDING, should not be returned
        $pending = $this->createWorkflowInstance(
            'pending4', 'defA', '1.0', \Flowy\Model\WorkflowStatus::PENDING, [], null, null, [], null, 0
        );
        $this->adapter->save($pending);

        // Instance 5: FAILED, different definition, within retry limit
        $failed5 = $this->createWorkflowInstance(
            'failed5', 'defB', '1.0', \Flowy\Model\WorkflowStatus::FAILED, [], null, null, [], 'Error 5', 0
        );
        $this->adapter->save($failed5);

        // Test case 1: No definition filter
        $foundAll = $this->adapter->findFailed(10, $maxRetries);
        $this->assertCount(2, $foundAll, 'Should find failed1 and failed5');
        $foundIds = array_map(fn($i) => (string)$i->id, $foundAll);
        $this->assertContains('failed1', $foundIds);
        $this->assertContains('failed5', $foundIds);
        $this->assertNotContains('failed2', $foundIds);
        $this->assertNotContains('failed3', $foundIds);

        // Test case 2: Filter by definition defA
        $foundDefA = $this->adapter->findFailed(10, $maxRetries, 'defA');
        $this->assertCount(1, $foundDefA, 'Should find only failed1 for defA');
        $this->assertSame('failed1', (string)$foundDefA[0]->id);

        // Test case 3: Filter by definition defB
        $foundDefB = $this->adapter->findFailed(10, $maxRetries, 'defB');
        $this->assertCount(1, $foundDefB, 'Should find only failed5 for defB');
        $this->assertSame('failed5', (string)$foundDefB[0]->id);

        // Test case 4: Limit results
        $foundLimited = $this->adapter->findFailed(1, $maxRetries);
        $this->assertCount(1, $foundLimited);

        // Test case 5: No matching instances
        $foundNone = $this->adapter->findFailed(10, $maxRetries, 'defC');
        $this->assertCount(0, $foundNone);
    }
}
