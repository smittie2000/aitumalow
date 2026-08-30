import type { Node, Edge } from '@xyflow/react'
import type { CustomNodeData } from './mappers'
import type { WorkflowNodeRun } from '../api/types'

export interface UpstreamInput {
  nodeId: number
  nodeLabel: string
  sourcePort: string
  data: unknown
  source: 'test' | 'pinned'
}

export function getUpstreamInputs(
  targetNodeId: string,
  rfEdges: Edge[],
  rfNodes: Node<CustomNodeData>[],
  nodeTestResults: Record<number, WorkflowNodeRun> | null,
): UpstreamInput[] {
  const incomingEdges = rfEdges.filter((e) => e.target === targetNodeId)
  const results: UpstreamInput[] = []

  for (const edge of incomingEdges) {
    const sourceNode = rfNodes.find((n) => n.id === edge.source)
    if (!sourceNode) continue

    const sourceData = sourceNode.data as CustomNodeData
    const sourceApiId = sourceData.apiNode?.id
    if (!sourceApiId) continue

    const port = edge.sourceHandle ?? 'main'
    const label = sourceData.label || sourceData.nodeKey

    // Check test results first
    const testResult = nodeTestResults?.[sourceApiId]
    if (testResult?.output) {
      const portData = (testResult.output as Record<string, unknown>)[port]
      if (portData !== undefined) {
        results.push({ nodeId: sourceApiId, nodeLabel: label, sourcePort: port, data: portData, source: 'test' })
        continue
      }
    }

    // Fall back to pinned data
    const pinned = sourceData.apiNode?.pinned_data
    if (pinned?.output) {
      const portData = pinned.output[port]
      if (portData !== undefined) {
        results.push({ nodeId: sourceApiId, nodeLabel: label, sourcePort: port, data: portData, source: 'pinned' })
        continue
      }
    }
  }

  return results
}
