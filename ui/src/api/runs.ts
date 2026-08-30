import { api, type HttpTransport } from './client'
import type { ApiResponse, PaginatedResponse, WorkflowRun } from './types'

export const createRunsApi = (client: HttpTransport) => ({
  list: (workflowId: number, page = 1) =>
    client.get<PaginatedResponse<WorkflowRun>>(`/workflows/${workflowId}/runs?page=${page}`),

  show: (runId: number) =>
    client.get<ApiResponse<WorkflowRun>>(`/runs/${runId}`),

  cancel: (runId: number) =>
    client.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/cancel`),

  resume: (runId: number, data: { payload?: Record<string, unknown>[] }) =>
    client.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/resume`, data),

  replay: (runId: number) =>
    client.post<ApiResponse<WorkflowRun>>(`/runs/${runId}/replay`),

})

export const runsApi = createRunsApi(api)
