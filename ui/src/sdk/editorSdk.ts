import { createCatalogApi } from '../api/catalog'
import { createEdgesApi } from '../api/edges'
import { createFoldersApi } from '../api/folders'
import { createGraphApi } from '../api/graph'
import { createReferencesApi } from '../api/references'
import { createRevisionsApi } from '../api/revisions'
import { createNodesApi } from '../api/nodes'
import { createRunsApi } from '../api/runs'
import { createTagsApi } from '../api/tags'
import { createWorkflowsApi } from '../api/workflows'
import {
  createHttpTransport,
  type HttpTransport,
  type HttpTransportOptions,
} from '../api/client'

export interface AitumalowEditorSdk {
  transport: HttpTransport
  workflows: ReturnType<typeof createWorkflowsApi>
  nodes: ReturnType<typeof createNodesApi>
  edges: ReturnType<typeof createEdgesApi>
  catalog: ReturnType<typeof createCatalogApi>
  runs: ReturnType<typeof createRunsApi>
  tags: ReturnType<typeof createTagsApi>
  folders: ReturnType<typeof createFoldersApi>
  graph: ReturnType<typeof createGraphApi>
  references: ReturnType<typeof createReferencesApi>
  revisions: ReturnType<typeof createRevisionsApi>
}

export interface CreateEditorSdkOptions extends HttpTransportOptions {
  transport?: HttpTransport
}

export function createEditorSdk(options: CreateEditorSdkOptions = {}): AitumalowEditorSdk {
  const { transport: injectedTransport, ...httpOptions } = options
  const transport = injectedTransport ?? createHttpTransport(httpOptions)

  return {
    transport,
    workflows: createWorkflowsApi(transport),
    nodes: createNodesApi(transport),
    edges: createEdgesApi(transport),
    catalog: createCatalogApi(transport),
    runs: createRunsApi(transport),
    tags: createTagsApi(transport),
    folders: createFoldersApi(transport),
    graph: createGraphApi(transport),
    references: createReferencesApi(transport),
    revisions: createRevisionsApi(transport),
  }
}
