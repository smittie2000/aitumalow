import { createStore } from 'zustand/vanilla'
import type { CapabilityDefinition, NodeType } from '../api/types'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'

export interface RegistryStore {
  nodes: CapabilityDefinition[]
  isLoading: boolean
  fetchRegistry: () => Promise<void>
  getByKey: (key: string) => CapabilityDefinition | undefined
  getGroupedByType: () => Record<NodeType, CapabilityDefinition[]>
}

export const createRegistryStore = (sdk: AitumalowEditorSdk) => createStore<RegistryStore>((set, get) => ({
  nodes: [],
  isLoading: false,

  fetchRegistry: async () => {
    if (get().nodes.length > 0) return
    set({ isLoading: true })
    try {
      const [catalogRes, scriptsRes] = await Promise.all([
        sdk.catalog.list(),
        sdk.catalog.editorScripts().catch(() => ({ data: { scripts: [] } })),
      ])
      set({ nodes: catalogRes.data })

      for (const src of scriptsRes.data.scripts) {
        if (!document.querySelector(`script[src="${src}"]`)) {
          const script = document.createElement('script')
          script.src = src
          script.type = 'module'
          document.head.appendChild(script)
        }
      }
    } finally {
      set({ isLoading: false })
    }
  },

  getByKey: (key: string) => get().nodes.find((n) => n.key === key),

  getGroupedByType: () => {
    const grouped: Record<string, CapabilityDefinition[]> = {}
    for (const node of get().nodes) {
      if (!grouped[node.type]) grouped[node.type] = []
      grouped[node.type].push(node)
    }
    return grouped as Record<NodeType, CapabilityDefinition[]>
  },
}))
