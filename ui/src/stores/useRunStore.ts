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
  isTestingNode: boolean
  lastTriggerPayload: Record<string, unknown> | null
  pendingTestNodeId: number | null
  fetchRuns: (workflowId: number) => Promise<void>
  fetchRunDetail: (runId: number) => Promise<void>
  cancelRun: (runId: number) => Promise<void>
  replayRun: (runId: number) => Promise<void>
  clearSelectedRun: () => void
  testNode: (workflowId: number, nodeId: number, payload?: Record<string, unknown>) => Promise<void>
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
    if (payload !== undefined) {
      set({ lastTriggerPayload: payload })
    }
    set({ isTestingNode: true })
    try {
      const res = await sdk.workflows.testNode(workflowId, nodeId, payload ?? get().lastTriggerPayload ?? undefined)
      const run = res.data
      const map: Record<number, WorkflowNodeRun> = {}
      for (const nr of run.node_runs ?? []) {
        map[nr.node_id] = nr
      }
      // Merge with existing results so step-by-step data is preserved
      set({ nodeTestResults: { ...get().nodeTestResults, ...map } })
    } finally {
      set({ isTestingNode: false })
    }
  },

  clearNodeTestResults: () => set({ nodeTestResults: null, lastTriggerPayload: null }),

  requestNodeTest: (nodeId: number) => {
    const { workflow, rfNodes } = workflowEditorStore.getState()
    if (!workflow) return

    const { lastTriggerPayload, testNode } = get()

    // If we already have a trigger payload from a previous test, reuse it
    if (lastTriggerPayload) {
      testNode(workflow.id, nodeId)
      return
    }

    // Check if the trigger node has pinned input data
    const triggerNode = rfNodes.find((n) => (n.data as Record<string, unknown>).nodeType === 'trigger')
    const triggerPinned = (triggerNode?.data as Record<string, unknown> | undefined)?.apiNode as Record<string, unknown> | undefined
    const pinnedInput = (triggerPinned?.pinned_data as Record<string, unknown> | undefined)?.input as unknown[] | undefined
    if (pinnedInput?.length) {
      testNode(workflow.id, nodeId, pinnedInput as unknown as Record<string, unknown>)
      return
    }

    // No payload available — show the modal
    set({ pendingTestNodeId: nodeId })
  },

  clearPendingTest: () => set({ pendingTestNodeId: null }),
}))
