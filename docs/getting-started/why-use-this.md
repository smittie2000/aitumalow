# Why Aitumalow?

Aitumalow lets a Laravel application expose selected business operations as an
n8n-style visual workflow without moving authority out of the application.

The useful split is:

```text
Host code defines what is allowed and how it works.
Aitumalow lets an administrator connect and configure those capabilities.
Durable Workflow makes the resulting execution survive queues, retries, waits,
timers, schedules, and worker restarts.
```

## What changes

Instead of hard-coding every sequence in a controller, the host registers narrow
capabilities such as:

- `app.deals.find_open`;
- `app.tasks.create`;
- `app.ticket.escalate`; and
- `messaging.quote.send`.

An administrator can then change a schedule, condition, mapping, or ordering in
the visual editor. If the business meaning of creating a task changes, the host
updates its one Laravel action and every workflow still follows the same policy.

## What stays in Laravel

Models, queries, policies, tenancy, credentials, provider clients, transactions,
and domain rules remain host-owned. Aitumalow graphs contain stable registered
keys and schema-checked configuration, never executable class names or arbitrary
code.

This is especially useful for a reusable workflow package: the package can supply the
builder and runtime adapter without assuming the host's `Ticket`, `Deal`, user,
AI provider, or integration model.

## Operational value

- Visual definitions make automation reviewable.
- Per-node projections show inputs, outputs, duration, attempts, and failures.
- Durable history provides crash recovery, retry, timers, signals, and schedule
  state without a second Aitumalow execution engine.
- The optional MCP adapter lets a host-owned agent compose only capabilities the
  application registered and authorized.
- Deactivating a workflow stops future configured starts without changing host
  business code.

## Deliberate limits

Aitumalow is not an unrestricted integration runner, generic Eloquent admin,
BPMN suite, assistant runtime, secret vault, or provider catalog. Those limits
are what make the visual layer safe to place above an existing Laravel product.

Continue with the [Quick Start](/getting-started/quick-start) or read the
[Architecture](/architecture).
