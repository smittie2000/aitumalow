import { Graph, layout, type EdgeLabel, type GraphLabel, type NodeLabel } from '@dagrejs/dagre'
import type { Node, Edge } from '@xyflow/react'
import type { CustomNodeData } from './mappers'

const DEFAULT_NODE_WIDTH = 200
const DEFAULT_NODE_HEIGHT = 60

function getNodeDimensions(node: Node<CustomNodeData>): { width: number; height: number } {
  return {
    width: node.measured?.width ?? node.width ?? DEFAULT_NODE_WIDTH,
    height: node.measured?.height ?? node.height ?? DEFAULT_NODE_HEIGHT,
  }
}

export function getAutoLayoutPositions(
  nodes: Node<CustomNodeData>[],
  edges: Edge[],
  direction: 'LR' | 'TB' = 'LR',
): Node<CustomNodeData>[] {
  const layoutNodes = nodes.filter((node) => node.type !== 'sticky_note')
  const layoutNodeIds = new Set(layoutNodes.map((node) => node.id))
  const dimensions = new Map(layoutNodes.map((node) => [node.id, getNodeDimensions(node)]))
  const graph = new Graph<GraphLabel, NodeLabel, EdgeLabel>({ multigraph: true })
    .setDefaultEdgeLabel(() => ({}))

  graph.setGraph({
    rankdir: direction,
    nodesep: 50,
    edgesep: 30,
    ranksep: 120,
    marginx: 20,
    marginy: 20,
    acyclicer: 'greedy',
    ranker: 'network-simplex',
  })

  for (const node of layoutNodes) {
    graph.setNode(node.id, dimensions.get(node.id)!)
  }

  for (const edge of edges) {
    if (layoutNodeIds.has(edge.source) && layoutNodeIds.has(edge.target)) {
      graph.setEdge(edge.source, edge.target, {}, edge.id)
    }
  }

  layout(graph, { useDynamic: false })

  return nodes.map((node) => {
    if (!layoutNodeIds.has(node.id)) return node

    const position = graph.node(node.id)
    const size = dimensions.get(node.id)!

    return {
      ...node,
      position: {
        x: (position.x ?? 0) - size.width / 2,
        y: (position.y ?? 0) - size.height / 2,
      },
    }
  })
}

export function allNodesAtOrigin(nodes: Node<CustomNodeData>[]): boolean {
  if (nodes.length <= 1) return false
  return nodes.every((n) => n.position.x === 0 && n.position.y === 0)
}
