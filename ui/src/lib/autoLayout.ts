import Dagre from '@dagrejs/dagre'
import type { Node, Edge } from '@xyflow/react'
import type { CustomNodeData } from './mappers'

const NODE_WIDTH = 200
const NODE_HEIGHT = 60

export function getAutoLayoutPositions(
  nodes: Node<CustomNodeData>[],
  edges: Edge[],
  direction: 'LR' | 'TB' = 'LR',
): Node<CustomNodeData>[] {
  const g = new Dagre.graphlib.Graph().setDefaultEdgeLabel(() => ({}))

  g.setGraph({
    rankdir: direction,
    nodesep: 50,
    ranksep: 120,
    marginx: 20,
    marginy: 20,
  })

  for (const node of nodes) {
    g.setNode(node.id, { width: NODE_WIDTH, height: NODE_HEIGHT })
  }

  for (const edge of edges) {
    g.setEdge(edge.source, edge.target)
  }

  Dagre.layout(g)

  return nodes.map((node) => {
    const pos = g.node(node.id)
    return {
      ...node,
      position: {
        x: pos.x - NODE_WIDTH / 2,
        y: pos.y - NODE_HEIGHT / 2,
      },
    }
  })
}

export function allNodesAtOrigin(nodes: Node<CustomNodeData>[]): boolean {
  if (nodes.length <= 1) return false
  return nodes.every((n) => n.position.x === 0 && n.position.y === 0)
}
