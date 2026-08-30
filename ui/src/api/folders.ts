import { api, type HttpTransport } from './client'
import type { ApiResponse, WorkflowFolder } from './types'

export const createFoldersApi = (client: HttpTransport) => ({
  list: (tree = false) => {
    const params = tree ? '?tree=1' : ''
    return client.get<{ data: WorkflowFolder[] }>(`/folders${params}`)
  },

  create: (data: { name: string; parent_id?: number | null }) =>
    client.post<ApiResponse<WorkflowFolder>>('/folders', data),

  update: (id: number, data: { name?: string; parent_id?: number | null }) =>
    client.put<ApiResponse<WorkflowFolder>>(`/folders/${id}`, data),

  destroy: (id: number) =>
    client.delete<void>(`/folders/${id}`),
})

export const foldersApi = createFoldersApi(api)
