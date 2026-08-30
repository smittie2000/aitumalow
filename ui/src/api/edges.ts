import { api, type HttpTransport } from './client'
import type { ApiResponse, CreateEdgePayload, WorkflowEdge } from './types'

export const createEdgesApi = (client: HttpTransport) => ({
  create: (workflowId: number, data: CreateEdgePayload) =>
    client.post<ApiResponse<WorkflowEdge>>(`/workflows/${workflowId}/edges`, data),

  destroy: (workflowId: number, edgeId: number) =>
    client.delete<void>(`/workflows/${workflowId}/edges/${edgeId}`),
})

export const edgesApi = createEdgesApi(api)
