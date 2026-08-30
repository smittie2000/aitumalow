# Security boundary

Aitumalow stores composition, not authority.

- Hosts register every executable capability explicitly under a stable namespaced key.
- Hosts own authorization, tenancy, models, policies, credentials, providers, and idempotent business behavior.
- Stored graphs never contain arbitrary PHP classes, shell commands, credentials, provider API keys, or executable code.
- Reference fields store opaque values allowed by a host `WorkflowReferenceProvider` under the current `ExecutionScope`.
- External trigger routes are host-owned and must apply the host's authentication and tenancy middleware.

Durable activities are at-least-once. A host capability that changes business state must use the Durable `activityId()` or its own business key as an idempotency key.

The package ships no generic mail, HTTP, model-update, shell, job-dispatch, notification, or code node. A host may register narrowly bounded equivalents, but their schema should expose safe references rather than secrets or class names.
