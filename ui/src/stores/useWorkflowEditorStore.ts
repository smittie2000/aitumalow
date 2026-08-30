import { createStore } from 'zustand/vanilla'
import {
  applyNodeChanges,
  applyEdgeChanges,
  type Node,
  type Edge,
  type OnNodesChange,
  type OnEdgesChange,
  type Connection,
} from '@xyflow/react'
import type { Workflow, WorkflowNode, CapabilityDefinition } from '../api/types'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'
import { apiNodeToRFNode, apiEdgeToRFEdge, type CustomNodeData } from '../lib/mappers'
import { getAutoLayoutPositions, allNodesAtOrigin } from '../lib/autoLayout'

export interface WorkflowEditorStore {
  workflow: Workflow | null
  isLoading: boolean

  rfNodes: Node<CustomNodeData>[]
  rfEdges: Edge[]

  selectedNodeId: string | null
  selectedApiNode: WorkflowNode | null
  selectedRegistryNode: CapabilityDefinition | undefined

  loadWorkflow: (id: number, registryLookup: (key: string) => CapabilityDefinition | undefined) => Promise<void>
  updateWorkflowMeta: (data: { name?: string; description?: string; folder_id?: number | null; tag_ids?: number[]; settings?: Record<string, unknown> | null }) => Promise<void>

  addNode: (
    nodeKey: string,
    position: { x: number; y: number },
    registryNode: CapabilityDefinition,
  ) => Promise<void>
  updateNodeConfig: (nodeId: number, config: Record<string, unknown>) => Promise<void>
  setNodeLabel: (nodeId: number, label: string) => void
  setNodeConfig: (nodeId: number, config: Record<string, unknown>) => void
  updateNodeLabel: (nodeId: number, label: string) => Promise<void>
  deleteNode: (nodeId: number) => Promise<void>
  updateNodePosition: (nodeId: number, x: number, y: number) => Promise<void>

  autoLayout: () => Promise<void>

  addEdge: (connection: Connection) => Promise<void>
  deleteEdge: (edgeId: number) => Promise<void>

  onNodesChange: OnNodesChange
  onEdgesChange: OnEdgesChange

  pinNode: (nodeId: number, data: { source: 'run'; node_run_id: number } | { source: 'manual'; input?: unknown[]; output?: Record<string, unknown[]> }) => Promise<void>
  unpinNode: (nodeId: number) => Promise<void>

  selectNode: (nodeId: string | null) => void
  reset: () => void
}

