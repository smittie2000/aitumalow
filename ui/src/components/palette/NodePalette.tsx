import { ArrowRight, Loader2, Plus, Search } from 'lucide-react'
import { createElement, useRef, useState } from 'react'
import { useReactFlow, useStore } from '@xyflow/react'
import { useRegistryStore, useWorkflowEditorStore, useWorkflowEditorStoreApi } from '../../stores/EditorRuntimeProvider'
import { NODE_TYPE_COLORS } from '../../lib/constants'
import { capabilityIcon } from '../nodes/nodeStyles'
import { apiErrorMessage } from '../../api/client'
import type { CapabilityDefinition } from '../../api/types'
import type { ActionPickerRequest } from '../editor/ActionPickerContext'
import { getNewNodePosition, getNodeDimensions } from '../../lib/nodePlacement'

interface NodePaletteProps {
  request: ActionPickerRequest
  onAdded: () => void
}

export function NodePalette({ request, onAdded }: NodePaletteProps) {
  const { source, triggersOnly, edgeId } = request
  const nodes = useRegistryStore((state) => state.nodes)
  const store = useWorkflowEditorStoreApi()
  const { getViewport, setCenter } = useReactFlow()
  const width = useStore((state) => state.width)
  const height = useStore((state) => state.height)
  const resultsRef = useRef<HTMLDivElement>(null)
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('All')
  const [adding, setAdding] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const failedEdit = useWorkflowEditorStore((state) => state.failedEdit)
  const blocked = useWorkflowEditorStore((state) => state.isEditing || !!state.failedEdit || state.editConflict)
  const [chosen, setChosen] = useState<CapabilityDefinition | null>(null)
  const [inputPort, setInputPort] = useState('main')
  const [outputPort, setOutputPort] = useState('main')
  const available = nodes.filter((node) => triggersOnly
    ? node.type === 'trigger'
    : node.type !== 'trigger' && (!(source || edgeId) || node.input_ports.length > 0) && (!edgeId || node.output_ports.length > 0))
  const categories = ['All', ...new Set(available.map((node) => node.category || 'Other'))]
  const filtered = available.filter((node) =>
    (category === 'All' || (node.category || 'Other') === category)
    && `${node.name} ${node.description} ${node.category} ${node.key}`.toLowerCase().includes(search.trim().toLowerCase()),
  )
  const sourceNode = source ? store.getState().rfNodes.find((node) => node.id === source.nodeId) : undefined

  const add = async (capability: CapabilityDefinition, ports?: { input: string; output: string }) => {
    if (adding || blocked) return
    if (!ports && (((source || edgeId) && capability.input_ports.length > 1) || (edgeId && capability.output_ports.length > 1))) {
      setChosen(capability)
      setInputPort(capability.input_ports[0] ?? 'main')
      setOutputPort(capability.output_ports.includes('main') ? 'main' : capability.output_ports[0])
      return
    }
    setAdding(capability.key)
    setError(null)
    let created: string | undefined
    try {
      const state = store.getState()
      if (source && !state.rfNodes.some((node) => node.id === source.nodeId)) throw new Error('The previous step was removed. Close the picker and choose another step.')
      const viewport = getViewport()
      const position = getNewNodePosition(state.rfNodes, capability, request, {
        x: (width / 2 - viewport.x) / viewport.zoom,
        y: (height / 2 - viewport.y) / viewport.zoom,
      })
      created = await state.addNode(capability.key, position, capability, {
        ...(source ? { source: { node_id: Number(source.nodeId), port: source.port } } : {}),
        ...(edgeId ? { edge_id: Number(edgeId) } : {}),
        ...((source || edgeId) ? { input_port: ports?.input ?? capability.input_ports[0] } : {}),
        ...(edgeId ? { output_port: ports?.output ?? capability.output_ports[0] } : {}),
      })
      if (!created) return
      const createdNode = store.getState().rfNodes.find((node) => node.id === created)!
      const size = getNodeDimensions(createdNode)
      const zoom = Math.min(viewport.zoom, 1)
      // Keep the new step beside the settings panel on desktop.
      void setCenter(position.x + size.width / 2 + (width >= 768 ? 184 / zoom : 0), position.y + size.height / 2, { zoom, duration: 300 })
      onAdded()
    } catch (cause) {
      setError(apiErrorMessage(cause, 'The step could not be saved. Retry this edit or reload the draft.'))
    } finally {
      setAdding(null)
    }
  }

  return (
    <div className="flex h-full flex-col" onKeyDown={(event) => {
      const target = event.target as HTMLElement
      const isSearch = target.tagName === 'INPUT'
      const buttons = [...(resultsRef.current?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)') ?? [])]
      const index = buttons.indexOf(target as HTMLButtonElement)
      if ((isSearch || index >= 0) && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault()
        const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : buttons.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length
        buttons[next]?.focus()
      } else if (isSearch && event.key === 'Enter' && buttons.length) {
        event.preventDefault()
        buttons[0].click()
      }
    }}>
      <p className="mb-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
        {edgeId ? 'The new step will be connected between these two steps.' : sourceNode ? <>After <strong className="text-gray-700 dark:text-gray-200">{sourceNode.data.label}</strong> · {source?.port}</> : triggersOnly ? 'Choose the trigger that starts your workflow.' : 'Actions, branches, and notes available in your application.'}
      </p>
      <label className="relative block">
        <Search size={16} className="absolute left-3 top-3 text-gray-400" />
        <input autoFocus value={search} onChange={(event) => setSearch(event.target.value)} aria-label={triggersOnly ? 'Search triggers' : 'Search actions'} placeholder={triggersOnly ? 'Search triggers…' : 'Search actions…'} className="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
      </label>
      <div className="my-3 flex flex-wrap gap-1.5" aria-label="Action categories">
        {categories.map((item) => <button type="button" key={item} aria-pressed={category === item} onClick={() => setCategory(item)} className={`rounded-full px-2.5 py-1 text-[11px] font-medium ${category === item ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'}`}>{item}</button>)}
      </div>
      {error && <div role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
        {error}
        {failedEdit && <button type="button" disabled={!!adding} className="mt-2 block font-semibold underline disabled:opacity-50" onClick={async () => {
          setAdding('retry')
          try { await store.getState().retryEdit(); onAdded() } catch (cause) { setError(apiErrorMessage(cause, 'The edit still could not be saved.')) } finally { setAdding(null) }
        }}>Retry edit</button>}
      </div>}
      {chosen ? <div className="space-y-4 rounded-xl border border-blue-200 bg-blue-50/40 p-4 dark:border-blue-800 dark:bg-blue-950/20">
        <button type="button" onClick={() => setChosen(null)} className="text-xs text-blue-600 dark:text-blue-400">← Choose another step</button>
        <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{chosen.name}</h3>
        <label className="block text-xs text-gray-600 dark:text-gray-300">Connect into
          <select aria-label="New step input" value={inputPort} onChange={(event) => setInputPort(event.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900">{chosen.input_ports.map((port) => <option key={port}>{port}</option>)}</select>
        </label>
        {edgeId && <label className="block text-xs text-gray-600 dark:text-gray-300">Continue from
          <select aria-label="New step output" value={outputPort} onChange={(event) => setOutputPort(event.target.value)} className="mt-1 block w-full rounded-lg border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-900">{chosen.output_ports.map((port) => <option key={port}>{port}</option>)}</select>
        </label>}
        <button type="button" disabled={!!adding || blocked} onClick={() => void add(chosen, { input: inputPort, output: outputPort })} className="w-full rounded-lg bg-blue-600 p-2 text-sm text-white disabled:opacity-50">{adding ? 'Adding…' : edgeId ? 'Insert step' : 'Add connected step'}</button>
      </div> : <div ref={resultsRef} className="min-h-0 flex-1 space-y-1 overflow-y-auto">
        {filtered.map((capability) => <button
          type="button" key={capability.key} disabled={!!adding || blocked}
          draggable={!source && !edgeId && !adding && !blocked}
          onDragStart={(event) => { event.dataTransfer.setData('application/workflow-node-key', capability.key); event.dataTransfer.effectAllowed = 'move' }}
          onClick={() => void add(capability)}
          className="group flex w-full items-center gap-3 rounded-xl p-3 text-left transition hover:bg-gray-100 focus-visible:outline-blue-500 disabled:opacity-50 dark:hover:bg-gray-700"
        >
          <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 ${NODE_TYPE_COLORS[capability.type].text}`}>
            {createElement(capabilityIcon(capability.icon, capability.type), { size: 18 })}
          </span>
          <span className="min-w-0 flex-1"><span className="block text-sm font-medium text-gray-800 dark:text-gray-100">{capability.name}</span><span className="mt-0.5 line-clamp-2 block text-[11px] leading-relaxed text-gray-500 dark:text-gray-400">{capability.description || capability.category}</span></span>
          {adding === capability.key ? <Loader2 size={15} className="animate-spin text-blue-500" /> : source ? <ArrowRight size={15} className="text-gray-400" /> : <Plus size={15} className="text-gray-400" />}
        </button>)}
        {filtered.length === 0 && <p className="px-3 py-8 text-center text-sm text-gray-500">{available.length ? 'No matching actions. Try another search or category.' : 'No compatible actions are available in this application.'}</p>}
      </div>}
      <p className="border-t border-gray-100 pt-3 text-[11px] text-gray-400 dark:border-gray-700">{edgeId ? 'Inserts into this connection. ' : source ? 'Connects to this output. ' : 'Click to add, or drag onto the canvas. '}<span className="block mt-1">↑ ↓ to choose · Enter to add · Esc to close</span></p>
    </div>
  )
}
