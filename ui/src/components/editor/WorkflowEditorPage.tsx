import { useEffect, useCallback, useState, useRef } from 'react'
import {
  ArrowLeft,
  Play,
  Copy,
  ToggleLeft,
  ToggleRight,
  Clock,
  Layers,
  Sun,
  Moon,
  Menu,
  Tag,
  Folder,
  X,
  Check,
  Plus,
  Gauge,
} from 'lucide-react'
import { ReactFlowProvider } from '@xyflow/react'
import { useShallow } from 'zustand/react/shallow'

import {
  useRegistryStore,
  useRegistryStoreApi,
  useRunStore,
  useThemeStore,
  useWorkflowEditorStore,
  useWorkflowEditorStoreApi,
} from '../../stores/EditorRuntimeProvider'
import { useEditorSdk } from '../../sdk/EditorSdkContext'
import { apiErrorMessage } from '../../api/client'
import type { WorkflowTag, WorkflowFolder } from '../../api/types'
import { Canvas } from './Canvas'
import { ExportDropdown } from './ExportDropdown'
import { NodePalette } from '../palette/NodePalette'
import { NodeConfigPanel } from '../config/NodeConfigPanel'
import { RunHistoryPanel } from '../runs/RunHistoryPanel'
import { ExecuteModal } from '../execution/ExecuteModal'
import { TestNodeInputModal } from '../execution/TestNodeInputModal'
import { LoadingSpinner } from '../shared/LoadingSpinner'
import { ConfirmDialog } from '../shared/ConfirmDialog'
import { FolderTree } from '../folders/FolderTree'
import { folderPathLabel } from '../../lib/folders'

type SidebarTab = 'palette' | 'runs'

export interface WorkflowEditorPageProps {
  workflowId: number
  onExit?: () => void
  onOpenWorkflow?: (workflowId: number) => void
}

