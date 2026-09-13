import { apiErrorMessage } from '../api/client.ts'
import { createStore, type StoreApi } from 'zustand/vanilla'
import type { WorkflowNodeRun, WorkflowRun } from '../api/types'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'
import type { WorkflowEditorStore } from './useWorkflowEditorStore'

export interface RunStore {
  runs: WorkflowRun[]
  selectedRun: WorkflowRun | null
  isLoading: boolean
  isReplaying: boolean
  nodeTestResults: Record<number, WorkflowNodeRun> | null
  testRun: WorkflowRun | null
  testGraphHash: string | null
  testError: string | null
  isTestingNode: boolean
  lastTriggerPayload: Record<string, unknown>[] | null
  pendingTestNodeId: number | null
  fetchRuns: (workflowId: number) => Promise<void>
  fetchRunDetail: (runId: number) => Promise<void>
  cancelRun: (runId: number) => Promise<void>
  replayRun: (runId: number) => Promise<void>
  clearSelectedRun: () => void
  testNode: (workflowId: number, nodeId: number, payload?: Record<string, unknown>[]) => Promise<void>
  clearNodeTestResults: () => void
  requestNodeTest: (nodeId: number) => void
  clearPendingTest: () => void
}

export const createRunStore = (
  sdk: AitumalowEditorSdk,
  workflowEditorStore: StoreApi<WorkflowEditorStore>,
) => createStore<RunStore>((set, get) => ({
  runs: [],
  selectedRun: null,
  isLoading: false,
  isReplaying: false,
  nodeTestResults: null,
  testRun: null, testGraphHash: null, testError: null,
  isTestingNode: false,
  lastTriggerPayload: null,
  pendingTestNodeId: null,

  fetchRuns: async (workflowId) => {
    set({ isLoading: true })
    try {
      const res = await sdk.runs.list(workflowId)
      set({ runs: res.data })
    } finally {
      set({ isLoading: false })
    }
  },

  fetchRunDetail: async (runId) => {
    const res = await sdk.runs.show(runId)
    set({ selectedRun: res.data })
  },

  cancelRun: async (runId) => {
    await sdk.runs.cancel(runId)
    const res = await sdk.runs.show(runId)
    set({ selectedRun: res.data })
  },

  replayRun: async (runId) => {
    set({ isReplaying: true })
    try {
      const res = await sdk.runs.replay(runId)
      const newRun = res.data
      set({ selectedRun: newRun })
      const workflowId = newRun.workflow_id
      if (workflowId) {
        await get().fetchRuns(workflowId)
      }
    } finally {
      set({ isReplaying: false })
    }
  },

  clearSelectedRun: () => set({ selectedRun: null }),

  testNode: async (workflowId, nodeId, payload) => {
    const editor = workflowEditorStore.getState()
    if (get().isTestingNode) return
    if (editor.isEditing || editor.failedEdit || editor.editConflict || Object.keys(editor.nodeDrafts).length) {
      set({ testError: 'Save or discard step settings and resolve pending edits before testing.' })
      return
    }
    if (payload !== undefined) set({ lastTriggerPayload: payload })
    const hash = editor.graphHash
    set({ isTestingNode: true, testError: null, testGraphHash: hash, testRun: null, nodeTestResults: null })
    const showRun = (run: WorkflowRun) => {
      // A result map always belongs to this one run, including while it is running.
      set({ testRun: run, nodeTestResults: Object.fromEntries((run.node_runs ?? []).map((result) => [result.node_id, result])) })
    }
    try {
      const res = await sdk.workflows.testNode(workflowId, nodeId, payload ?? get().lastTriggerPayload ?? undefined, hash ?? undefined)
      let run = res.data
      if (workflowEditorStore.getState().workflow?.id !== workflowId) return
      showRun(run)
      for (let attempt = 0; attempt < 60 && ['pending', 'running'].includes(run.status); attempt++) {
        await new Promise((resolve) => setTimeout(resolve, 1000))
        if (workflowEditorStore.getState().workflow?.id !== workflowId) return
        run = (await sdk.runs.show(run.id)).data
        if (workflowEditorStore.getState().workflow?.id !== workflowId) return
        showRun(run)
      }
      if (['pending', 'running'].includes(run.status)) set({ testError: 'This run is still in progress. Follow it in Run history.' })
    } catch (error) {
      if (workflowEditorStore.getState().workflow?.id === workflowId) set({ testError: apiErrorMessage(error, 'The step could not be tested.') })
      throw error
    } finally {
      set({ isTestingNode: false })
    }
  },

  clearNodeTestResults: () => set({ nodeTestResults: null, lastTriggerPayload: null, testRun: null, testGraphHash: null, testError: null }),

  requestNodeTest: (nodeId: number) => {
    const { workflow, rfNodes } = workflowEditorStore.getState()
    if (!workflow) return
    const editor = workflowEditorStore.getState()
    if (editor.isEditing || editor.failedEdit || editor.editConflict || Object.keys(editor.nodeDrafts).length) {
      set({ testError: 'Save or discard step settings and resolve pending edits before testing.' })
      return
    }

    const { lastTriggerPayload, testNode } = get()

    // If we already have a trigger payload from a previous test, reuse it
    if (lastTriggerPayload) {
      void testNode(workflow.id, nodeId).catch(() => {})
      return
    }

    // Check if the trigger node has pinned input data
    const triggerNode = rfNodes.find((n) => (n.data as Record<string, unknown>).nodeType === 'trigger')
    const triggerPinned = (triggerNode?.data as Record<string, unknown> | undefined)?.apiNode as Record<string, unknown> | undefined
    const pinnedInput = (triggerPinned?.pinned_data as Record<string, unknown> | undefined)?.input as unknown[] | undefined
    if (pinnedInput?.length) {
      void testNode(workflow.id, nodeId, pinnedInput as Record<string, unknown>[]).catch(() => {})
      return
    }

    // No payload available — show the modal
    set({ pendingTestNodeId: nodeId })
  },

  clearPendingTest: () => set({ pendingTestNodeId: null }),
}))
