import type { Node, XYPosition } from '@xyflow/react'
import type { CapabilityDefinition } from '../api/types'
import type { ActionPickerRequest } from '../components/editor/ActionPickerContext'
import type { CustomNodeData } from './mappers'

const NODE_HEIGHT = 100
const CLEARANCE = 64

export function getNodeDimensions(node: Node<CustomNodeData>): { width: number; height: number } {
  return {
    width: node.measured?.width ?? node.width ?? Math.max(240, Math.max(node.data.inputPorts.length, node.data.outputPorts.length) * 100),
    height: node.measured?.height ?? node.height ?? (node.type === 'sticky_note' ? 200 : NODE_HEIGHT),
  }
}

/** Place only the new node; saved graph positions and annotations stay put. */
export function getNewNodePosition(
  nodes: Node<CustomNodeData>[],
  capability: CapabilityDefinition,
  request: ActionPickerRequest,
  viewportCenter: XYPosition,
): XYPosition {
  const width = Math.max(240, Math.max(capability.input_ports.length, capability.output_ports.length) * 100)
  const height = capability.type === 'annotation' ? 200 : NODE_HEIGHT
  const anchor = request.position ?? viewportCenter
  const position = { x: anchor.x - width / 2, y: anchor.y - height / 2 }
  const source = request.source
  const sourceNode = source ? nodes.find((node) => node.id === source.nodeId) : undefined

  if (sourceNode && source && !request.position) {
    const size = getNodeDimensions(sourceNode)
    const ports = sourceNode.data.outputPorts
    const branch = Math.max(0, ports.indexOf(source.port)) - (ports.length - 1) / 2
    position.x = sourceNode.position.x + size.width / 2 - width / 2 + branch * (width + 80)
    position.y = sourceNode.position.y + size.height + 150
  }

  // Each collision moves past at least one occupied rectangle, so this is bounded
  // by the node count even when the canvas contains a dense row of steps.
  for (let attempt = 0; attempt < nodes.length; attempt++) {
    const collisions = nodes.filter((node) => {
      const size = getNodeDimensions(node)
      return position.x < node.position.x + size.width + CLEARANCE
        && position.x + width + CLEARANCE > node.position.x
        && position.y < node.position.y + size.height + CLEARANCE
        && position.y + height + CLEARANCE > node.position.y
    })
    if (collisions.length === 0) break
    position.x = Math.max(...collisions.map((node) => node.position.x + getNodeDimensions(node).width + CLEARANCE))
  }

  return { x: Math.round(position.x), y: Math.round(position.y) }
}
