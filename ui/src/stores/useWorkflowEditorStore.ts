import { createStore } from 'zustand/vanilla'
import {
  applyNodeChanges, applyEdgeChanges,
  type Node, type Edge, type OnNodesChange, type OnEdgesChange, type Connection,
} from '@xyflow/react'
import type { Workflow, WorkflowNode, CapabilityDefinition } from '../api/types'
import type { GraphEditRequest, GraphEditReceipt, GraphOperation, WorkflowGraph } from '../api/graph'
import { ApiError, apiErrorMessage } from '../api/client.ts'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'
import { apiNodeToRFNode, apiEdgeToRFEdge, type CustomNodeData } from '../lib/mappers.ts'
import { getAutoLayoutPositions } from '../lib/autoLayout.ts'
import { resolveWorkflowValidationIssues, type WorkflowValidationIssue } from '../lib/workflowValidation.ts'

export interface WorkflowValidationFocus { issue: WorkflowValidationIssue; token: number }
export interface NodeDraft { name: string; config: Record<string, unknown> }
export interface AddNodeConnection { source?: { node_id: number; port: string }; edge_id?: number; input_port?: string; output_port?: string }
type PinRequest = { source: 'run'; node_run_id: number } | { source: 'manual'; input?: unknown[]; output?: Record<string, unknown[]> }

export interface WorkflowEditorStore {
  workflow: Workflow | null
  isLoading: boolean
  rfNodes: Node<CustomNodeData>[]
  rfEdges: Edge[]
  selectedNodeId: string | null
  selectedApiNode: WorkflowNode | null
  selectedRegistryNode: CapabilityDefinition | undefined
  validationIssues: WorkflowValidationIssue[]
  validationFocus: WorkflowValidationFocus | null
  graphHash: string | null
  isEditing: boolean
  editError: string | null
  editConflict: boolean
  failedEdit: GraphEditRequest | null
  undoStack: GraphEditReceipt[]
  redoStack: GraphEditReceipt[]
  nodeDrafts: Record<number, NodeDraft>
  loadWorkflow: (id: number, registryLookup: (key: string) => CapabilityDefinition | undefined) => Promise<void>
  refreshGraph: () => Promise<void>
  updateWorkflowMeta: (data: { name?: string; description?: string; folder_id?: number | null; tag_ids?: number[]; settings?: Record<string, unknown> | null }) => Promise<void>
  submitEdit: (request: GraphEditRequest) => Promise<GraphEditReceipt | undefined>
  edit: (operation: GraphOperation, data: Record<string, unknown>) => Promise<GraphEditReceipt | undefined>
  retryEdit: () => Promise<void>
  undo: () => Promise<void>
  redo: () => Promise<void>
  addNode: (nodeKey: string, position: { x: number; y: number }, registryNode: CapabilityDefinition, connection?: AddNodeConnection) => Promise<string | undefined>
  setNodeDraft: (nodeId: number, draft: NodeDraft) => void
  discardNodeDraft: (nodeId: number) => void
  saveNodeDraft: (nodeId: number) => Promise<void>
  remove: (nodeIds: number[], edgeIds: number[]) => Promise<void>
  moveNodes: (nodes: Node<CustomNodeData>[]) => Promise<void>
  autoLayout: () => Promise<void>
  addEdge: (connection: Connection) => Promise<void>
  onNodesChange: OnNodesChange
  onEdgesChange: OnEdgesChange
  pinNode: (nodeId: number, data: PinRequest) => Promise<void>
  unpinNode: (nodeId: number) => Promise<void>
  selectNode: (nodeId: string | null) => void
  setValidationErrors: (errors: string[]) => void
  focusValidationIssue: (issue: WorkflowValidationIssue) => void
  clearValidationErrors: () => void
  reset: () => void
}