export const createWorkflowEditorStore = (sdk: AitumalowEditorSdk) => createStore<WorkflowEditorStore>((set, get) => ({
  workflow: null,
  isLoading: false,
  rfNodes: [],
  rfEdges: [],
  selectedNodeId: null,
  selectedApiNode: null,
  selectedRegistryNode: undefined,

  loadWorkflow: async (id, registryLookup) => {
    set({ isLoading: true })
    try {
      const res = await sdk.workflows.show(id)
      const wf = res.data
      let rfNodes = (wf.nodes ?? []).map((n) => apiNodeToRFNode(n, registryLookup(n.node_key)))
      const rfEdges = (wf.edges ?? []).map(apiEdgeToRFEdge)
      if (allNodesAtOrigin(rfNodes)) {
        rfNodes = getAutoLayoutPositions(rfNodes, rfEdges)
      }
      set({ workflow: wf, rfNodes, rfEdges, selectedNodeId: null, selectedApiNode: null })
    } finally {
      set({ isLoading: false })
    }
  },

  updateWorkflowMeta: async (data) => {
    const wf = get().workflow
    if (!wf) return
    const res = await sdk.workflows.update(wf.id, data)
    set({ workflow: res.data })
  },

  addNode: async (nodeKey, position, registryNode) => {
    const wf = get().workflow
    if (!wf) return
    const config = Object.fromEntries(
      registryNode.config_schema
        .filter((field) => field.default !== undefined)
        .map((field) => [field.key, field.default]),
    )
    const res = await sdk.nodes.create(wf.id, {
      node_key: nodeKey,
      name: registryNode.name,
      config,
      position_x: Math.round(position.x),
      position_y: Math.round(position.y),
    })
    const newNode = apiNodeToRFNode(res.data, registryNode)
    set({ rfNodes: [...get().rfNodes, newNode] })
  },

  updateNodeConfig: async (nodeId, config) => {
    const wf = get().workflow
    if (!wf) return
    const res = await sdk.nodes.update(wf.id, nodeId, { config })
    set({
      rfNodes: get().rfNodes.map((n) => {
        if (n.id === String(nodeId)) {
          return {
            ...n,
            data: { ...n.data, apiNode: res.data },
          }
        }
        return n
      }),
      selectedApiNode: get().selectedNodeId === String(nodeId) ? res.data : get().selectedApiNode,
    })
  },

  setNodeLabel: (nodeId, label) => {
    set({
      rfNodes: get().rfNodes.map((n) => {
        if (n.id === String(nodeId)) {
          return { ...n, data: { ...n.data, label } }
        }
        return n
      }),
    })
  },

  setNodeConfig: (nodeId, config) => {
    set({
      rfNodes: get().rfNodes.map((n) => {
        if (n.id === String(nodeId)) {
          return { ...n, data: { ...n.data, apiNode: { ...n.data.apiNode, config } } }
        }
        return n
      }),
    })
  },

  updateNodeLabel: async (nodeId, label) => {
    const wf = get().workflow
    if (!wf) return
    const res = await sdk.nodes.update(wf.id, nodeId, { label })
    set({
      rfNodes: get().rfNodes.map((n) => {
        if (n.id === String(nodeId)) {
          return {
            ...n,
            data: { ...n.data, label: res.data.name || label, apiNode: res.data },
          }
        }
        return n
      }),
    })
  },

  deleteNode: async (nodeId) => {
    const wf = get().workflow
    if (!wf) return
    await sdk.nodes.destroy(wf.id, nodeId)
    const nodeIdStr = String(nodeId)
    set({
      rfNodes: get().rfNodes.filter((n) => n.id !== nodeIdStr),
      rfEdges: get().rfEdges.filter(
        (e) => e.source !== nodeIdStr && e.target !== nodeIdStr,
      ),
      selectedNodeId: get().selectedNodeId === nodeIdStr ? null : get().selectedNodeId,
      selectedApiNode: get().selectedNodeId === nodeIdStr ? null : get().selectedApiNode,
    })
  },

  updateNodePosition: async (nodeId, x, y) => {
    const wf = get().workflow
    if (!wf) return
    await sdk.nodes.updatePosition(wf.id, nodeId, {
      position_x: Math.round(x),
      position_y: Math.round(y),
    })
  },

  autoLayout: async () => {
    const wf = get().workflow
    if (!wf) return
    const layoutedNodes = getAutoLayoutPositions(get().rfNodes, get().rfEdges)
    set({ rfNodes: layoutedNodes })
    await Promise.all(
      layoutedNodes.map((n) =>
        sdk.nodes.updatePosition(wf.id, parseInt(n.id), {
          position_x: Math.round(n.position.x),
          position_y: Math.round(n.position.y),
        }),
      ),
    )
  },

  addEdge: async (connection) => {
    const wf = get().workflow
    if (!wf || !connection.source || !connection.target) return
    const res = await sdk.edges.create(wf.id, {
      source_node_id: parseInt(connection.source),
      target_node_id: parseInt(connection.target),
      source_port: connection.sourceHandle || 'main',
      target_port: connection.targetHandle || 'main',
    })
    const newEdge = apiEdgeToRFEdge(res.data)
    set({ rfEdges: [...get().rfEdges, newEdge] })
  },

  deleteEdge: async (edgeId) => {
    const wf = get().workflow
    if (!wf) return
    await sdk.edges.destroy(wf.id, edgeId)
    set({ rfEdges: get().rfEdges.filter((e) => e.id !== String(edgeId)) })
  },

  onNodesChange: (changes) => {
    set({ rfNodes: applyNodeChanges(changes, get().rfNodes) as Node<CustomNodeData>[] })
  },

  onEdgesChange: (changes) => {
    set({ rfEdges: applyEdgeChanges(changes, get().rfEdges) })
  },

  pinNode: async (nodeId, data) => {
    const wf = get().workflow
    if (!wf) return
    const res = await sdk.nodes.pin(wf.id, nodeId, data)
    const updatedApiNode = res.data
    set({
      rfNodes: get().rfNodes.map((n) =>
        n.id === String(nodeId)
          ? { ...n, data: { ...n.data, apiNode: updatedApiNode } }
          : n,
      ),
      selectedApiNode: get().selectedNodeId === String(nodeId) ? updatedApiNode : get().selectedApiNode,
    })
  },

  unpinNode: async (nodeId) => {
    const wf = get().workflow
    if (!wf) return
    const res = await sdk.nodes.unpin(wf.id, nodeId)
    const updatedApiNode = res.data
    set({
      rfNodes: get().rfNodes.map((n) =>
        n.id === String(nodeId)
          ? { ...n, data: { ...n.data, apiNode: updatedApiNode } }
          : n,
      ),
      selectedApiNode: get().selectedNodeId === String(nodeId) ? updatedApiNode : get().selectedApiNode,
    })
  },

  selectNode: (nodeId) => {
    if (!nodeId) {
      set({ selectedNodeId: null, selectedApiNode: null, selectedRegistryNode: undefined })
      return
    }
    const node = get().rfNodes.find((n) => n.id === nodeId)
    if (node) {
      set({
        selectedNodeId: nodeId,
        selectedApiNode: node.data.apiNode,
        selectedRegistryNode: node.data.registryNode,
      })
    }
  },

  reset: () => {
    set({
      workflow: null,
      rfNodes: [],
      rfEdges: [],
      selectedNodeId: null,
      selectedApiNode: null,
      selectedRegistryNode: undefined,
    })
  },
}))
