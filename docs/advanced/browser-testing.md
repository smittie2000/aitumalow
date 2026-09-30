# Real browser testing

The acceptance test builds **Lead status changed → Email lead owner** through
the visible interface, publishes it, changes a test lead's status, and inspects
the captured email and completed run. Workflow creation, node configuration,
connections and publishing use clicks and form controls. The test never seeds a
workflow, calls the workflow API directly, injects a graph into the store, or
mocks network responses.

## Dedicated test area

The local host serves the compiled editor, uses a persistent SQLite database,
runs an ordinary Laravel database queue worker, and captures actual SMTP
messages on loopback. It uses Testbench's native application lifecycle and
Durable's v2-only fresh-install option (`DW_V1_ENABLED=false`). No queue or
workflow execution fakes are used. The dedicated SQLite connection uses WAL,
a busy timeout and Laravel's native `IMMEDIATE` transaction mode so HTTP graph
edits and worker writes serialize before reading a transaction snapshot.

Test-only host capabilities are registered in `tests/Browser/`:

- **Lead status changed** starts published workflows when a saved status differs
  from its previous value.
- **Email lead owner** resolves the owner from the persisted lead and sends a
  configured subject and message with the previous/new status appended.
- **Find open tickets → Remind ticket owner** queries persisted open tickets
  and sends a captured reminder per ticket, excluding closed tickets.
- **Find upcoming unconfirmed bookings → Call client with AI agent** queries
  pending calendar records in the next two days and captures one call request
  per booking, including its client context and operator-entered instructions.
  Confirmed, past and later bookings are excluded. This captures the calling
  boundary; it does not invoke a real model, dial a phone or confirm a booking.

These capabilities are fixture host code, loaded only by this test application.
They do not register CRM behavior in installed Aitumalow hosts. A production CRM
must expose its own authorized lead-event and owner-email capabilities. Once
those are available, an operator builds this workflow without writing code.

The database, inbox and captured calls live exclusively under `storage/browser-testing/`. The
host does not load an application's `.env`. HTTP binds to `127.0.0.1:8099`, SMTP
to `127.0.0.1:1026`; these ports must be free. This fixture has no login flow and
is intended only for local testing, not public hosting. Run the PHP suite
separately from the browser host because Testbench shares framework files.

## Install and run

From the repository root:

```bash
composer install
cd ui
npm install
npx playwright install chromium
npm run test:browser
```

`test:browser` builds the production assets, starts the host, resets only its
dedicated database/inbox/calls, runs the browser tests, and stops the host and worker.
It refuses to reuse an existing server. Test steps are named in the HTML report;
interaction traces are recorded for every run, with failure screenshots/videos.

```bash
npm run test:browser:report
```

The primary test verifies:

1. Workflow creation, trigger selection, adding a connected email action,
   saving settings, publishing and persistence after reload.
2. Alice's status change sends one message to Alice with the configured content
   and previous/new status; Bob is not a recipient.
3. A name-only edit sends nothing; Bob's status change emails Bob.
4. Both executions appear as completed in run history.
5. Deactivation prevents further email. The example is then published again for
   manual review.

A second test submits a name exceeding the server's limit and checks that the
dialog shows the validation error, retains the entered name, and permits a
successful correction and retry. This reproduces the silent creation failure
discovered while establishing the test area.

Two scheduled scenarios share the same direct browser script. Each authors
**Schedule → Find records → Act on each record** in 17 clicks/fills/selections,
from New Workflow through Publish. The lead-owner example takes 12 (previously
13). The authoring steps use accessible button names and field labels only:
no CSS selectors, coordinates, forced clicks, sleeps, expressions, JSON or API
setup. Assertions, navigation, evidence capture and execution checks are counted
separately from authoring; small helper wrappers must not conceal interaction
cost when comparing future changes.

The scheduled tests select Every day, 08:00 and Africa/Johannesburg; verify the
persisted native cron/timezone and displayed next occurrence; replay through
Durable's native schedule backfill; inspect actual captured results and completed
runs; then close/confirm the matching records and replay a later occurrence.
The later run must complete without more emails or call requests. **Replay next
unplayed occurrence** advances past captured occurrence history for this purpose.
Backfill exercises
native cron enumeration and queue execution without waiting until tomorrow;
this is not proof of a deployed host's wall-clock scheduler.

