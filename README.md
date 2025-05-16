# Flowy - PHP Workflow Engine

Flowy is a modern, extensible PHP workflow engine designed for robust, maintainable, and scalable workflow automation. Built with PHP 8.1+, it leverages modern language features, strict typing, and a modular architecture for maximum flexibility and developer experience.

## 🚀 Getting Started

### Installation

Install Flowy via Composer:

```bash
composer require flowy/core
```

### Minimal Example: Define and Run a Workflow

```php
use Flowy\Model\Data\WorkflowDefinition;
use Flowy\Model\Data\StepDefinition;
use Flowy\Registry\InMemoryDefinitionRegistry;
use Flowy\Engine\WorkflowEngineService;
use Flowy\Context\WorkflowContext;

// 1. Define a workflow and its steps
$step = new StepDefinition(
    'start',
    [],
    [],
    'Start',
    'The initial step.',
    true,
    'action',
    null,
    null
);
$workflow = new WorkflowDefinition(
    'demo_workflow',
    '1.0.0',
    'start',
    [$step],
    'Demo Workflow',
    'A simple demo workflow.'
);

// 2. Register the workflow definition
$registry = new InMemoryDefinitionRegistry();
$registry->addDefinition($workflow);

// 3. Create the engine (using in-memory persistence for demo)
$engine = new WorkflowEngineService(
    $registry,
    /* PersistenceInterface */ new class implements \Flowy\Persistence\PersistenceInterface {
        public function save($instance) { /* ... */ }
        public function find($id) { /* ... */ }
        public function findByBusinessKey($defId, $key) { /* ... */ }
        public function findInstancesByStatus($status) { /* ... */ }
    },
    /* EventDispatcherInterface */ new \Flowy\Event\NullEventDispatcher()
);

// 4. Start a workflow instance
$context = new WorkflowContext(['foo' => 'bar']);
$instance = $engine->start('demo_workflow', '1.0.0', $context);
echo "Started instance: " . $instance->id->toString() . PHP_EOL;
```

### Running Tests, Static Analysis, and Code Style Checks

- **Run tests:**
  ```bash
  composer test
  ```
- **Run static analysis (PHPStan):**
  ```bash
  composer stan
  ```
- **Check code style (PSR-12):**
  ```bash
  composer cs
  ```

## 📚 Further Documentation
- See [docs/CoreConcepts.md](./docs/CoreConcepts.md) for an overview of Flowy's core concepts.

---

Flowy is open-source and welcomes contributions! Please see the roadmap and specification before submitting PRs.
