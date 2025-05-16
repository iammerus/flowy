# Flowy Core Concepts

This document provides an overview of the core concepts in Flowy, the PHP Workflow Engine. It is intended for developers integrating, extending, or contributing to Flowy.

## Workflow
A **Workflow** is a definition of a business process, composed of ordered steps, actions, and transitions. Workflows are defined using PHP attributes or YAML, and are registered in a Definition Registry.

- **ID:** Unique identifier for the workflow.
- **Version:** Semantic version string (e.g., `1.0.0`).
- **Steps:** An array of StepDefinitions.
- **Initial Step:** The step where execution begins.
- **Description:** Human-readable summary.

## Step
A **Step** represents a single stage in a workflow. Each step can execute one or more actions and may have outgoing transitions to other steps.

- **ID:** Unique within the workflow.
- **Name:** Optional, for display purposes.
- **Type:** e.g., `action`, `fork`, `join`, `callActivity`.
- **Is Initial:** Boolean, marks the starting step.
- **Actions:** Actions to execute in this step.
- **Transitions:** Outgoing transitions to other steps.
- **Description:** Optional, for documentation.

## Action
An **Action** is a unit of work performed during a step. Actions implement `ActionInterface` and are resolved via the ActionResolver. Actions can be PHP callables, service IDs, or FQCNs.

- **ID:** Unique within the workflow.
- **Parameters:** Optional, passed to the action.
- **Type:** Callable, service, or FQCN.

## Transition
A **Transition** defines the path from one step to another, optionally guarded by a condition.

- **Target:** The ID of the next step.
- **Condition:** Optional, must evaluate to true for the transition to be taken.
- **Event:** (Advanced) Used for event-based transitions.

## Condition
A **Condition** is a boolean expression or object that determines if a transition should be taken. Conditions implement `ConditionInterface` and are resolved via the ConditionResolver.

## Context
The **WorkflowContext** is a key-value store passed through the workflow execution. It holds business data, action results, and is available to all actions and conditions.

- **Immutable:** Context objects are immutable; modifications return a new instance.
- **Serialization:** Context is serializable for persistence.

## Workflow Instance
A **WorkflowInstance** is a runtime execution of a workflow definition. It tracks the current step, status, context, history, and business key.

- **ID:** Unique, implements `WorkflowInstanceIdInterface`.
- **Status:** Enum (`PENDING`, `RUNNING`, `PAUSED`, `COMPLETED`, `FAILED`, `CANCELLED`).
- **Current Step:** The step currently being executed.
- **Context:** The current WorkflowContext.
- **History:** Array of events (step entered, action executed, etc.).
- **Business Key:** Optional, for business-level correlation.

## Registry
The **DefinitionRegistryInterface** provides storage and lookup for workflow definitions. Implementations include in-memory and persistent registries.

## Persistence
The **PersistenceInterface** abstracts storage of workflow instances. Implementations may use Doctrine ORM, MongoDB, Redis, etc.

## Event Dispatcher
Flowy uses PSR-14 for event dispatching. Events are emitted for workflow lifecycle changes (started, completed, failed, etc.) and step transitions.

---

For further details, see [SPEC.md](../SPEC.md) and the source code in the `src/` directory.
