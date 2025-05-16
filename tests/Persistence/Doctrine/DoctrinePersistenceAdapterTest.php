<?php

declare(strict_types=1);

namespace Flowy\Tests\Persistence\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\OptimisticLockException;
use Flowy\Context\WorkflowContext;
use Flowy\Model\ValueObject\WorkflowInstanceId;
use Flowy\Model\WorkflowInstance;
use Flowy\Model\WorkflowStatus;
use Flowy\Persistence\Doctrine\DoctrinePersistenceAdapter;
use Flowy\Persistence\Doctrine\Type\WorkflowInstanceIdType;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class DoctrinePersistenceAdapterTest extends TestCase
{
    private ?EntityManager $entityManager = null;
    private ?DoctrinePersistenceAdapter $persistenceAdapter = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure Doctrine ORM for testing with SQLite in-memory
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 3) . '/src/Model'], // Use absolute path to src/Model
            isDevMode: true,
        );

        // Database connection parameters (SQLite in-memory)
        $connectionParams = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        $connection = DriverManager::getConnection($connectionParams, $config);
        $this->entityManager = new EntityManager($connection, $config);

        // Register custom DBAL types
        if (!Type::hasType(WorkflowInstanceIdType::NAME)) {
            Type::addType(WorkflowInstanceIdType::NAME, WorkflowInstanceIdType::class);
        }
        
        // Register the WorkflowContextType
        if (!Type::hasType(\Flowy\Persistence\Doctrine\Type\WorkflowContextType::NAME)) {
            Type::addType(\Flowy\Persistence\Doctrine\Type\WorkflowContextType::NAME, \Flowy\Persistence\Doctrine\Type\WorkflowContextType::class);
        }

        // Create schema
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->createSchema($metadata);

        $this->persistenceAdapter = new DoctrinePersistenceAdapter($this->entityManager);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Close the connection to avoid issues between tests
        if ($this->entityManager !== null && $this->entityManager->getConnection()->isConnected()) {
            $this->entityManager->getConnection()->close();
        }
        $this->entityManager = null;
        $this->persistenceAdapter = null;
    }

    private function createInstance(
        ?WorkflowInstanceId $id = null,
        string $definitionId = 'test_workflow:1.0',
        string $definitionVersion = '1.0',
        WorkflowStatus $status = WorkflowStatus::PENDING,
        ?WorkflowContext $context = null,
        ?string $businessKey = null,
        ?string $currentStepId = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        array $history = [],
        ?string $errorDetails = null,
        int $retryAttempts = 0,
        int $version = 1 // Default for new instance, Doctrine handles increment
    ): WorkflowInstance {
        return new WorkflowInstance(
            id: $id ?? WorkflowInstanceId::generate(),
            definitionId: $definitionId,
            definitionVersion: $definitionVersion,
            status: $status,
            context: $context ?? new WorkflowContext([]),
            createdAt: $createdAt ?? new DateTimeImmutable(),
            updatedAt: $updatedAt ?? new DateTimeImmutable(),
            businessKey: $businessKey,
            currentStepId: $currentStepId,
            history: $history,
            errorDetails: $errorDetails,
            retryAttempts: $retryAttempts,
            version: $version
        );
    }

    public function testSaveAndFindNewInstance(): void
    {
        $instanceId = WorkflowInstanceId::generate();
        $definitionId = 'test_workflow:1.0';
        $context = new WorkflowContext(['key' => 'value']);
        $now = new DateTimeImmutable();
        $businessKey = 'biz_key_123';
        $currentStepId = 'step1';

        $instance = $this->createInstance(
            id: $instanceId,
            definitionId: $definitionId,
            context: $context,
            createdAt: $now, // Save exact time for comparison
            updatedAt: $now, // Save exact time for comparison
            businessKey: $businessKey,
            currentStepId: $currentStepId
        );

        $this->persistenceAdapter->save($instance);

        // Clear the entity manager to ensure we are fetching from the database
        $this->entityManager->clear();

        $foundInstance = $this->persistenceAdapter->find($instanceId);

        $this->assertInstanceOf(WorkflowInstance::class, $foundInstance);
        $this->assertTrue($instanceId->equals($foundInstance->id));
        $this->assertEquals($definitionId, $foundInstance->definitionId);
        $this->assertEquals('1.0', $foundInstance->definitionVersion);
        $this->assertEquals(WorkflowStatus::PENDING, $foundInstance->status);
        $this->assertEquals('value', $foundInstance->context->get('key'));
        $this->assertEquals($now->getTimestamp(), $foundInstance->createdAt->getTimestamp());
        // UpdatedAt is modified by save method, so we check it's later or equal
        $this->assertGreaterThanOrEqual($now->getTimestamp(), $foundInstance->updatedAt->getTimestamp());
        $this->assertEquals($businessKey, $foundInstance->businessKey);
        $this->assertEquals($currentStepId, $foundInstance->currentStepId);
        $this->assertEquals(1, $foundInstance->version); // Initial version after first save
    }

    public function testSaveAndUpdateExistingInstance(): void
    {
        // Arrange: Create and save an initial instance
        $instanceId = WorkflowInstanceId::generate();
        $initialDefinitionId = 'test_workflow:1.0';
        $initialContext = new WorkflowContext(['key' => 'initial_value']);
        $initialStep = 'step_initial';
        $now = new DateTimeImmutable();
        
        $instance = $this->createInstance(
            id: $instanceId,
            definitionId: $initialDefinitionId,
            context: $initialContext,
            currentStepId: $initialStep,
            createdAt: $now,
            updatedAt: $now
        );
        
        $this->persistenceAdapter->save($instance);
        
        // Clear EntityManager to ensure a fresh load
        $this->entityManager->clear();
        
        // Act: Retrieve, modify, and save again
        $retrievedInstance = $this->persistenceAdapter->find($instanceId);
        $this->assertNotNull($retrievedInstance);
        
        // Make several changes to the instance
        $retrievedInstance->status = WorkflowStatus::RUNNING;
        $retrievedInstance->context = new WorkflowContext(['key' => 'updated_value', 'newKey' => 'new_value']);
        $retrievedInstance->currentStepId = 'step_updated';
        $retrievedInstance->addHistoryEvent('Step updated', 'step_updated');
        
        $this->persistenceAdapter->save($retrievedInstance);
        
        // Clear EntityManager again for a fresh load
        $this->entityManager->clear();
        
        // Assert: Check that changes were persisted
        $updatedInstance = $this->persistenceAdapter->find($instanceId);
        $this->assertNotNull($updatedInstance);
        $this->assertEquals(WorkflowStatus::RUNNING, $updatedInstance->status);
        $this->assertEquals('updated_value', $updatedInstance->context->get('key'));
        $this->assertEquals('new_value', $updatedInstance->context->get('newKey'));
        $this->assertEquals('step_updated', $updatedInstance->currentStepId);
        $this->assertCount(1, $updatedInstance->history);
        $this->assertEquals('Step updated', $updatedInstance->history[0]['message']);
        // Version should be incremented automatically by Doctrine's optimistic locking
        $this->assertEquals(2, $updatedInstance->version);
    }

    public function testOptimisticLockingOnConcurrentUpdates(): void
    {
        // Skip this test on SQLite, as it does not support true optimistic locking.
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();
        if ($platform instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $this->markTestSkipped('SQLite does not support optimistic locking exceptions.');
        }

        // Arrange: Create and save an initial instance
        $instanceId = WorkflowInstanceId::generate();
        $instance = $this->createInstance(id: $instanceId);
        $this->persistenceAdapter->save($instance);
        
        // Act/Assert: Simulate concurrent updates
        // 1. Get the same instance twice (simulating two concurrent requests)
        $instance1 = $this->persistenceAdapter->find($instanceId);
        $instance2 = $this->persistenceAdapter->find($instanceId);
        
        $this->assertNotNull($instance1);
        $this->assertNotNull($instance2);
        
        // 2. Update the first instance
        $instance1->status = WorkflowStatus::RUNNING;
        $this->persistenceAdapter->save($instance1);
        
        // 3. Try to update the second instance - this should fail with OptimisticLockException
        $instance2->status = WorkflowStatus::PAUSED;
        $this->expectException(OptimisticLockException::class);
        $this->persistenceAdapter->save($instance2);
    }

    public function testFindByBusinessKeyReturnsCorrectInstance(): void
    {
        // Arrange: Create and save instances with different business keys
        $businessKey1 = 'biz_key_123';
        $businessKey2 = 'biz_key_456';
        $definitionId = 'test_workflow:1.0';
        
        $instance1 = $this->createInstance(
            businessKey: $businessKey1,
            definitionId: $definitionId
        );
        $instance2 = $this->createInstance(
            businessKey: $businessKey2,
            definitionId: $definitionId
        );
        
        $this->persistenceAdapter->save($instance1);
        $this->persistenceAdapter->save($instance2);
        
        // Clear EntityManager for a fresh load
        $this->entityManager->clear();
        
        // Act: Find by business key
        $foundInstance = $this->persistenceAdapter->findByBusinessKey($definitionId, $businessKey1);
        
        // Assert: Correct instance is returned
        $this->assertNotNull($foundInstance);
        $this->assertEquals($businessKey1, $foundInstance->businessKey);
        $this->assertTrue($instance1->id->equals($foundInstance->id));
    }

    public function testFindByBusinessKeyReturnsNullWhenNotFound(): void
    {
        // Act: Find by non-existent business key
        $result = $this->persistenceAdapter->findByBusinessKey('test_workflow:1.0', 'non_existent_key');
        
        // Assert: Should return null
        $this->assertNull($result);
    }

    public function testFindInstancesByStatusReturnsMatchingInstances(): void
    {
        // Arrange: Create and save instances with different statuses
        $pendingInstance1 = $this->createInstance(status: WorkflowStatus::PENDING);
        $pendingInstance2 = $this->createInstance(status: WorkflowStatus::PENDING);
        $runningInstance = $this->createInstance(status: WorkflowStatus::RUNNING);
        $pausedInstance = $this->createInstance(status: WorkflowStatus::PAUSED);
        $completedInstance = $this->createInstance(status: WorkflowStatus::COMPLETED);
        
        $this->persistenceAdapter->save($pendingInstance1);
        $this->persistenceAdapter->save($pendingInstance2);
        $this->persistenceAdapter->save($runningInstance);
        $this->persistenceAdapter->save($pausedInstance);
        $this->persistenceAdapter->save($completedInstance);
        
        // Clear EntityManager for a fresh load
        $this->entityManager->clear();
        
        // Act: Find instances by status
        $pendingInstances = $this->persistenceAdapter->findInstancesByStatus(WorkflowStatus::PENDING);
        $runningInstances = $this->persistenceAdapter->findInstancesByStatus(WorkflowStatus::RUNNING);
        
        // Assert: Correct instances are returned
        $this->assertCount(2, $pendingInstances);
        $this->assertCount(1, $runningInstances);
        
        // Verify the instance IDs match what we expect
        $pendingInstanceIds = array_map(
            fn(WorkflowInstance $instance) => $instance->id->toString(),
            $pendingInstances
        );
        
        $this->assertContains($pendingInstance1->id->toString(), $pendingInstanceIds);
        $this->assertContains($pendingInstance2->id->toString(), $pendingInstanceIds);
        
        $this->assertTrue($runningInstance->id->equals($runningInstances[0]->id));
    }

    public function testFindInstancesByStatusWithLimit(): void
    {
        // Arrange: Create and save multiple instances with the same status
        $instanceIds = [];
        for ($i = 0; $i < 5; $i++) {
            $instance = $this->createInstance(status: WorkflowStatus::PENDING);
            $this->persistenceAdapter->save($instance);
            $instanceIds[] = $instance->id->toString();
        }
        
        // Clear EntityManager for a fresh load
        $this->entityManager->clear();
        
        // Act: Find instances with a limit
        $limitedInstances = $this->persistenceAdapter->findInstancesByStatus(
            WorkflowStatus::PENDING,
            null, // No specific workflow definition ID
            3     // Limit to 3 results
        );
        
        // Assert: Limited number of instances returned
        $this->assertCount(3, $limitedInstances);
    }
    
    public function testFindInstancesByStatusWithNonPositiveLimit(): void
    {
        // Arrange: Create an instance
        $instance = $this->createInstance(status: WorkflowStatus::PENDING);
        $this->persistenceAdapter->save($instance);
        
        // Act: Call with zero limit
        $result = $this->persistenceAdapter->findInstancesByStatus(
            WorkflowStatus::PENDING,
            null, // No specific workflow definition ID
            0     // Zero limit
        );
        
        // Assert: Empty array returned
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testFindDueForProcessingReturnsPendingAndScheduledInstances(): void
    {
        // Arrange: Create timestamp references
        $now = new DateTimeImmutable();
        $pastTime = $now->modify('-1 hour');
        $futureTime = $now->modify('+1 hour');
        
        // Create instances with different statuses and scheduling
        $pendingNotScheduled = $this->createInstance(
            status: WorkflowStatus::PENDING,
            // scheduledAt defaults to null
        );
        
        $pendingPastScheduled = $this->createInstance(status: WorkflowStatus::PENDING);
        $pendingPastScheduled->scheduledAt = $pastTime;
        
        $pendingFutureScheduled = $this->createInstance(status: WorkflowStatus::PENDING);
        $pendingFutureScheduled->scheduledAt = $futureTime;
        
        $runningNotScheduled = $this->createInstance(status: WorkflowStatus::RUNNING);
        
        $runningPastScheduled = $this->createInstance(status: WorkflowStatus::RUNNING);
        $runningPastScheduled->scheduledAt = $pastTime;
        
        $runningFutureScheduled = $this->createInstance(status: WorkflowStatus::RUNNING);
        $runningFutureScheduled->scheduledAt = $futureTime;
        
        $completedPastScheduled = $this->createInstance(status: WorkflowStatus::COMPLETED);
        $completedPastScheduled->scheduledAt = $pastTime;
        
        // Save all instances
        $this->persistenceAdapter->save($pendingNotScheduled);
        $this->persistenceAdapter->save($pendingPastScheduled);
        $this->persistenceAdapter->save($pendingFutureScheduled);
        $this->persistenceAdapter->save($runningNotScheduled);
        $this->persistenceAdapter->save($runningPastScheduled);
        $this->persistenceAdapter->save($runningFutureScheduled);
        $this->persistenceAdapter->save($completedPastScheduled);
        
        // Clear EntityManager for a fresh load
        $this->entityManager->clear();
        
        // Act: Find instances due for processing
        $dueInstances = $this->persistenceAdapter->findDueForProcessing(10);
        
        // Assert: Only due instances are returned
        $this->assertCount(3, $dueInstances);
        
        // Collect the IDs of due instances for easier assertion
        $dueInstanceIds = array_map(
            fn(WorkflowInstance $instance) => $instance->id->toString(),
            $dueInstances
        );
        
        // Should include: PENDING with null scheduledAt, PENDING with past scheduledAt,
        // RUNNING with past scheduledAt
        $this->assertContains($pendingNotScheduled->id->toString(), $dueInstanceIds);
        $this->assertContains($pendingPastScheduled->id->toString(), $dueInstanceIds);
        $this->assertContains($runningPastScheduled->id->toString(), $dueInstanceIds);
        
        // Should NOT include: PENDING with future scheduledAt, RUNNING with future scheduledAt,
        // COMPLETED with any scheduledAt, RUNNING with null scheduledAt
        $this->assertNotContains($pendingFutureScheduled->id->toString(), $dueInstanceIds);
        $this->assertNotContains($runningFutureScheduled->id->toString(), $dueInstanceIds);
        $this->assertNotContains($completedPastScheduled->id->toString(), $dueInstanceIds);
        $this->assertNotContains($runningNotScheduled->id->toString(), $dueInstanceIds);
    }

    public function testFindDueForProcessingRespectsLimit(): void
    {
        // Arrange: Create multiple instances due for processing
        $instances = [];
        for ($i = 0; $i < 5; $i++) {
            $instance = $this->createInstance(status: WorkflowStatus::PENDING);
            $this->persistenceAdapter->save($instance);
            $instances[] = $instance;
        }
        
        // Clear EntityManager for a fresh load
        $this->entityManager->clear();
        
        // Act: Find instances with a limit
        $limitedInstances = $this->persistenceAdapter->findDueForProcessing(3);
        
        // Assert: Limited number of instances returned
        $this->assertCount(3, $limitedInstances);
    }

    public function testFindDueForProcessingWithNonPositiveLimit(): void
    {
        // Arrange: Create an instance due for processing
        $instance = $this->createInstance(status: WorkflowStatus::PENDING);
        $this->persistenceAdapter->save($instance);
        
        // Act: Call with non-positive limit
        $result = $this->persistenceAdapter->findDueForProcessing(0);
        
        // Assert: Empty array returned
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
        
        // Act: Call with negative limit
        $result = $this->persistenceAdapter->findDueForProcessing(-1);
        
        // Assert: Empty array returned
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