export function WorkflowEditorPage({ workflowId, onExit, onOpenWorkflow }: WorkflowEditorPageProps) {
  const sdk = useEditorSdk()
  const registryStore = useRegistryStoreApi()
  const workflowEditorStore = useWorkflowEditorStoreApi()
  const { workflow, isLoading, loadWorkflow, updateWorkflowMeta, reset, selectedNodeId } = useWorkflowEditorStore(useShallow((state) => ({
    workflow: state.workflow,
    isLoading: state.isLoading,
    loadWorkflow: state.loadWorkflow,
    updateWorkflowMeta: state.updateWorkflowMeta,
    reset: state.reset,
    selectedNodeId: state.selectedNodeId,
  })))
  const fetchRegistry = useRegistryStore((state) => state.fetchRegistry)
  const {
    fetchRuns,
    pendingTestNodeId,
    clearPendingTest,
    runTestNode,
    isTestingNode,
    lastTriggerPayload,
  } = useRunStore(useShallow((state) => ({
    fetchRuns: state.fetchRuns,
    pendingTestNodeId: state.pendingTestNodeId,
    clearPendingTest: state.clearPendingTest,
    runTestNode: state.testNode,
    isTestingNode: state.isTestingNode,
    lastTriggerPayload: state.lastTriggerPayload,
  })))
  const theme = useThemeStore((state) => state.theme)
  const toggleTheme = useThemeStore((state) => state.toggle)
  const [sidebarTab, setSidebarTab] = useState<SidebarTab>('palette')
  const [showExecute, setShowExecute] = useState(false)
  const [isDuplicating, setIsDuplicating] = useState(false)
  const [isTogglingActive, setIsTogglingActive] = useState(false)
  const [activationError, setActivationError] = useState<string | null>(null)
  const [showDuplicateConfirm, setShowDuplicateConfirm] = useState(false)
  const [mobileLeftOpen, setMobileLeftOpen] = useState(false)
  const [mobileRightOpen, setMobileRightOpen] = useState(false)
  const [configTab, setConfigTab] = useState<'config' | 'output' | 'docs'>('config')
  const [allTags, setAllTags] = useState<WorkflowTag[]>([])
  const [allFolders, setAllFolders] = useState<WorkflowFolder[]>([])
  const [showTagPicker, setShowTagPicker] = useState(false)
  const [showFolderPicker, setShowFolderPicker] = useState(false)
  const [showConcurrencyPicker, setShowConcurrencyPicker] = useState(false)
  const [newTagName, setNewTagName] = useState('')
  const tagPickerRef = useRef<HTMLDivElement>(null)
  const folderPickerRef = useRef<HTMLDivElement>(null)
  const concurrencyPickerRef = useRef<HTMLDivElement>(null)

  const loadTagsAndFolders = useCallback(async () => {
    const [tagsRes, foldersRes] = await Promise.all([sdk.tags.list(), sdk.folders.list()])
    setAllTags(tagsRes.data)
    setAllFolders(foldersRes.data)
  }, [sdk.folders, sdk.tags])

  useEffect(() => {
    const init = async () => {
      await fetchRegistry()
      await loadTagsAndFolders()
      loadWorkflow(workflowId, registryStore.getState().getByKey)
    }
    init()
    return () => {
      reset()
    }
  }, [workflowId]) // eslint-disable-line react-hooks/exhaustive-deps

  // Close pickers on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (tagPickerRef.current && !tagPickerRef.current.contains(e.target as HTMLElement)) setShowTagPicker(false)
      if (folderPickerRef.current && !folderPickerRef.current.contains(e.target as HTMLElement)) setShowFolderPicker(false)
      if (concurrencyPickerRef.current && !concurrencyPickerRef.current.contains(e.target as HTMLElement)) setShowConcurrencyPicker(false)
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  useEffect(() => {
    const frame = requestAnimationFrame(() => setMobileRightOpen(!!selectedNodeId))
    return () => cancelAnimationFrame(frame)
  }, [selectedNodeId])

  const handleToggleActive = async () => {
    if (!workflow || isTogglingActive) return

    setActivationError(null)
    setIsTogglingActive(true)
    try {
      if (workflow.is_active) {
        await sdk.workflows.deactivate(workflow.id)
      } else {
        await sdk.workflows.activate(workflow.id)
      }
      await loadWorkflow(workflow.id, registryStore.getState().getByKey)
    } catch (error) {
      setActivationError(apiErrorMessage(error, 'The workflow status could not be changed.'))
    } finally {
      setIsTogglingActive(false)
    }
  }

  const handleToggleTag = async (tagId: number) => {
    if (!workflow) return
    const currentTagIds = (workflow.tags ?? []).map((t) => t.id)
    const newTagIds = currentTagIds.includes(tagId)
      ? currentTagIds.filter((id) => id !== tagId)
      : [...currentTagIds, tagId]
    await updateWorkflowMeta({ tag_ids: newTagIds })
    loadWorkflow(workflow.id, registryStore.getState().getByKey)
  }

  const handleSetFolder = async (folderId: number | null) => {
    if (!workflow) return
    await updateWorkflowMeta({ folder_id: folderId })
    loadWorkflow(workflow.id, registryStore.getState().getByKey)
    setShowFolderPicker(false)
  }

  const handleCreateTag = async () => {
    if (!newTagName.trim()) return
    const res = await sdk.tags.create({ name: newTagName.trim() })
    setNewTagName('')
    setAllTags((prev) => [...prev, res.data])
    await handleToggleTag(res.data.id)
  }

  const handleSetConcurrency = async (value: number) => {
    if (!workflow) return
    const currentSettings = workflow.settings ?? {}
    await updateWorkflowMeta({
      settings: { ...currentSettings, max_concurrent_runs: value },
    })
    loadWorkflow(workflow.id, registryStore.getState().getByKey)
    setShowConcurrencyPicker(false)
  }

  const handleDuplicate = async () => {
    if (!workflow || isDuplicating) return
    setIsDuplicating(true)
    try {
      const res = await sdk.workflows.duplicate(workflow.id)
      onOpenWorkflow?.(res.data.id)
    } finally {
      setIsDuplicating(false)
    }
  }

  if (isLoading || !workflow) {
    return (
      <div className={`aitumalow-editor flex h-full min-h-80 items-center justify-center ${theme === 'dark' ? 'dark' : ''}`}>
        <LoadingSpinner />
      </div>
    )
  }

  return (
    <ReactFlowProvider>
    <div className={`aitumalow-editor flex h-full min-h-0 flex-col ${theme === 'dark' ? 'dark' : ''}`}>
      {/* Header */}
      <div className="flex h-12 shrink-0 items-center justify-between border-b border-gray-200 bg-white px-4 dark:border-gray-700 dark:bg-gray-800">
        <div className="flex items-center gap-2 md:gap-3 min-w-0">
          <button
            type="button"
            onClick={() => setMobileLeftOpen((v) => !v)}
            className="rounded p-1 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 md:hidden"
          >
            <Menu size={18} />
          </button>
          {onExit && (
            <button
              type="button"
              onClick={onExit}
              className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            >
              <ArrowLeft size={18} />
            </button>
          )}
          <h1 className="truncate max-w-35 md:max-w-none text-sm font-semibold text-gray-900 dark:text-gray-100">{workflow.name}</h1>
          <span
            className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium ${
              workflow.is_active ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
            }`}
          >
            {workflow.is_active ? 'Active' : 'Inactive'}
          </span>

          {/* Folder indicator */}
          <div className="relative" ref={folderPickerRef}>
            <button
              type="button"
              onClick={() => { setShowFolderPicker(!showFolderPicker); setShowTagPicker(false) }}
              className="flex items-center gap-1 rounded-md p-1 text-[10px] text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 sm:px-2"
              title="Set workflow folder"
              aria-label={'Workflow folder: ' + folderPathLabel(allFolders, workflow.folder_id)}
              aria-expanded={showFolderPicker}
            >
              <Folder size={12} />
              <span className="hidden max-w-28 truncate sm:inline">
                {folderPathLabel(allFolders, workflow.folder_id)}
              </span>
            </button>
            {showFolderPicker && (
              <div className="absolute left-0 top-full z-50 mt-1 w-64 rounded-lg border border-gray-200 bg-white p-1.5 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                <div className="px-2 py-1 text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                  Workflow location
                </div>
                <button
                  type="button"
                  onClick={() => handleSetFolder(null)}
                  className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-gray-700 ${
                    !workflow.folder_id ? 'font-medium text-blue-600' : 'text-gray-700 dark:text-gray-300'
                  }`}
                >
                  {!workflow.folder_id && <Check size={12} />}
                  <span className={!workflow.folder_id ? '' : 'ml-5'}>Unfiled</span>
                </button>
                <div className="mt-1 max-h-64 overflow-y-auto border-t border-gray-100 pt-1 dark:border-gray-700">
                  <FolderTree
                    folders={allFolders}
                    selectedFolderId={workflow.folder_id}
                    onSelect={handleSetFolder}
                  />
                </div>
              </div>
            )}
          </div>

          {/* Tag badges + picker */}
          <div className="relative hidden items-center gap-1 md:flex" ref={tagPickerRef}>
            {(workflow.tags ?? []).map((tag) => (
              <span
                key={tag.id}
                className="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[10px] font-medium"
                style={{
                  backgroundColor: (tag.color ?? '#6B7280') + '20',
                  color: tag.color ?? '#6B7280',
                }}
              >
                <span className="inline-block h-1.5 w-1.5 rounded-full" style={{ backgroundColor: tag.color ?? '#6B7280' }} />
                {tag.name}
                <button
                  type="button" onClick={() => handleToggleTag(tag.id)} className="ml-0.5 rounded-full p-0.5 hover:bg-black/10">
                  <X size={8} />
                </button>
              </span>
            ))}
            <button
              type="button"
              onClick={() => { setShowTagPicker(!showTagPicker); setShowFolderPicker(false) }}
              className="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
              title="Manage tags"
            >
              <Tag size={12} />
            </button>
            {showTagPicker && (
              <div className="absolute left-0 top-full z-50 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                {allTags.map((tag) => {
                  const isActive = (workflow.tags ?? []).some((t) => t.id === tag.id)
                  return (
                    <button
                      type="button"
                      key={tag.id}
                      onClick={() => handleToggleTag(tag.id)}
                      className="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                      <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded border border-gray-300 dark:border-gray-600"
                        style={isActive ? { backgroundColor: tag.color ?? '#3B82F6', borderColor: tag.color ?? '#3B82F6' } : {}}
                      >
                        {isActive && <Check size={10} className="text-white" />}
                      </span>
                      <span className="inline-block h-2 w-2 rounded-full" style={{ backgroundColor: tag.color ?? '#6B7280' }} />
                      {tag.name}
                    </button>
                  )
                })}
                <div className="border-t border-gray-100 px-2 py-1.5 dark:border-gray-700">
                  <div className="flex gap-1">
                    <input
                      type="text"
                      value={newTagName}
                      onChange={(e) => setNewTagName(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && handleCreateTag()}
                      placeholder="New tag..."
                      className="w-full rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                    />
                    <button
                      type="button" onClick={handleCreateTag} className="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">
                      <Plus size={12} />
                    </button>
                  </div>
                </div>
              </div>
            )}
          </div>

          {/* Concurrency limit picker */}
          <div className="relative hidden md:block" ref={concurrencyPickerRef}>
            <button
              type="button"
              onClick={() => { setShowConcurrencyPicker(!showConcurrencyPicker); setShowTagPicker(false); setShowFolderPicker(false) }}
              className={`flex items-center gap-1 rounded-md px-2 py-1 text-[10px] hover:bg-gray-100 dark:hover:bg-gray-700 ${
                (workflow.settings as Record<string, unknown> | null)?.max_concurrent_runs
                  ? 'text-amber-600 dark:text-amber-400'
                  : 'text-gray-500 dark:text-gray-400'
              }`}
              title="Concurrency limit"
            >
              <Gauge size={12} />
              <span>
                {(workflow.settings as Record<string, unknown> | null)?.max_concurrent_runs
                  ? `Max ${(workflow.settings as Record<string, unknown>).max_concurrent_runs}`
                  : 'No limit'}
              </span>
            </button>
            {showConcurrencyPicker && (
              <div className="absolute left-0 top-full z-50 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                <div className="px-3 py-1.5 text-[10px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                  Max Concurrent Runs
                </div>
                {[0, 1, 2, 3, 5, 10, 20].map((n) => {
                  const current = ((workflow.settings as Record<string, unknown> | null)?.max_concurrent_runs as number) ?? 0
                  return (
                    <button
                      type="button"
                      key={n}
                      onClick={() => handleSetConcurrency(n)}
                      className={`flex w-full items-center gap-2 px-3 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-gray-700 ${
                        current === n ? 'font-medium text-blue-600' : 'text-gray-700 dark:text-gray-300'
                      }`}
                    >
                      {current === n && <Check size={12} />}
                      <span className={current === n ? '' : 'ml-5'}>{n === 0 ? 'Unlimited' : String(n)}</span>
                    </button>
                  )
                })}
              </div>
            )}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={toggleTheme}
            className="rounded-md p-1.5 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
            title={theme === 'light' ? 'Dark mode' : 'Light mode'}
          >
            {theme === 'light' ? <Moon size={14} /> : <Sun size={14} />}
          </button>
          <div className="hidden md:flex items-center gap-2">
            <ExportDropdown workflow={workflow} />
            <button
              type="button"
              onClick={() => setShowDuplicateConfirm(true)}
              disabled={isDuplicating}
              className="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 disabled:opacity-50"
              title="Duplicate"
            >
              <Copy size={14} />
              {isDuplicating ? 'Duplicating...' : 'Duplicate'}
            </button>
            <button
              type="button"
              onClick={handleToggleActive}
              disabled={isTogglingActive}
              aria-pressed={workflow.is_active}
              className={`flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium ${
                workflow.is_active
                  ? 'text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/30'
                  : 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30'
              } disabled:cursor-wait disabled:opacity-50`}
              title={workflow.is_active ? 'Deactivate' : 'Activate'}
            >
              {workflow.is_active ? <ToggleRight size={14} /> : <ToggleLeft size={14} />}
              {isTogglingActive
                ? (workflow.is_active ? 'Deactivating...' : 'Activating...')
                : (workflow.is_active ? 'Active' : 'Inactive')}
            </button>
          </div>
          <button
            type="button"
            onClick={() => setShowExecute(true)}
            className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium ${
              workflow.is_active
                ? 'bg-green-600 text-white hover:bg-green-700'
                : 'bg-gray-300 text-gray-500 dark:bg-gray-600 dark:text-gray-400'
            }`}
          >
            <Play size={12} /> <span className="hidden md:inline">{workflow.is_active ? 'Run' : 'Run (Inactive)'}</span>
          </button>
        </div>
      </div>

      {activationError && (
        <div
          className="flex shrink-0 items-start justify-between gap-3 border-b border-red-200 bg-red-50 px-4 py-2 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-900/30 dark:text-red-300"
          role="alert"
        >
          <span>{activationError}</span>
          <button
            type="button"
            onClick={() => setActivationError(null)}
            className="shrink-0 rounded p-0.5 hover:bg-red-100 dark:hover:bg-red-900/50"
            aria-label="Dismiss workflow status error"
          >
            <X size={14} />
          </button>
        </div>
      )}

      {/* Body */}
      <div className="relative flex flex-1 overflow-hidden">
        {/* Mobile backdrop for left drawer */}
        {mobileLeftOpen && (
          <div className="fixed inset-0 z-30 bg-black/40 md:hidden" onClick={() => setMobileLeftOpen(false)} />
        )}

        {/* Left Sidebar — static on desktop, drawer on mobile */}
        <div className={`
          flex flex-col border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800
          fixed top-12 bottom-0 left-0 z-40 w-72 transition-transform duration-200
          ${mobileLeftOpen ? 'translate-x-0' : '-translate-x-full'}
          md:relative md:top-auto md:bottom-auto md:z-auto md:w-60 md:shrink-0 md:translate-x-0 md:transition-none
        `}>
          {/* Tabs */}
          <div className="flex border-b border-gray-200 dark:border-gray-700">
            <button
              type="button"
              onClick={() => setSidebarTab('palette')}
              className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-medium ${
                sidebarTab === 'palette'
                  ? 'border-b-2 border-blue-600 text-blue-600'
                  : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              <Layers size={12} /> Nodes
            </button>
            <button
              type="button"
              onClick={() => setSidebarTab('runs')}
              className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-medium ${
                sidebarTab === 'runs'
                  ? 'border-b-2 border-blue-600 text-blue-600'
                  : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
              }`}
            >
              <Clock size={12} /> Runs
            </button>
          </div>

          {/* Tab Content */}
          <div className="flex-1 overflow-y-auto p-2">
            {sidebarTab === 'palette' ? <NodePalette /> : <RunHistoryPanel />}
          </div>
        </div>

        {/* Canvas */}
        <div className="min-w-0 flex-1">
          <Canvas />
        </div>

        {/* Right Panel (Config) — static on desktop, drawer on mobile */}
        {selectedNodeId && (
          <>
            {mobileRightOpen && (
              <div className="fixed inset-0 z-30 bg-black/40 md:hidden" onClick={() => setMobileRightOpen(false)} />
            )}
            <div className={`
              border-l border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800
              fixed top-12 bottom-0 right-0 z-40 w-[85vw] max-w-sm transition-all duration-200
              ${mobileRightOpen ? 'translate-x-0' : 'translate-x-full'}
              md:relative md:top-auto md:bottom-auto md:z-auto md:max-w-none md:shrink-0 md:translate-x-0 md:transition-[width] md:duration-200
              ${configTab === 'docs' ? 'md:w-xl' : 'md:w-80'}
            `}>
              <NodeConfigPanel key={selectedNodeId} onTabChange={setConfigTab} />
            </div>
          </>
        )}

      </div>

      {/* Execute Modal */}
      {showExecute && (
        <ExecuteModal
          workflowId={workflow.id}
          onClose={() => setShowExecute(false)}
          onExecuted={() => {
            if (workflow?.id) fetchRuns(workflow.id)
            setSidebarTab('runs')
          }}
        />
      )}
      {/* Canvas-triggered Test Node Modal */}
      {pendingTestNodeId && (() => {
        const pendingNode = workflowEditorStore.getState().rfNodes.find(
          (n) => (n.data as Record<string, unknown>).apiNode && ((n.data as Record<string, unknown>).apiNode as Record<string, unknown>).id === pendingTestNodeId
        )
        const pendingNodeName = pendingNode
          ? (pendingNode.data as Record<string, unknown>).label as string
          : `Node #${pendingTestNodeId}`
        return (
          <TestNodeInputModal
            nodeName={pendingNodeName}
            onRun={(payload) => {
              runTestNode(workflow.id, pendingTestNodeId, payload as Record<string, unknown>)
              clearPendingTest()
            }}
            onClose={clearPendingTest}
            isRunning={isTestingNode}
            initialPayload={lastTriggerPayload ? JSON.stringify(lastTriggerPayload, null, 2) : undefined}
          />
        )
      })()}
      <ConfirmDialog
        open={showDuplicateConfirm}
        title="Duplicate Workflow"
        message={`Create a copy of "${workflow.name}"? The duplicate will be inactive by default.`}
        confirmLabel="Duplicate"
        variant="primary"
        onConfirm={async () => {
          setShowDuplicateConfirm(false)
          await handleDuplicate()
        }}
        onCancel={() => setShowDuplicateConfirm(false)}
      />
    </div>
    </ReactFlowProvider>
  )
}