Evidence lives in `ui/test-results/` and `ui/playwright-report/`, which are
ignored generated files. A passing test writes `published-workflow.png`,
`captured-email.png`, and `trace.zip` in its result directory. This proves local
editor interaction, queued execution and SMTP receipt; it does not prove
production CRM event wiring, tenant authorization, external mailbox delivery,
or a particular deployed Filament panel.

## Manual review

After the tests finish, from `ui/`:

```bash
npm run browser:serve
```

Open [the editor](http://127.0.0.1:8099),
[test leads](http://127.0.0.1:8099/testing/leads), and
[captured email](http://127.0.0.1:8099/testing/inbox), and
[scheduled scenarios](http://127.0.0.1:8099/testing/scenarios). This command retains the
test area's existing data; a fresh area is initialized automatically. Stop it
with Ctrl+C before rerunning automated tests. The saved example is active.
Change a lead to a different status to see another email arrive.

To watch automated interaction on a machine with a display, run
`npm run test:browser -- --headed`. If a supported browser is already installed,
`BROWSER_EXECUTABLE_PATH=/absolute/path/to/browser npm run test:browser` selects
it; otherwise Playwright uses its installed Chromium.

## Codex browser control

Codex can run these Playwright tests, view the screenshots and inspect traces
using its terminal/file tools. A separate computer-use model or API key is not
needed for this test setup.

For interactive browser tools in Codex, Microsoft's Playwright MCP provides
navigation, accessibility snapshots, form controls and screenshots. Register
the server in the Codex environment on the same machine as this test area:

```bash
codex mcp add playwright -- npx -y @playwright/mcp@latest --headless
codex mcp list
```

Then start/reconnect a Codex session with that server enabled. Configuration
alone does not establish that browser tools are available: verify the active
session can navigate to the test-area URL and return a snapshot/screenshot.
This repository does not edit global Codex configuration.

See [Codex MCP configuration](https://learn.chatgpt.com/docs/extend/mcp?surface=cli)
and [Microsoft's Playwright MCP documentation](https://github.com/microsoft/playwright-mcp).

## Upstream adoption

Durable Workflow was upgraded from 2.0.14 to 2.2.21 and React Flow from 12.11.5
to 12.12.0. Reviewed sources were the installed Durable changelog plus the
individual [Durable releases](https://github.com/durable-workflow/workflow/releases),
and [React Flow's changelog](https://github.com/xyflow/xyflow/blob/main/packages/react/CHANGELOG.md).
The published Durable changelog alone omits several intervening release entries.

Durable adds migrations, the patched framework minimum and projection/schedule
fixes. Existing Aitumalow public runtime adapters remain compatible. Its boundary
test now enforces the upgraded minimum; the test host uses the native v2-only
install option and actual queue delivery. Review the
[runtime upgrade guidance](/advanced/execution-engine) before upgrading a host.

React Flow 12.11.6 resets provider state on unmount; 12.12.0 corrects resize
completion and rejected-resize values. No custom store-reset or resize wrapper
is needed. The production editor is rebuilt against the upgraded package and
the browser flow reloads and reopens it during acceptance testing.

## MCP and editor parity

The isolated host also mounts the package's native Laravel MCP server at
`http://127.0.0.1:8099/testing/mcp`. This loopback test route has the same test
catalog and data as the editor; it is not mounted by the installed package.
Production transports must use the host's editor authentication and tenant
scope as explained in the [MCP setup](/mcp).

```bash
cd ui
npm run test:browser -- zz-mcp-workflow.spec.ts
```

This case uses two MCP authoring calls (catalog with schemas, then complete
creation), opens the saved workflow in the browser, changes the message through
visible controls, reads the change through MCP, tests the draft through the
real database queue and SMTP inbox, inspects node samples, and explicitly
publishes and deactivates. Its attachments contain a JSON-RPC transcript and
screenshots. It exercises real transport interactions rather than only direct
tool handlers; no workflow PHP or API graph seeding is used. These checks do
not measure a particular LLM's natural-language planning quality.
