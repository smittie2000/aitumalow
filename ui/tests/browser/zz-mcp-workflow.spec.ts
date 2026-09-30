import { test, expect } from '@playwright/test'

test('MCP authors a workflow that the editor can change, test and inspect', async ({ request, page }, testInfo) => {
  let id = 0
  let session: string | undefined
  const transcript: { method: string; params: object; result: unknown }[] = []
  async function rpc<T>(method: string, params: object): Promise<T> {
    const response = await request.post('/testing/mcp', {
      headers: { Accept: 'application/json, text/event-stream', ...(session ? { 'MCP-Session-Id': session } : {}) },
      data: { jsonrpc: '2.0', id: ++id, method, params },
    })
    expect(response.ok(), await response.text()).toBeTruthy()
    session = response.headers()['mcp-session-id'] ?? session
    const message = await response.json() as { error?: unknown; result: T }
    expect(message.error).toBeUndefined()
    transcript.push({ method, params, result: message.result })
    return message.result
  }
  async function call<T>(name: string, args: object): Promise<T> {
    const result = await rpc<{ isError?: boolean; content: unknown; structuredContent: T }>('tools/call', { name, arguments: args })
    expect(result.isError, JSON.stringify(result.content)).not.toBe(true)
    expect(result.structuredContent).toBeDefined()
    return result.structuredContent
  }
  await rpc('initialize', { protocolVersion: '2025-11-25', capabilities: {}, clientInfo: { name: 'aitumalow-parity-test', version: '1' } })
  const tools = await rpc<{ tools: { name: string }[] }>('tools/list', {})
  expect(tools.tools.map((tool) => tool.name)).toEqual(expect.arrayContaining(['create_workflow', 'test_workflow_node', 'control_workflow_run', 'manage_workflow_folder']))

  // Two authoring calls: discover the existing catalog and create a complete draft.
  const catalog = await call<{ workflow_nodes: { key: string; config_schema: { key: string }[] }[] }>('list_workflow_nodes', { category: 'Test CRM', include_schemas: true })
  const email = catalog.workflow_nodes.find((node) => node.key === 'test.lead.email_owner')
  expect(email?.config_schema.map((field) => field.key)).toEqual(['subject', 'message'])
  const created = await call<{ workflow: { id: number; is_active: boolean; active_revision_id: number | null } }>('create_workflow', {
    name: 'MCP lead owner email',
    nodes: [
      { id: 'start', capability: 'test.lead.status_changed', name: 'Lead event' },
      { id: 'email', capability: email?.key, name: 'Notify owner', config: { subject: 'MCP notification', message: 'Agent-created draft.' } },
    ],
    edges: [{ from: 'start', to: 'email' }],
  })
  expect(created.workflow.is_active).toBe(false)
  expect(created.workflow.active_revision_id).toBeNull()
  const workflowId = created.workflow.id
  await page.goto(`/${workflowId}`)
  await expect(page.getByText('MCP lead owner email', { exact: true })).toBeVisible()
  await expect(page.getByText('Lead event', { exact: true })).toBeVisible()
  await page.getByText('Notify owner', { exact: true }).click()
  await expect(page.getByRole('textbox', { name: 'Message', exact: true })).toHaveValue('Agent-created draft.')
  await page.getByRole('textbox', { name: 'Message', exact: true }).fill('Human-reviewed draft.')
  await page.getByRole('button', { name: 'Save settings', exact: true }).click()
  const draft = await call<{ draft: { nodes: { config: { message?: string } }[] } }>('get_workflow_draft', { workflow_id: workflowId })
  expect(draft.draft.nodes[1].config.message).toBe('Human-reviewed draft.')
  await page.screenshot({ path: testInfo.outputPath('mcp-workflow-in-editor.png'), fullPage: true })

  const graph = await call<{ hash: string; workflow: { nodes: { id: number; node_key: string }[] } }>('get_workflow_graph', { workflow_id: workflowId })
  const emailNode = graph.workflow.nodes.find((node) => node.node_key === email?.key)
  expect(emailNode).toBeDefined()
  const tested = await call<{ workflow_run: { id: number } }>('test_workflow_node', {
    workflow_id: workflowId, node_id: emailNode?.id, expected_graph_hash: graph.hash,
    payload: [{ lead_id: 1, old_status: 'new', new_status: 'qualified' }],
  })
  await expect.poll(async () => (await call<{ workflow_run: { status: string } }>('show_workflow_run', { workflow_run_id: tested.workflow_run.id })).workflow_run.status).toBe('completed')
  const samples = await call<{ workflow_run: { node_runs: { output: { main: { recipient?: string }[] } }[] } }>('show_workflow_run', { workflow_run_id: tested.workflow_run.id, include_data: true })
  expect(samples.workflow_run.node_runs[1].output.main[0].recipient).toBe('alice@example.test')
  const summary = await call<{ workflow_run: object }>('show_workflow_run', { workflow_run_id: tested.workflow_run.id })
  expect(summary.workflow_run).not.toHaveProperty('initial_payload')

  // Publication remains a separate user operation; the browser sees its result.
  await call('activate_workflow', { workflow_id: workflowId })
  await page.reload()
  await expect(page.getByText('Active', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Run history', exact: true }).click()
  await expect(page.getByRole('complementary', { name: 'Run history' }).getByText('completed', { exact: true })).toHaveCount(1)
  await page.goto('/testing/inbox')
  const captured = page.getByRole('article', { name: 'Captured email', exact: true }).filter({ hasText: 'MCP notification' })
  await expect(captured).toHaveCount(1)
  await expect(captured).toContainText('Human-reviewed draft.')
  await expect(captured).toContainText('alice@example.test')
  await page.screenshot({ path: testInfo.outputPath('mcp-captured-email.png'), fullPage: true })
  await call('deactivate_workflow', { workflow_id: workflowId })
  await testInfo.attach('mcp-transcript', { body: JSON.stringify(transcript, null, 2), contentType: 'application/json' })
})
