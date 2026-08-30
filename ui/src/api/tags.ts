import { api, type HttpTransport } from './client'
import type { ApiResponse, WorkflowTag } from './types'

export const createTagsApi = (client: HttpTransport) => ({
  list: (search?: string) => {
    const params = new URLSearchParams()
    if (search) params.set('search', search)
    return client.get<{ data: WorkflowTag[] }>(`/tags?${params}`)
  },

  create: (data: { name: string; color?: string }) =>
    client.post<ApiResponse<WorkflowTag>>('/tags', data),

  update: (id: number, data: { name?: string; color?: string }) =>
    client.put<ApiResponse<WorkflowTag>>(`/tags/${id}`, data),

  destroy: (id: number) =>
    client.delete<void>(`/tags/${id}`),
})

export const tagsApi = createTagsApi(api)
