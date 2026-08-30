import type {
  WorkflowRevisionDefinition,
  WorkflowRevisionEdgeDefinition,
  WorkflowRevisionNodeDefinition,
} from '../api/types'

export type WorkflowDefinitionChangeKind = 'added' | 'removed' | 'changed'
export type WorkflowDefinitionChangeTarget = 'settings' | 'node' | 'connection'

export interface WorkflowDefinitionChange {
  id: string
  kind: WorkflowDefinitionChangeKind
  target: WorkflowDefinitionChangeTarget
  label: string
  summary: string
  before?: unknown
  after?: unknown
}

interface NodePair {
  key: string
  before?: WorkflowRevisionNodeDefinition
  after?: WorkflowRevisionNodeDefinition
}

const canonicalize = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(canonicalize)
  if (value === null || typeof value !== 'object') return value

  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>)
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([key, item]) => [key, canonicalize(item)]),
  )
}

const sameValue = (left: unknown, right: unknown): boolean => (
  JSON.stringify(canonicalize(left)) === JSON.stringify(canonicalize(right))
)

export const formatWorkflowDiffValue = (value: unknown): string => (
  JSON.stringify(canonicalize(value), null, 2) ?? 'null'
)

const nodeLabel = (node: WorkflowRevisionNodeDefinition): string => (
  node.name || node.key
)

const semanticNodeKey = (node: WorkflowRevisionNodeDefinition): string => (
  `${node.key}\u0000${node.name ?? ''}\u0000${node.type}`
)

const comparableNode = (node: WorkflowRevisionNodeDefinition) => ({
  capability: node.key,
  name: node.name,
  type: node.type,
  config: node.config,
  pinned_data: node.pinned_data,
  position: {
    x: node.position_x,
    y: node.position_y,
  },
})

const changedNodeFields = (
  before: WorkflowRevisionNodeDefinition,
  after: WorkflowRevisionNodeDefinition,
): string[] => {
  const fields: string[] = []
  if (before.key !== after.key || before.type !== after.type) fields.push('capability')
  if (before.name !== after.name) fields.push('name')
  if (!sameValue(before.config, after.config)) fields.push('configuration')
  if (!sameValue(before.pinned_data, after.pinned_data)) fields.push('pinned data')
  if (before.position_x !== after.position_x || before.position_y !== after.position_y) fields.push('canvas position')

  return fields
}

const pairNodes = (
  beforeNodes: WorkflowRevisionNodeDefinition[],
  afterNodes: WorkflowRevisionNodeDefinition[],
): NodePair[] => {
  const pairs: NodePair[] = []
  const remainingBefore = new Map(beforeNodes.map((node) => [String(node.id), node]))
  const remainingAfter = new Map(afterNodes.map((node) => [String(node.id), node]))
  let pairNumber = 0

  for (const [id, before] of remainingBefore) {
    const after = remainingAfter.get(id)
    if (!after) continue
    pairs.push({ key: `node-${pairNumber++}`, before, after })
    remainingBefore.delete(id)
    remainingAfter.delete(id)
  }

  const afterBySemanticKey = new Map<string, WorkflowRevisionNodeDefinition[]>()
  for (const node of remainingAfter.values()) {
    const key = semanticNodeKey(node)
    afterBySemanticKey.set(key, [...(afterBySemanticKey.get(key) ?? []), node])
  }

  for (const [id, before] of [...remainingBefore]) {
    const candidates = afterBySemanticKey.get(semanticNodeKey(before)) ?? []
    const after = candidates.shift()
    if (!after) continue

    pairs.push({ key: `node-${pairNumber++}`, before, after })
    remainingBefore.delete(id)
    remainingAfter.delete(String(after.id))
  }

  for (const before of remainingBefore.values()) {
    pairs.push({ key: `removed-${before.id}`, before })
  }
  for (const after of remainingAfter.values()) {
    pairs.push({ key: `added-${after.id}`, after })
  }

  return pairs
}

