import { api, type HttpTransport } from './client'
import type { CapabilityDefinition } from './types'

export const createCatalogApi = (client: HttpTransport) => ({
  list: () =>
    client.get<{ data: CapabilityDefinition[] }>('/catalog'),

  editorScripts: () =>
    client.get<{ data: { scripts: string[] } }>('/catalog/editor-scripts'),
})

export const catalogApi = createCatalogApi(api)
