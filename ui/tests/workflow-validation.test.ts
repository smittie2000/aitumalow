import assert from 'node:assert/strict'
import test from 'node:test'
import { resolveWorkflowValidationIssues } from '../src/lib/workflowValidation.ts'

const nodes = [
  { id: '12', label: 'Start' },
  { id: '42', label: 'Send email' },
]

test('validation issues resolve explicit node and edge identifiers', () => {
  const issues = resolveWorkflowValidationIssues([
    "Node 'Send email' (id:42): The recipient field is required.",
    "Edge 7: source node 'Start' does not have output port 'missing'.",
  ], nodes)

  assert.deepEqual(issues, [
    {
      message: "Node 'Send email' (id:42): The recipient field is required.",
      nodeId: '42',
      edgeId: null,
    },
    {
      message: "Edge 7: source node 'Start' does not have output port 'missing'.",
      nodeId: null,
      edgeId: '7',
    },
  ])
})

test('validation issues use a unique node name when older messages have no id', () => {
  const [issue] = resolveWorkflowValidationIssues([
    "Node 'Start' has an invalid stable state key.",
  ], nodes)

  assert.equal(issue?.nodeId, '12')
})

test('workflow-wide and ambiguous issues remain readable without a false target', () => {
  const issues = resolveWorkflowValidationIssues([
    'Workflow graph contains a hot cycle.',
    "Node 'Duplicate' has an invalid stable state key.",
  ], [
    { id: '1', label: 'Duplicate' },
    { id: '2', label: 'Duplicate' },
  ])

  assert.equal(issues[0]?.nodeId, null)
  assert.equal(issues[0]?.edgeId, null)
  assert.equal(issues[1]?.nodeId, null)
})
