import { useState, useEffect, useCallback } from 'react'
import { X, Save, Play, Loader2, Pin, PinOff, Maximize2, Minimize2, ArrowRight, Database } from 'lucide-react'
import { useShallow } from 'zustand/react/shallow'
import { useRunStore, useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { useEditorSdk } from '../../sdk/EditorSdkContext'
import { apiErrorMessage } from '../../api/client'
import type { AvailableVariablesResponse, WorkflowRun } from '../../api/types'
import { DynamicForm } from './DynamicForm'
import { VariablePanel } from './VariablePanel'
import { DataPreview } from './DataPreview'
import { NodeRunStatusBadge } from '../shared/StatusBadge'
import { TestNodeInputModal } from '../execution/TestNodeInputModal'
import { MarkdownRenderer } from '../shared/MarkdownRenderer'

export type NodeWorkspaceTab = 'input' | 'config' | 'output' | 'docs'
interface NodeConfigPanelProps {
  expanded: boolean
  onToggleExpanded: () => void
  onTabChange?: (tab: NodeWorkspaceTab) => void
}

export function NodeConfigPanel({ expanded, onToggleExpanded, onTabChange }: NodeConfigPanelProps) {
  const sdk = useEditorSdk()
  const editor = useWorkflowEditorStore()
  const { workflow, selectedApiNode: node, selectedRegistryNode: capability, nodeDrafts, selectNode, setNodeDraft, discardNodeDraft, saveNodeDraft, pinNode, unpinNode } = editor
  const { nodeTestResults, testRun, testGraphHash, testError, isTestingNode, testNode, runs, fetchRuns } = useRunStore(useShallow((state) => ({
    nodeTestResults: state.nodeTestResults, testRun: state.testRun, testGraphHash: state.testGraphHash, testError: state.testError,
    isTestingNode: state.isTestingNode, testNode: state.testNode, runs: state.runs, fetchRuns: state.fetchRuns,
  })))
  const [tab, setTabState] = useState<NodeWorkspaceTab>('config')
  const setTab = useCallback((next: NodeWorkspaceTab) => { setTabState(next); onTabChange?.(next) }, [onTabChange])
  const [source, setSource] = useState(node?.pinned_data ? 'pinned' : 'test')
  const [historicalRun, setHistoricalRun] = useState<WorkflowRun | null>(null)
  const [isLoadingRun, setIsLoadingRun] = useState(false)
  const [selectedPort, setSelectedPort] = useState('main')
  const [error, setError] = useState<string | null>(null)
  const [showTestModal, setShowTestModal] = useState(false)
  const [variables, setVariables] = useState<AvailableVariablesResponse | null>(null)

  useEffect(() => {
    let active = true
    if (workflow && node) {
      void sdk.nodes.availableVariables(workflow.id, node.id).then((data) => { if (active) setVariables(data) }).catch(() => { if (active) setVariables(null) })
    }
    return () => { active = false }
  }, [sdk.nodes, workflow?.id, node?.id, editor.graphHash]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (workflow?.id) void fetchRuns(workflow.id).catch(() => {})
  }, [workflow?.id, fetchRuns])

  useEffect(() => {
    if (!source.startsWith('run:')) return
    let active = true
    void sdk.runs.show(Number(source.slice(4))).then((response) => { if (active) setHistoricalRun(response.data) })
      .catch((cause) => { if (active) setError(apiErrorMessage(cause, 'Could not load this run.')) })
      .finally(() => { if (active) setIsLoadingRun(false) })
    return () => { active = false }
  }, [sdk.runs, source])

  if (!node || !capability) return <p className="p-5 text-sm text-gray-500">Select a registered step to configure it.</p>
  const draft = nodeDrafts[node.id]
  const local = draft ?? { name: node.name ?? capability.name, config: node.config ?? {} }
  const blocked = editor.isEditing || !!editor.failedEdit || editor.editConflict
  const dirty = !!draft
  const annotation = node.type === 'annotation'
  const activeTest = testRun?.workflow_id === workflow?.id ? testRun : null
  const run = source === 'test' ? activeTest : source.startsWith('run:') && historicalRun?.id === Number(source.slice(4)) ? historicalRun : null
  const result = source === 'test' && activeTest ? nodeTestResults?.[node.id] : run?.node_runs?.find((entry) => entry.node_id === node.id)
  const stale = source === 'test' && !!activeTest && editor.graphHash !== testGraphHash
  const input = source === 'pinned' ? node.pinned_data?.input : result?.input
  const output = source === 'pinned' ? node.pinned_data?.output : result?.output
  const ports = Object.keys(output ?? {})
  const port = ports.includes(selectedPort) ? selectedPort : ports[0]
  const incoming = editor.rfEdges.filter((edge) => edge.target === String(node.id))
  const sourceLabel = source === 'pinned' ? `Pinned sample${node.pinned_data?.source_run_id ? ` · run #${node.pinned_data.source_run_id}` : ''}`
    : run ? `${source === 'test' ? 'Draft test' : 'Run'} #${run.id} · revision #${run.workflow_revision_id}` : 'Draft test'
  const feedback = error || testError
  const act = async (callback: () => Promise<void>) => {
    setError(null)
    try { await callback() } catch (cause) { setError(apiErrorMessage(cause, 'This change could not be saved.')) }
  }

  return <div className="flex h-full min-w-0 flex-col">
    <div className="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
      <div className="min-w-0 flex-1">
        <input aria-label="Step name" value={local.name} disabled={blocked} onChange={(event) => setNodeDraft(node.id, { ...local, name: event.target.value })}
          className="w-full border-none bg-transparent text-sm font-semibold text-gray-900 outline-none dark:text-gray-100" />
        <p className="mt-0.5 text-[11px] text-gray-400">{capability.name}{dirty && <span className="ml-2 text-amber-600 dark:text-amber-400">Unsaved settings</span>}</p>
      </div>
      {!annotation && <button type="button" aria-label={expanded ? 'Collapse step workspace' : 'Expand step workspace'} onClick={onToggleExpanded} title={expanded ? 'Return to canvas sidebar' : 'Inspect input, settings and output together'} className="hidden rounded-lg p-2 text-gray-500 hover:bg-gray-100 md:block dark:hover:bg-gray-700">{expanded ? <Minimize2 size={16} /> : <Maximize2 size={16} />}</button>}
      <button type="button" aria-label="Close step settings" onClick={() => selectNode(null)} className="rounded-lg p-2 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"><X size={16} /></button>
    </div>

    {!annotation && <>
      <div className={`flex border-b border-gray-200 dark:border-gray-700 ${expanded ? 'lg:hidden' : ''}`} role="tablist" aria-label="Step workspace">
        {(['input', 'config', 'output', ...(capability.documentation ? ['docs'] : [])] as NodeWorkspaceTab[]).map((item) => <button type="button" role="tab" aria-selected={tab === item} key={item} onClick={() => setTab(item)} className={`flex-1 border-b-2 px-2 py-2.5 text-xs ${tab === item ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400'}`}>{item === 'config' ? 'Settings' : item === 'input' ? 'Input' : item === 'output' ? 'Output' : 'Docs'}</button>)}
      </div>
      <div className="flex flex-wrap items-center gap-2 border-b border-gray-200 bg-gray-50/70 px-4 py-2 dark:border-gray-700 dark:bg-gray-900/40">
        <label className="text-[11px] text-gray-500" htmlFor="step-data-source">Data from</label>
        <select id="step-data-source" aria-label="Data source" value={source} onChange={(event) => { setSource(event.target.value); setHistoricalRun(null); setIsLoadingRun(event.target.value.startsWith('run:')); setError(null) }} className="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
          <option value="test">Latest draft test{activeTest ? ` #${activeTest.id}` : ''}</option>
          <option value="pinned">Pinned sample{node.pinned_data ? '' : ' (none)'}</option>
          {runs.filter((entry) => entry.workflow_id === workflow?.id).map((entry) => <option key={entry.id} value={`run:${entry.id}`}>Run #{entry.id} · {entry.status}</option>)}
        </select>
        {expanded && capability.documentation && <button type="button" onClick={() => setTab(tab === 'docs' ? 'config' : 'docs')} className="hidden px-2 text-xs text-blue-600 lg:block dark:text-blue-400">{tab === 'docs' ? 'Back to settings' : 'Step docs'}</button>}
      </div>
      {(stale || dirty) && <p className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">{dirty ? 'Save settings before testing. The data below belongs to the selected sample or run.' : 'The draft has changed since this test. Test again to see current results.'}</p>}
    </>}

    {feedback && <p role="alert" className="border-b border-red-200 bg-red-50 px-4 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">{feedback}</p>}
    <div className={`min-h-0 flex-1 ${expanded && !annotation ? 'lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(280px,1.2fr)_minmax(0,1fr)]' : ''}`}>
      {!annotation && <section aria-label="Step input" className={`h-full min-w-0 overflow-y-auto p-4 ${tab === 'input' ? '' : 'hidden'} ${expanded ? 'lg:block lg:border-r lg:border-gray-200 lg:dark:border-gray-700' : ''}`}>
        <h3 className="text-xs font-semibold text-gray-900 dark:text-gray-100">Input</h3>
        <p className="mb-4 mt-1 text-[11px] text-gray-400">{sourceLabel}</p>
        {isLoadingRun ? <Loader2 className="animate-spin text-gray-400" size={18} /> : input != null ? <DataPreview data={input} /> : <EmptyData message={source === 'pinned' ? 'No input is pinned for this step.' : run && !result ? 'This step has no recorded input in this run.' : 'Test this step to inspect its incoming items.'} />}
        {incoming.length > 0 && <div className="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700">
          <h4 className="mb-2 text-[11px] font-medium text-gray-500">Connected in this draft</h4>
          {incoming.map((edge) => <button type="button" key={edge.id} onClick={() => selectNode(edge.source)} className="mb-1 flex w-full items-center justify-between gap-2 rounded-lg bg-gray-50 p-2.5 text-left text-xs text-gray-600 hover:bg-gray-100 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-700"><span className="truncate">{editor.rfNodes.find((entry) => entry.id === edge.source)?.data.label ?? 'Previous step'}<span className="mt-1 block text-[10px] text-gray-400">{edge.sourceHandle} → {edge.targetHandle}</span></span><ArrowRight size={13} /></button>)}
        </div>}
      </section>}

      <section aria-label="Step configuration" className={`h-full min-w-0 overflow-y-auto p-4 ${annotation || tab === 'config' || tab === 'docs' ? '' : 'hidden'} ${expanded ? 'lg:block' : ''}`}>
        {tab === 'docs' && capability.documentation ? <MarkdownRenderer markdown={capability.documentation} /> : <div className="space-y-4">
          {expanded && <h3 className="text-xs font-semibold text-gray-900 dark:text-gray-100">Settings</h3>}
          {capability.description && <p className="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{capability.description}</p>}
          <fieldset disabled={blocked} className="min-w-0 space-y-4 disabled:opacity-60"><DynamicForm schema={capability.config_schema} values={local.config} onChange={(key, value) => setNodeDraft(node.id, { ...local, config: { ...local.config, [key]: value } })} variables={variables} workflowId={workflow?.id} /></fieldset>
          {variables && <div className="border-t border-gray-200 pt-3 dark:border-gray-700"><VariablePanel data={variables} onInsert={(expression) => { void navigator.clipboard.writeText(expression).catch(() => setError('Could not copy the expression.')) }} /></div>}
        </div>}
      </section>

      {!annotation && <section aria-label="Step output" className={`h-full min-w-0 overflow-y-auto p-4 ${tab === 'output' ? '' : 'hidden'} ${expanded ? 'lg:block lg:border-l lg:border-gray-200 lg:dark:border-gray-700' : ''}`}>
        <div className="flex items-center justify-between gap-2"><h3 className="text-xs font-semibold text-gray-900 dark:text-gray-100">Output</h3>
          {result?.status === 'completed' && result.output && <button type="button" disabled={blocked || dirty} onClick={() => void act(() => pinNode(node.id, { source: 'run', node_run_id: result.id }))} className="flex items-center gap-1 text-[11px] text-gray-500 disabled:opacity-40"><Pin size={12} /> Pin sample</button>}
        </div>
        <p className="mb-4 mt-1 text-[11px] text-gray-400">{sourceLabel}</p>
        {result && <div className="mb-3 flex items-center gap-2"><NodeRunStatusBadge status={result.status} />{result.duration_ms != null && <span className="text-[11px] text-gray-400">{result.duration_ms} ms</span>}</div>}
        {result?.error_message && <p className="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">{result.error_message}</p>}
        {isLoadingRun ? <Loader2 className="animate-spin text-gray-400" size={18} /> : output != null ? <>
          {ports.length > 0 && <div className="mb-3 flex flex-wrap gap-1" aria-label="Output ports">{ports.map((item) => <button type="button" key={item} aria-pressed={port === item} onClick={() => setSelectedPort(item)} className={`rounded-full px-2.5 py-1 text-[11px] ${port === item ? 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-900'}`}>{item}</button>)}</div>}
          <DataPreview data={port ? output[port] : output} />
        </> : <EmptyData message={source === 'pinned' ? 'No output is pinned for this step.' : run && !result ? 'This step has no recorded output in this run.' : 'Test the step to see its output here.'} />}
        {node.pinned_data && <button type="button" disabled={blocked} onClick={() => void act(() => unpinNode(node.id))} className="mt-4 flex items-center gap-1.5 text-xs text-orange-600 disabled:opacity-40 dark:text-orange-400"><PinOff size={12} /> Remove pinned sample</button>}
      </section>}
    </div>

    <div className="flex flex-wrap items-center gap-2 border-t border-gray-200 px-4 py-3 dark:border-gray-700">
      {dirty ? <><button type="button" disabled={blocked} onClick={() => void act(() => saveNodeDraft(node.id))} className="flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white disabled:opacity-50"><Save size={13} />{editor.isEditing ? 'Saving…' : 'Save settings'}</button><button type="button" disabled={blocked} onClick={() => discardNodeDraft(node.id)} className="px-2 text-xs text-gray-500 disabled:opacity-50">Discard</button></> : <span className="text-[11px] text-gray-400">Settings saved in draft</span>}
      {!annotation && <button type="button" disabled={blocked || isTestingNode || Object.keys(nodeDrafts).length > 0} onClick={() => setShowTestModal(true)} className="ml-auto flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-xs font-medium text-white disabled:opacity-50">{isTestingNode ? <Loader2 size={13} className="animate-spin" /> : <Play size={13} />}{isTestingNode ? 'Testing…' : 'Test step'}</button>}
    </div>
    {showTestModal && workflow && <TestNodeInputModal nodeName={local.name} isRunning={isTestingNode} onClose={() => setShowTestModal(false)} onRun={(payload) => {
      setSource('test'); setShowTestModal(false); setTab('output')
      void act(() => testNode(workflow.id, node.id, payload))
    }} />}
  </div>
}

function EmptyData({ message }: { message: string }) {
  return <div className="rounded-xl border border-dashed border-gray-200 px-4 py-8 text-center dark:border-gray-700"><Database size={22} className="mx-auto mb-3 text-gray-300 dark:text-gray-600" /><p className="text-xs leading-relaxed text-gray-400">{message}</p></div>
}
