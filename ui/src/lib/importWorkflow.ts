import type { CapabilityDefinition, Workflow } from '../api/types'
import type { ExportedWorkflow } from './exportWorkflow'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'

export interface ImportIssue {
  type: 'error' | 'warning'
  message: string
}

export function validateWorkflowJson(data: unknown): ImportIssue[] {
  const issues: ImportIssue[] = []

  if (!data || typeof data !== 'object') {
    issues.push({ type: 'error', message: 'Invalid JSON structure' })
    return issues
  }

  const wf = data as Record<string, unknown>

  if (!wf.name || typeof wf.name !== 'string' || !wf.name.trim()) {
    issues.push({ type: 'error', message: 'Workflow name is required' })
  }

  if (!Array.isArray(wf.nodes)) {
    issues.push({ type: 'error', message: 'Nodes must be an array' })
    return issues
  }

  if (!Array.isArray(wf.edges)) {
    issues.push({ type: 'error', message: 'Edges must be an array' })
    return issues
  }

  for (let i = 0; i < wf.nodes.length; i++) {
    const node = wf.nodes[i] as Record<string, unknown>
    if (!node.node_key || typeof node.node_key !== 'string') {
      issues.push({ type: 'error', message: `Node at index ${i} is missing node_key` })
    }
    if (!node.type || typeof node.type !== 'string') {
      issues.push({ type: 'error', message: `Node at index ${i} is missing type` })
    }
  }

  for (let i = 0; i < wf.edges.length; i++) {
    const edge = wf.edges[i] as Record<string, unknown>
    const srcIdx = edge.source_node_index
    const tgtIdx = edge.target_node_index

    if (typeof srcIdx !== 'number' || srcIdx < 0 || srcIdx >= wf.nodes.length) {
      issues.push({ type: 'error', message: `Edge at index ${i} references invalid source node index (${srcIdx})` })
    }
    if (typeof tgtIdx !== 'number' || tgtIdx < 0 || tgtIdx >= wf.nodes.length) {
      issues.push({ type: 'error', message: `Edge at index ${i} references invalid target node index (${tgtIdx})` })
    }
    if (!edge.source_port || typeof edge.source_port !== 'string') {
      issues.push({ type: 'error', message: `Edge at index ${i} is missing source_port` })
    }
    if (!edge.target_port || typeof edge.target_port !== 'string') {
      issues.push({ type: 'error', message: `Edge at index ${i} is missing target_port` })
    }
  }

  return issues
}

export function checkRegistryCompatibility(
  data: ExportedWorkflow,
  registryNodes: CapabilityDefinition[],
): ImportIssue[] {
  const issues: ImportIssue[] = []
  const registryMap = new Map(registryNodes.map((n) => [n.key, n]))

  for (let i = 0; i < data.nodes.length; i++) {
    const node = data.nodes[i]
    const reg = registryMap.get(node.node_key)

    if (!reg) {
      issues.push({
        type: 'error',
        message: `Node "${node.name || node.node_key}" uses "${node.node_key}" which is not available in this installation`,
      })
    } else if (reg.type !== node.type) {
      issues.push({
        type: 'warning',
        message: `Node "${node.name || node.node_key}" type mismatch: expected "${reg.type}", got "${node.type}"`,
      })
    }
  }

  return issues
}

export function checkNameConflict(
  name: string,
  existingWorkflows: Workflow[],
): ImportIssue[] {
  const conflict = existingWorkflows.some(
    (wf) => wf.name.toLowerCase() === name.toLowerCase(),
  )
  if (conflict) {
    return [{ type: 'warning', message: `A workflow named "${name}" already exists` }]
  }
  return []
}

export async function importWorkflow(
  sdk: AitumalowEditorSdk,
  data: ExportedWorkflow,
  overrideName?: string,
): Promise<Workflow> {
  const name = overrideName?.trim() || data.name
  const res = await sdk.workflows.create({
    name,
    description: data.description ?? undefined,
    is_active: false,
    created_via: 'import',
  })
  const workflow = res.data
  const wfId = workflow.id

  // Create nodes and build index → id mapping
  const nodeIdMap = new Map<number, number>()

  for (let i = 0; i < data.nodes.length; i++) {
    const node = data.nodes[i]
    const created = await sdk.nodes.create(wfId, {
      node_key: node.node_key,
      name: node.name ?? undefined,
      config: node.config ?? undefined,
      position_x: node.position_x ?? undefined,
      position_y: node.position_y ?? undefined,
    })
    nodeIdMap.set(i, created.data.id)
  }

  // Create edges
  for (const edge of data.edges) {
    const sourceId = nodeIdMap.get(edge.source_node_index)
    const targetId = nodeIdMap.get(edge.target_node_index)
    if (sourceId == null || targetId == null) continue

    await sdk.edges.create(wfId, {
      source_node_id: sourceId,
      target_node_id: targetId,
      source_port: edge.source_port,
      target_port: edge.target_port,
    })
  }

  return workflow
}
