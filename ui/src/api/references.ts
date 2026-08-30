import type { HttpTransport } from './client'
import type { ReferenceOption } from './types'

export const createReferencesApi = (client: HttpTransport) => ({
  list: (workflowId: number, source: string) =>
    client.get<{ data: ReferenceOption[] }>(`/workflows/${workflowId}/references/${encodeURIComponent(source)}`),
})
