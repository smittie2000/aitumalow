import type { Node, Edge } from '@xyflow/react'
import type { WorkflowNode, WorkflowEdge, CapabilityDefinition } from '../api/types'

export interface CustomNodeData extends Record<string, unknown> {
  apiNode: WorkflowNode
  registryNode: CapabilityDefinition | undefined
  label: string
  nodeKey: string
  nodeType: string
  inputPorts: string[]
  outputPorts: string[]
  invalid?: boolean
  validationMessage?: string | null
}

export function apiNodeToRFNode(
  apiNode: WorkflowNode,
  registryNode?: CapabilityDefinition,
): Node<CustomNodeData> {
  const isStickyNote = apiNode.node_key === 'core.sticky_note'

  return {
    id: String(apiNode.id),
    type: isStickyNote ? 'sticky_note' : 'custom',
    position: {
      x: apiNode.position_x ?? 0,
      y: apiNode.position_y ?? 0,
    },
    data: {
      apiNode,
      registryNode,
      label: apiNode.name || registryNode?.name || apiNode.node_key,
      nodeKey: apiNode.node_key,
      nodeType: apiNode.type,
      inputPorts: isStickyNote ? [] : (registryNode?.input_ports ?? ['main']),
      outputPorts: isStickyNote ? [] : (registryNode?.output_ports ?? ['main']),
    },
  }
}

export function apiEdgeToRFEdge(apiEdge: WorkflowEdge): Edge {
  return {
    id: String(apiEdge.id),
    source: String(apiEdge.source_node_id),
    target: String(apiEdge.target_node_id),
    sourceHandle: apiEdge.source_port,
    targetHandle: apiEdge.target_port,
    type: 'smoothstep',
    interactionWidth: 24,
    style: { strokeWidth: 2 },
    data: { apiEdge },
  }
}
