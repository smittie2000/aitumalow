import { api, type HttpTransport } from './client'
import type {
  ApiResponse,
  AvailableVariablesResponse,
  CreateNodePayload,
  UpdateNodePayload,
  UpdateNodePositionPayload,
  WorkflowNode,
} from './types'

export const createNodesApi = (client: HttpTransport) => ({
  create: (workflowId: number, data: CreateNodePayload) =>
    client.post<ApiResponse<WorkflowNode>>(`/workflows/${workflowId}/nodes`, data),

  update: (workflowId: number, nodeId: number, data: UpdateNodePayload) =>
    client.put<ApiResponse<WorkflowNode>>(`/workflows/${workflowId}/nodes/${nodeId}`, data),

  destroy: (workflowId: number, nodeId: number) =>
    client.delete<void>(`/workflows/${workflowId}/nodes/${nodeId}`),

  updatePosition: (workflowId: number, nodeId: number, data: UpdateNodePositionPayload) =>
    client.patch<ApiResponse<WorkflowNode>>(`/workflows/${workflowId}/nodes/${nodeId}/position`, data),

  availableVariables: (workflowId: number, nodeId: number) =>
    client.get<AvailableVariablesResponse>(`/workflows/${workflowId}/nodes/${nodeId}/variables`),

  pin: (workflowId: number, nodeId: number, data: { source: 'run'; node_run_id: number } | { source: 'manual'; input?: unknown[]; output?: Record<string, unknown[]> }) =>
    client.post<ApiResponse<WorkflowNode>>(`/workflows/${workflowId}/nodes/${nodeId}/pin`, data),

  unpin: (workflowId: number, nodeId: number) =>
    client.delete<ApiResponse<WorkflowNode>>(`/workflows/${workflowId}/nodes/${nodeId}/pin`),
})

export const nodesApi = createNodesApi(api)
