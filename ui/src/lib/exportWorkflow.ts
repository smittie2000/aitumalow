import type { Workflow } from '../api/types'

export interface ExportedWorkflow {
  name: string
  description: string | null
  is_active: boolean
  settings: Record<string, unknown> | null
  nodes: ExportedNode[]
  edges: ExportedEdge[]
}

interface ExportedNode {
  node_key: string
  type: string
  name: string | null
  config: Record<string, unknown> | null
  position_x: number | null
  position_y: number | null
}

interface ExportedEdge {
  source_node_index: number
  target_node_index: number
  source_port: string
  target_port: string
}

function downloadFile(content: string, filename: string, mimeType: string) {
  const blob = new Blob([content], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

export function exportAsJson(workflow: Workflow) {
  const nodes = workflow.nodes ?? []
  const edges = workflow.edges ?? []

  const nodeIdToIndex = new Map<number, number>()
  nodes.forEach((node, i) => nodeIdToIndex.set(node.id, i))

  const exported: ExportedWorkflow = {
    name: workflow.name,
    description: workflow.description,
    is_active: workflow.is_active,
    settings: workflow.settings,
    nodes: nodes.map((n) => ({
      node_key: n.node_key,
      type: n.type,
      name: n.name,
      config: n.config,
      position_x: n.position_x,
      position_y: n.position_y,
    })),
    edges: edges.map((e) => ({
      source_node_index: nodeIdToIndex.get(e.source_node_id) ?? 0,
      target_node_index: nodeIdToIndex.get(e.target_node_id) ?? 0,
      source_port: e.source_port,
      target_port: e.target_port,
    })),
  }

  const slug = workflow.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  downloadFile(JSON.stringify(exported, null, 2), `${slug}.workflow.json`, 'application/json')
}
