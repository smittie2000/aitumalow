import assert from 'node:assert/strict'
import test from 'node:test'
import { createWorkflowEditorStore } from '../src/stores/useWorkflowEditorStore.ts'
import { createRunStore } from '../src/stores/useRunStore.ts'
import { ApiError } from '../src/api/client.ts'
import type { AitumalowEditorSdk } from '../src/sdk/editorSdk.ts'
import type { GraphEditRequest, WorkflowGraph } from '../src/api/graph.ts'
import type { CapabilityDefinition, Workflow, WorkflowRun } from '../src/api/types.ts'

const capability = { key: 'app.action', name: 'Action', type: 'action', input_ports: ['main'], output_ports: ['main'], config_schema: [] } as unknown as CapabilityDefinition
const workflow = { id: 1, nodes: [{ id: 1, workflow_id: 1, node_key: 'app.action', type: 'action', name: 'Original', config: {}, pinned_data: null, position_x: 40, position_y: 80 }], edges: [] } as unknown as Workflow
const hash = (value: string) => value.repeat(64)

async function editor() {
  const calls: GraphEditRequest[] = []
  const replies: (WorkflowGraph | Error)[] = []
  const sdk = { graph: {
    get: async () => ({ data: { workflow: structuredClone(workflow), hash: hash('a') } }),
    edit: async (_id: number, request: GraphEditRequest) => {
      calls.push(structuredClone(request))
      const reply = replies.shift()
      if (!reply) throw new Error('Missing test response')
      if (reply instanceof Error) throw reply
      return { data: reply }
    },
  } } as unknown as AitumalowEditorSdk
  const store = createWorkflowEditorStore(sdk)
  await store.getState().loadWorkflow(1, () => capability)
  return { store, calls, replies, sdk }
}

test('retry after an uncertain save reuses the exact connected-add request and blocks competing edits', async () => {
  const { store, calls, replies } = await editor()
  replies.push(new Error('Response lost'))
  await assert.rejects(store.getState().addNode('app.action', { x: 40, y: 200 }, capability, { source: { node_id: 1, port: 'main' }, input_port: 'main' }))
  assert.equal(store.getState().rfNodes.length, 1)
  await assert.rejects(store.getState().edit('unpin', { node_id: 1 }))
  assert.equal(calls.length, 1)
  const request = calls[0]
  const added = { ...workflow.nodes![0], id: 2, position_y: 200 }
  replies.push({ workflow: { ...workflow, nodes: [...workflow.nodes!, added] }, hash: hash('b'), edit: { id: 10, operation: 'add_node', before_hash: hash('a'), after_hash: hash('b'), created_node_id: 2 } })
  await store.getState().retryEdit()
  assert.deepEqual(calls[1], request)
  assert.deepEqual(request.data.source, { node_id: 1, port: 'main' })
  assert.equal(store.getState().rfNodes.length, 2)
  assert.equal(store.getState().selectedNodeId, '2')
  assert.equal(store.getState().undoStack.length, 1)
})

test('failed moves restore persisted positions and keep unsaved settings while navigating', async () => {
  const { store, replies } = await editor()
  store.getState().setNodeDraft(1, { name: 'My pending name', config: {} })
  store.getState().selectNode(null)
  store.getState().selectNode('1')
  assert.equal(store.getState().nodeDrafts[1].name, 'My pending name')
  store.getState().onNodesChange([{ type: 'position', id: '1', position: { x: 500, y: 600 } }])
  replies.push(new ApiError(422, null, 'Rejected'))
  await assert.rejects(store.getState().moveNodes(store.getState().rfNodes))
  assert.deepEqual(store.getState().rfNodes[0].position, { x: 40, y: 80 })
  assert.equal(store.getState().nodeDrafts[1].name, 'My pending name')
  assert.equal(store.getState().failedEdit, null)
})

test('undo and redo follow the original receipt and a new edit clears redo', async () => {
  const { store, replies, calls } = await editor()
  const receipt = { id: 10, operation: 'update_node' as const, before_hash: hash('a'), after_hash: hash('b'), created_node_id: null }
  replies.push({ workflow, hash: hash('b'), edit: receipt })
  await store.getState().edit('update_node', { node_id: 1, name: 'Changed' })
  replies.push({ workflow, hash: hash('a'), edit: { ...receipt, id: 11, operation: 'undo', before_hash: hash('b'), after_hash: hash('a') } })
  await store.getState().undo()
  assert.deepEqual(calls[1].data, { edit_id: 10 })
  assert.equal(store.getState().undoStack.length, 0)
  assert.equal(store.getState().redoStack[0].id, 10)
  replies.push({ workflow, hash: hash('b'), edit: { ...receipt, id: 12, operation: 'redo' } })
  await store.getState().redo()
  assert.deepEqual(calls[2].data, { edit_id: 10 })
  assert.equal(store.getState().undoStack[0].id, 10)
  replies.push({ workflow, hash: hash('a'), edit: { ...receipt, id: 13, operation: 'undo', before_hash: hash('b'), after_hash: hash('a') } })
  await store.getState().undo()
  replies.push({ workflow, hash: hash('c'), edit: { ...receipt, id: 14, operation: 'move_nodes', after_hash: hash('c') } })
  await store.getState().moveNodes(store.getState().rfNodes)
  assert.equal(store.getState().redoStack.length, 0)
})

test('conflicts require a refresh, preserve form drafts, and clear obsolete history', async () => {
  const { store, replies, calls } = await editor()
  store.getState().setNodeDraft(1, { name: 'My pending name', config: {} })
  replies.push(new ApiError(409, null, 'Draft changed'))
  await assert.rejects(store.getState().saveNodeDraft(1))
  assert.equal(store.getState().editConflict, true)
  await assert.rejects(store.getState().edit('remove', { node_ids: [1], edge_ids: [] }))
  assert.equal(calls.length, 1)
  await store.getState().refreshGraph()
  assert.equal(store.getState().editConflict, false)
  assert.equal(store.getState().nodeDrafts[1].name, 'My pending name')
  assert.equal(store.getState().undoStack.length, 0)
})

test('test result maps belong to one run and carry the draft hash sent to the API', async () => {
  const { store, sdk } = await editor()
  const calls: unknown[][] = []
  const responses = [
    { id: 31, workflow_id: 1, workflow_revision_id: 6, status: 'completed', node_runs: [{ node_id: 1 }, { node_id: 2 }] },
    { id: 32, workflow_id: 1, workflow_revision_id: 7, status: 'completed', node_runs: [{ node_id: 1 }] },
  ] as WorkflowRun[]
  sdk.workflows = { testNode: async (...args: unknown[]) => { calls.push(args); return { data: responses.shift()! } } } as unknown as AitumalowEditorSdk['workflows']
  const runs = createRunStore(sdk, store)
  await runs.getState().testNode(1, 2, [{ id: 7 }])
  assert.deepEqual(Object.keys(runs.getState().nodeTestResults!), ['1', '2'])
  store.setState({ graphHash: hash('b') })
  await runs.getState().testNode(1, 1)
  assert.deepEqual(Object.keys(runs.getState().nodeTestResults!), ['1'])
  assert.equal(runs.getState().testRun?.id, 32)
  assert.equal(runs.getState().testGraphHash, hash('b'))
  assert.equal(calls[1][3], hash('b'))
  store.getState().setNodeDraft(1, { name: 'Pending settings', config: {} })
  await runs.getState().testNode(1, 1)
  assert.equal(calls.length, 2)
  assert.match(runs.getState().testError!, /Save or discard/)
})
