import assert from 'node:assert/strict'
import test from 'node:test'
import type {
  WorkflowRevisionDefinition,
  WorkflowRevisionEdgeDefinition,
  WorkflowRevisionNodeDefinition,
} from '../src/api/types.ts'
import { buildWorkflowDefinitionChanges } from '../src/lib/workflowDefinitionDiff.ts'

const node = (
  id: number,
  key: string,
  name: string,
  config: Record<string, unknown> = {},
): WorkflowRevisionNodeDefinition => ({
  id,
  key,
  name,
  type: key === 'app.trigger' ? 'trigger' : 'action',
  config,
  pinned_data: null,
  position_x: id * 10,
  position_y: 20,
  input_ports: key === 'app.trigger' ? [] : ['main'],
  output_ports: ['main'],
})

const definition = (
  nodes: WorkflowRevisionNodeDefinition[],
  edges: WorkflowRevisionEdgeDefinition[],
  settings: Record<string, unknown> = { retry_count: 0 },
): WorkflowRevisionDefinition => ({
  version: 1,
  workflow_id: 1,
  trigger_node_id: nodes[0]?.id ?? null,
  settings,
  node_name_map: {},
  nodes,
  edges,
})

test('revision comparison reports settings, node, and connection changes', () => {
  const revision = definition(
    [node(1, 'app.trigger', 'Start'), node(2, 'app.mail', 'Send email', { subject: 'Old' })],
    [{ source_node_id: 1, source_port: 'main', target_node_id: 2, target_port: 'main' }],
  )
  const draft = definition(
    [node(1, 'app.trigger', 'Start'), node(2, 'app.mail', 'Send email', { subject: 'New' }), node(3, 'app.log', 'Log')],
    [{ source_node_id: 2, source_port: 'main', target_node_id: 3, target_port: 'main' }],
    { retry_count: 2 },
  )

  const changes = buildWorkflowDefinitionChanges(revision, draft)

  assert.deepEqual(
    changes.map((change) => [change.kind, change.target, change.label]),
    [
      ['changed', 'settings', 'Workflow settings'],
      ['changed', 'node', 'Send email'],
      ['added', 'node', 'Log'],
      ['removed', 'connection', 'Start (main) → Send email (main)'],
      ['added', 'connection', 'Send email (main) → Log (main)'],
    ],
  )
})

test('revision comparison pairs restored nodes semantically when database ids changed', () => {
  const revision = definition([
    node(10, 'app.trigger', 'Start'),
    node(11, 'app.mail', 'Send email', { subject: 'Old' }),
  ], [])
  const draft = definition([
    node(20, 'app.trigger', 'Start'),
    node(21, 'app.mail', 'Send email', { subject: 'New' }),
  ], [])

  const changes = buildWorkflowDefinitionChanges(revision, draft)

  assert.equal(changes.length, 2)
  assert.deepEqual(changes.map((change) => change.kind), ['changed', 'changed'])
  assert.match(changes[0]?.summary ?? '', /canvas position/)
  assert.match(changes[1]?.summary ?? '', /configuration/)
})
