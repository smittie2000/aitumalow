import type { HttpTransport } from './client'
import type { ApiResponse, Workflow } from './types'

export type GraphOperation = 'add_node' | 'update_node' | 'remove' | 'connect' | 'move_nodes' | 'pin' | 'unpin' | 'undo' | 'redo'

export interface GraphEditRequest {
  request_id: string
  expected_hash: string
  operation: GraphOperation
  data: Record<string, unknown>
}

export interface GraphEditReceipt {
  id: number
  operation: GraphOperation
  before_hash: string
  after_hash: string
  created_node_id: number | null
}

export interface WorkflowGraph {
  workflow: Workflow
  hash: string
  edit?: GraphEditReceipt
}

export const createGraphApi = (client: HttpTransport) => ({
  get: (workflowId: number) => client.get<ApiResponse<WorkflowGraph>>(`/workflows/${workflowId}/graph`),
  edit: (workflowId: number, request: GraphEditRequest) => client.post<ApiResponse<WorkflowGraph>>(`/workflows/${workflowId}/graph-edits`, request),
})