const edgeLabel = (
  edge: WorkflowRevisionEdgeDefinition,
  nodes: Map<string, WorkflowRevisionNodeDefinition>,
): string => {
  const source = nodes.get(String(edge.source_node_id))
  const target = nodes.get(String(edge.target_node_id))
  const sourceLabel = source ? nodeLabel(source) : `Node #${edge.source_node_id}`
  const targetLabel = target ? nodeLabel(target) : `Node #${edge.target_node_id}`

  return `${sourceLabel} (${edge.source_port}) → ${targetLabel} (${edge.target_port})`
}

const edgeChanges = (
  before: WorkflowRevisionDefinition,
  after: WorkflowRevisionDefinition,
  pairs: NodePair[],
): WorkflowDefinitionChange[] => {
  const beforeIdentity = new Map<string, string>()
  const afterIdentity = new Map<string, string>()
  for (const pair of pairs) {
    if (pair.before) beforeIdentity.set(String(pair.before.id), pair.key)
    if (pair.after) afterIdentity.set(String(pair.after.id), pair.key)
  }

  const signature = (
    edge: WorkflowRevisionEdgeDefinition,
    identities: Map<string, string>,
  ): string => [
    identities.get(String(edge.source_node_id)) ?? `missing-${edge.source_node_id}`,
    edge.source_port,
    identities.get(String(edge.target_node_id)) ?? `missing-${edge.target_node_id}`,
    edge.target_port,
  ].join('\u0000')

  const remainingAfter = new Map<string, WorkflowRevisionEdgeDefinition[]>()
  for (const edge of after.edges) {
    const key = signature(edge, afterIdentity)
    remainingAfter.set(key, [...(remainingAfter.get(key) ?? []), edge])
  }

  const beforeNodes = new Map(before.nodes.map((node) => [String(node.id), node]))
  const afterNodes = new Map(after.nodes.map((node) => [String(node.id), node]))
  const changes: WorkflowDefinitionChange[] = []

  before.edges.forEach((edge, index) => {
    const key = signature(edge, beforeIdentity)
    const matching = remainingAfter.get(key)
    if (matching && matching.length > 0) {
      matching.shift()
      return
    }

    changes.push({
      id: `connection-removed-${index}`,
      kind: 'removed',
      target: 'connection',
      label: edgeLabel(edge, beforeNodes),
      summary: 'Connection removed from the draft',
      before: edge,
    })
  })

  let addedIndex = 0
  for (const edges of remainingAfter.values()) {
    for (const edge of edges) {
      changes.push({
        id: `connection-added-${addedIndex++}`,
        kind: 'added',
        target: 'connection',
        label: edgeLabel(edge, afterNodes),
        summary: 'Connection added to the draft',
        after: edge,
      })
    }
  }

  return changes
}

export function buildWorkflowDefinitionChanges(
  revision: WorkflowRevisionDefinition,
  draft: WorkflowRevisionDefinition,
): WorkflowDefinitionChange[] {
  const changes: WorkflowDefinitionChange[] = []

  if (!sameValue(revision.settings, draft.settings)) {
    changes.push({
      id: 'settings',
      kind: 'changed',
      target: 'settings',
      label: 'Workflow settings',
      summary: 'Execution settings changed',
      before: revision.settings,
      after: draft.settings,
    })
  }

  const pairs = pairNodes(revision.nodes, draft.nodes)
  for (const pair of pairs) {
    if (pair.before && pair.after) {
      const fields = changedNodeFields(pair.before, pair.after)
      if (fields.length === 0) continue
      changes.push({
        id: `node-changed-${pair.key}`,
        kind: 'changed',
        target: 'node',
        label: nodeLabel(pair.after),
        summary: `Changed ${fields.join(', ')}`,
        before: comparableNode(pair.before),
        after: comparableNode(pair.after),
      })
      continue
    }

    if (pair.before) {
      changes.push({
        id: `node-removed-${pair.before.id}`,
        kind: 'removed',
        target: 'node',
        label: nodeLabel(pair.before),
        summary: 'Node removed from the draft',
        before: comparableNode(pair.before),
      })
    }
    if (pair.after) {
      changes.push({
        id: `node-added-${pair.after.id}`,
        kind: 'added',
        target: 'node',
        label: nodeLabel(pair.after),
        summary: 'Node added to the draft',
        after: comparableNode(pair.after),
      })
    }
  }

  return [...changes, ...edgeChanges(revision, draft, pairs)]
}
