import { api, type HttpTransport } from './client'
import type {
  ApiResponse,
  Workflow,
  WorkflowRevision,
  WorkflowRevisionComparison,
} from './types'

export const createRevisionsApi = (client: HttpTransport) => ({
  list: (workflowId: number) =>
    client.get<ApiResponse<WorkflowRevision[]>>(`/workflows/${workflowId}/revisions`),

  compareDraft: (workflowId: number, revisionId: number) =>
    client.get<ApiResponse<WorkflowRevisionComparison>>(
      `/workflows/${workflowId}/revisions/${revisionId}/compare-draft`,
    ),

  restoreDraft: (workflowId: number, revisionId: number) =>
    client.post<ApiResponse<Workflow>>(
      `/workflows/${workflowId}/revisions/${revisionId}/restore-draft`,
    ),
})

export const revisionsApi = createRevisionsApi(api)
