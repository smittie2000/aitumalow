export interface WorkflowValidationNodeReference {
  id: string
  label: string
}

export interface WorkflowValidationIssue {
  message: string
  nodeId: string | null
  edgeId: string | null
}

export function resolveWorkflowValidationIssues(
  messages: string[],
  nodes: WorkflowValidationNodeReference[],
): WorkflowValidationIssue[] {
  return messages.map((message) => {
    const explicitNodeId = message.match(/\(id:(\d+)\)/)?.[1] ?? null
    const edgeId = message.match(/^Edge (\d+)(?:\b|:)/)?.[1] ?? null
    const nodeName = message.match(/^Node '([^']+)'/)?.[1]
    const namedMatches = nodeName
      ? nodes.filter((node) => node.label === nodeName)
      : []

    return {
      message,
      nodeId: explicitNodeId ?? (namedMatches.length === 1 ? namedMatches[0].id : null),
      edgeId,
    }
  })
}
