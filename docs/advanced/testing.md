# Testing Workflows

Workflow starts are asynchronous even in tests. Do not switch the production
architecture to a package-owned synchronous executor. Test at one of these two
seams instead.

## Unit-test a host action

Instantiate the action with faked host dependencies and pass a `WorkflowContext`.
This is the fastest place to prove domain validation, authorization, and an
idempotent side effect.

## Drain Durable tasks in an integration test

Load Durable Workflow's service provider and migrations, fake queue transport,
start the Aitumalow run, and invoke the ready Durable workflow/activity/timer task
handlers until the run becomes waiting or terminal. This package's own test
suite uses that seam in `tests/TestCase.php`.

Assertions should cover:

- the Aitumalow run has Durable workflow and run IDs;
- each executed node projection has a Durable activity ID;
- expected output is projected on the correct port;
- a retry increments the attempt count while preserving the activity ID; and
- host service fakes observed the intended side effect.

For waits, drain until the run is waiting, call `Workflow::resume()` with the
bounded signal payload, then drain again. For delays or retry backoff, advance
Laravel's test clock to the Durable task's `available_at` time.

The focused examples are:

- `tests/Feature/StableCapabilityWorkflowTest.php`
- `tests/Feature/WaitResumeTest.php`
- `tests/Feature/MailboxMonitoringWorkflowTest.php`

The test helper is intentionally test-only. Production must use Durable queue
workers and its schedule tick process.
