# Scheduled host agents

An operator or AI agent should compose the same host-registered capabilities.
The package supplies durable timing, connections, publishing and run history.
The Laravel app supplies calendar/ticket access, scoped queries, agent delegation
and delivery. A workflow never needs to know the host's PHP model names or provider
credentials.

## Every morning, confirm bookings

In the editor, create a workflow and choose **Schedule**. Select **Every day**,
**08:00**, and the business timezone. Choose **Save and add next step**, then
the host's **Find upcoming unconfirmed bookings** capability. Choose **Add next
step**, then **Call client with AI agent**. Enter instructions such as:

> Confirm the booking with the client. Record their response; escalate changes
> to the team.

Save the settings and publish. Each query result becomes one incoming item for
the action. A flat sequence of records needs no Loop node or expression mapping:
the host action reads its booking reference and resolves the current client
details within the execution scope.

The host must register these business capabilities before they appear. The
dedicated [browser test area](/advanced/browser-testing) includes fixture
versions and captures outbound call requests. It proves authoring, scheduling
and delegation inputs, not a live call or a confirmed booking.

The production calling action should enqueue the host's own agent/calling
service with the booking reference, scope and instructions. The host owns the
agent's permitted tools, including recording a confirmed response or escalating
a reschedule request. Requesting a call alone must not mark the booking confirmed.
Use `WorkflowContext::activityId()` plus the booking reference as a stable
delivery key across retries, recheck booking eligibility at execution, and keep
provider secrets in the host.

## Follow up on open tickets

Use the same schedule controls, then **Find open tickets → Remind ticket owner**.
Enter a reminder message, save and publish. The query returns one item per open
ticket; the action resolves its owner and rechecks that it remains open. An empty
query result completes the workflow without executing the downstream action.

Host schemas can expose scoped calendar, team or agent choices with `reference`
fields, and status/range settings with ordinary form fields. Those use the same
generic editor rather than adding a calendar-specific or ticket-specific wizard.

## AI composition and execution

An AI that authors workflows uses the existing [MCP server](/mcp) to discover the
actual host catalog, create a draft, save its validated graph, and inspect it.
Publishing is a separate tool action. The editor displays that same draft and
immutable published version for human review. An AI step inside a workflow is a
host action: the app chooses the agent framework and authorized business tools.

The host must run its Laravel scheduler with Durable's schedule tick command and
the configured queue worker; see [Schedule](/triggers/schedule). Scheduled runs
have no logged-in HTTP user, so bind an `ExecutionScopeResolver` for the workflow's
tenant/principal. See the [mailbox agent example](/examples/mailbox-quote-agent)
for the scope and selectable-resource contracts.