function newEditId(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  bytes[6] = (bytes[6] & 15) | 64
  bytes[8] = (bytes[8] & 63) | 128
  const hex = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

const initialState = {
  workflow: null, isLoading: false, rfNodes: [], rfEdges: [], selectedNodeId: null,
  selectedApiNode: null, selectedRegistryNode: undefined, validationIssues: [], validationFocus: null,
  graphHash: null, isEditing: false, editError: null, editConflict: false, failedEdit: null,
  undoStack: [], redoStack: [], nodeDrafts: {},
}

export const createWorkflowEditorStore = (sdk: AitumalowEditorSdk) => {
  let registryLookup: (key: string) => CapabilityDefinition | undefined = () => undefined
  let generation = 0
  return createStore<WorkflowEditorStore>((set, get) => {
    const renderGraph = (graph: WorkflowGraph) => {
      const state = get()
      const rfNodes = (graph.workflow.nodes ?? []).map((node) => {
        const previous = state.rfNodes.find((item) => item.id === String(node.id))
        const mapped = apiNodeToRFNode(node, registryLookup(node.node_key))
        const draft = state.nodeDrafts[node.id]
        return {
          ...mapped, measured: previous?.measured, selected: previous?.selected ?? false,
          data: { ...mapped.data, label: draft?.name || mapped.data.label,
            apiNode: draft && node.type === 'annotation' ? { ...node, config: draft.config } : node },
        }
      })
      const selected = graph.workflow.nodes?.find((node) => String(node.id) === state.selectedNodeId)
      const nodeDrafts = Object.fromEntries(Object.entries(state.nodeDrafts).filter(([id]) => graph.workflow.nodes?.some((node) => node.id === Number(id))))
      set({ workflow: graph.workflow, graphHash: graph.hash, rfNodes, nodeDrafts,
        rfEdges: (graph.workflow.edges ?? []).map((edge) => ({ ...apiEdgeToRFEdge(edge), selected: state.rfEdges.find((item) => item.id === String(edge.id))?.selected })),
        selectedNodeId: selected ? String(selected.id) : null, selectedApiNode: selected ?? null,
        selectedRegistryNode: selected ? registryLookup(selected.node_key) : undefined,
        validationIssues: [], validationFocus: null,
      })
    }

    return {
      ...initialState,
      loadWorkflow: async (id, lookup) => {
        const current = ++generation
        registryLookup = lookup
        set({ ...initialState, isLoading: true })
        try {
          const res = await sdk.graph.get(id)
          if (current === generation) renderGraph(res.data)
        } finally {
          if (current === generation) set({ isLoading: false })
        }
      },
      refreshGraph: async () => {
        const workflow = get().workflow
        if (!workflow || get().isEditing) return
        const res = await sdk.graph.get(workflow.id)
        if (get().workflow?.id !== workflow.id) return
        set({ failedEdit: null, editError: null, editConflict: false, undoStack: [], redoStack: [] })
        renderGraph(res.data)
      },
      updateWorkflowMeta: async (data) => {
        const workflow = get().workflow
        if (!workflow) return
        const res = await sdk.workflows.update(workflow.id, data)
        set({ workflow: { ...get().workflow!, ...res.data, nodes: get().workflow?.nodes, edges: get().workflow?.edges } })
      },
      submitEdit: async (request) => {
        const workflow = get().workflow
        if (!workflow) return
        if (get().isEditing) throw new Error('Wait for the current edit to finish.')
        const current = generation
        set({ isEditing: true, editError: null })
        try {
          const res = await sdk.graph.edit(workflow.id, request)
          if (generation !== current) return
          const receipt = res.data.edit
          if (!receipt) throw new Error('The graph API did not return an edit receipt.')
          const { undoStack, redoStack } = get()
          if (res.data.hash !== receipt.after_hash) {
            set({ undoStack: [], redoStack: [] })
          } else if (request.operation === 'undo') {
            const original = undoStack.at(-1)
            set({ undoStack: undoStack.slice(0, -1), redoStack: original ? [...redoStack, original] : redoStack })
          } else if (request.operation === 'redo') {
            const original = redoStack.at(-1)
            set({ undoStack: original ? [...undoStack, original] : undoStack, redoStack: redoStack.slice(0, -1) })
          } else if (receipt.before_hash !== receipt.after_hash) {
            set({ undoStack: [...undoStack, receipt], redoStack: [] })
          }
          if (request.operation === 'update_node') {
            const drafts = { ...get().nodeDrafts }
            delete drafts[Number(request.data.node_id)]
            set({ nodeDrafts: drafts })
          }
          set({ failedEdit: null, editConflict: false, editError: null })
          renderGraph(res.data)
          if (receipt.created_node_id && res.data.workflow.nodes?.some((node) => node.id === receipt.created_node_id)) get().selectNode(String(receipt.created_node_id))
          return receipt
        } catch (error) {
          if (generation === current) {
            const definitive = error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 408 && error.status !== 429
            set({ editError: apiErrorMessage(error, 'The edit could not be saved.'), editConflict: error instanceof ApiError && error.status === 409,
              failedEdit: definitive ? null : request })
            // Drag positions are a local preview until the atomic move succeeds.
            if (get().workflow && get().graphHash) renderGraph({ workflow: get().workflow!, hash: get().graphHash! })
          }
          throw error
        } finally {
          if (generation === current) set({ isEditing: false })
        }
      },
      edit: async (operation, data) => {
        const { graphHash, failedEdit, editConflict } = get()
        if (failedEdit || editConflict) throw new Error('Retry the pending edit or reload the saved draft before editing again.')
        if (!graphHash) return
        return get().submitEdit({ request_id: newEditId(), expected_hash: graphHash, operation, data })
      },
      retryEdit: async () => {
        const request = get().failedEdit
        if (request) await get().submitEdit(request)
      },
      undo: async () => {
        const edit = get().undoStack.at(-1)
        if (Object.keys(get().nodeDrafts).length) throw new Error('Save or discard step settings before undoing.')
        if (edit) await get().edit('undo', { edit_id: edit.id })
      },
      redo: async () => {
        const edit = get().redoStack.at(-1)
        if (Object.keys(get().nodeDrafts).length) throw new Error('Save or discard step settings before redoing.')
        if (edit) await get().edit('redo', { edit_id: edit.id })
      },
      addNode: async (nodeKey, position, registryNode, connection = {}) => {
        const config = Object.fromEntries(registryNode.config_schema.filter((field) => field.default !== undefined).map((field) => [field.key, field.default]))
        const receipt = await get().edit('add_node', { node_key: nodeKey, name: registryNode.name, config,
          position_x: Math.round(position.x), position_y: Math.round(position.y), ...connection })
        return receipt?.created_node_id ? String(receipt.created_node_id) : undefined
      },
      setNodeDraft: (nodeId, draft) => {
        set({ nodeDrafts: { ...get().nodeDrafts, [nodeId]: draft } })
        if (get().workflow && get().graphHash) renderGraph({ workflow: get().workflow!, hash: get().graphHash! })
      },
      discardNodeDraft: (nodeId) => {
        const drafts = { ...get().nodeDrafts }
        delete drafts[nodeId]
        set({ nodeDrafts: drafts })
        if (get().workflow && get().graphHash) renderGraph({ workflow: get().workflow!, hash: get().graphHash! })
      },
      saveNodeDraft: async (nodeId) => {
        const draft = get().nodeDrafts[nodeId]
        if (draft) await get().edit('update_node', { node_id: nodeId, ...draft })
      },
      remove: async (nodeIds, edgeIds) => {
        await get().edit('remove', { node_ids: nodeIds, edge_ids: edgeIds })
        const drafts = { ...get().nodeDrafts }
        for (const id of nodeIds) delete drafts[id]
        set({ nodeDrafts: drafts })
      },
      moveNodes: async (nodes) => {
        if (!nodes.length) return
        await get().edit('move_nodes', { positions: nodes.map((node) => ({ node_id: Number(node.id), position_x: Math.round(node.position.x), position_y: Math.round(node.position.y) })) })
      },
      autoLayout: async () => get().moveNodes(getAutoLayoutPositions(get().rfNodes, get().rfEdges)),
      addEdge: async (connection) => {
        if (!connection.source || !connection.target) return
        await get().edit('connect', { source_node_id: Number(connection.source), target_node_id: Number(connection.target), source_port: connection.sourceHandle || 'main', target_port: connection.targetHandle || 'main' })
      },
      onNodesChange: (changes) => set({ rfNodes: applyNodeChanges(changes, get().rfNodes) as Node<CustomNodeData>[] }),
      onEdgesChange: (changes) => set({ rfEdges: applyEdgeChanges(changes, get().rfEdges) }),
      pinNode: async (nodeId, data) => { await get().edit('pin', { node_id: nodeId, ...data }) },
      unpinNode: async (nodeId) => { await get().edit('unpin', { node_id: nodeId }) },
  selectNode: (nodeId) => {
    if (!nodeId) {
      set({ selectedNodeId: null, selectedApiNode: null, selectedRegistryNode: undefined })
      return
    }
    const node = get().rfNodes.find((n) => n.id === nodeId)
    if (node) {
      set({
        rfNodes: get().rfNodes.map((candidate) => ({ ...candidate, selected: candidate.id === nodeId })),
        selectedNodeId: nodeId,
        selectedApiNode: node.data.apiNode,
        selectedRegistryNode: node.data.registryNode,
      })
    }
  },

  setValidationErrors: (errors) => {
    const state = get()
    const issues = resolveWorkflowValidationIssues(
      errors,
      state.rfNodes.map((node) => ({ id: node.id, label: node.data.label })),
    )
    const nodeMessages = new Map<string, string[]>()
    const edgeMessages = new Map<string, string[]>()

    for (const issue of issues) {
      if (issue.nodeId) {
        nodeMessages.set(issue.nodeId, [...(nodeMessages.get(issue.nodeId) ?? []), issue.message])
      }
      if (issue.edgeId) {
        edgeMessages.set(issue.edgeId, [...(edgeMessages.get(issue.edgeId) ?? []), issue.message])
      }
    }

    set({
      validationIssues: issues,
      validationFocus: null,
      rfNodes: state.rfNodes.map((node) => {
        const messages = nodeMessages.get(node.id) ?? []
        return {
          ...node,
          data: {
            ...node.data,
            invalid: messages.length > 0,
            validationMessage: messages.length > 0 ? messages.join('\n') : null,
          },
        }
      }),
      rfEdges: state.rfEdges.map((edge) => {
        const messages = edgeMessages.get(edge.id) ?? []
        return {
          ...edge,
          data: {
            ...(edge.data ?? {}),
            invalid: messages.length > 0,
            validationMessage: messages.length > 0 ? messages.join('\n') : null,
          },
          animated: messages.length > 0,
          style: {
            ...(edge.style ?? {}),
            stroke: messages.length > 0 ? '#ef4444' : undefined,
          },
        }
      }),
    })
  },

  focusValidationIssue: (issue) => {
    const state = get()
    const selectedNode = issue.nodeId
      ? state.rfNodes.find((node) => node.id === issue.nodeId)
      : undefined

    set({
      rfNodes: state.rfNodes.map((node) => ({
        ...node,
        selected: issue.nodeId === node.id,
      })),
      rfEdges: state.rfEdges.map((edge) => ({
        ...edge,
        selected: issue.edgeId === edge.id,
      })),
      selectedNodeId: selectedNode?.id ?? null,
      selectedApiNode: selectedNode?.data.apiNode ?? null,
      selectedRegistryNode: selectedNode?.data.registryNode,
      validationFocus: {
        issue,
        token: (state.validationFocus?.token ?? 0) + 1,
      },
    })
  },

  clearValidationErrors: () => {
    const state = get()
    if (state.validationIssues.length === 0 && state.validationFocus === null) return

    set({
      validationIssues: [],
      validationFocus: null,
      rfNodes: state.rfNodes.map((node) => ({
        ...node,
        data: { ...node.data, invalid: false, validationMessage: null },
      })),
      rfEdges: state.rfEdges.map((edge) => ({
        ...edge,
        data: { ...(edge.data ?? {}), invalid: false, validationMessage: null },
        animated: false,
        style: { ...(edge.style ?? {}), stroke: undefined },
      })),
    })
  },

      reset: () => { generation++; set(initialState) },
    }
  })
}
