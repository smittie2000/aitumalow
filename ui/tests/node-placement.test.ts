import assert from 'node:assert/strict'
import test from 'node:test'
import type { Node } from '@xyflow/react'
import type { CapabilityDefinition } from '../src/api/types.ts'
import type { CustomNodeData } from '../src/lib/mappers.ts'
import { getNewNodePosition, getNodeDimensions } from '../src/lib/nodePlacement.ts'

const action = { type: 'action', input_ports: ['main'], output_ports: ['main'] } as CapabilityDefinition
const node = (id: string, x: number, y: number, outputPorts = ['main']): Node<CustomNodeData> => ({
  id, type: 'custom', position: { x, y },
  data: { inputPorts: ['main'], outputPorts } as CustomNodeData,
})

test('unconnected additions follow the visible viewport instead of the last saved node', () => {
  const nodes = [node('distant', 5000, 8000)]
  const before = structuredClone(nodes)
  assert.deepEqual(getNewNodePosition(nodes, action, {}, { x: 400, y: 300 }), { x: 280, y: 250 })
  assert.deepEqual(nodes, before)
})

test('each branch gets space below its measured source', () => {
  const source = { ...node('branch', 400, 100, ['true', 'false']), measured: { width: 400, height: 160 } }
  const left = getNewNodePosition([source], action, { source: { nodeId: 'branch', port: 'true' } }, { x: 0, y: 0 })
  const right = getNewNodePosition([source], action, { source: { nodeId: 'branch', port: 'false' } }, { x: 0, y: 0 })
  assert.ok(left.x + 240 < right.x)
  assert.equal(left.y, 410)
  assert.equal(right.y, 410)
})

test('dropping an output on empty space uses the drop location', () => {
  assert.deepEqual(getNewNodePosition([node('source', 0, 0)], action, {
    source: { nodeId: 'source', port: 'main' }, position: { x: 900, y: 400 },
  }, { x: 200, y: 200 }), { x: 780, y: 350 })
})

test('placement clears an entire occupied row without moving any existing nodes', () => {
  const nodes = [node('one', 0, 0), node('two', 300, 0), node('three', 620, 0)]
  const before = structuredClone(nodes)
  const position = getNewNodePosition(nodes, action, { position: { x: 120, y: 50 } }, { x: 0, y: 0 })
  assert.ok(position.x >= 620 + 240 + 64)
  assert.equal(position.y, 0)
  assert.deepEqual(nodes, before)
})

test('unmeasured multi-port nodes reserve their rendered minimum width', () => {
  const wide = node('wide', 0, 0, ['one', 'two', 'three', 'four', 'five'])
  assert.equal(getNodeDimensions(wide).width, 500)
  const position = getNewNodePosition([wide], action, {}, { x: 520, y: 50 })
  assert.ok(position.x >= 564)
})

test('annotations are obstacles and large new nodes get their own full clearance', () => {
  const note = { ...node('note', 400, 200), type: 'sticky_note', measured: { width: 600, height: 400 } }
  const wideAction = { ...action, output_ports: ['one', 'two', 'three', 'four', 'five'] }
  const position = getNewNodePosition([note], wideAction, {}, { x: 400, y: 400 })
  assert.ok(position.x >= 1064)
  assert.deepEqual(note.position, { x: 400, y: 200 })
})
