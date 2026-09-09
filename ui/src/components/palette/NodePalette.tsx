import { ArrowRight, Loader2, Plus, Search } from 'lucide-react'
import { createElement, useState } from 'react'
import { useReactFlow } from '@xyflow/react'
import { useRegistryStore, useWorkflowEditorStoreApi } from '../../stores/EditorRuntimeProvider'
import { NODE_TYPE_COLORS } from '../../lib/constants'
import { capabilityIcon } from '../nodes/nodeStyles'
import { apiErrorMessage } from '../../api/client'
import type { CapabilityDefinition } from '../../api/types'
import type { ActionSource } from '../editor/ActionPickerContext'

interface NodePaletteProps {
  source?: ActionSource
  onAdded: () => void
}

export function NodePalette({ source, onAdded }: NodePaletteProps) {
  const nodes = useRegistryStore((state) => state.nodes)
  const store = useWorkflowEditorStoreApi()
  const { getViewport, setCenter } = useReactFlow()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('All')
  const [adding, setAdding] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [unconnectedNode, setUnconnectedNode] = useState<{ id: string; port: string } | null>(null)
  const available = nodes.filter((node) => !source || node.input_ports.length > 0)
  const categories = ['All', ...new Set(available.map((node) => node.category || 'Other'))]
  const filtered = available.filter((node) =>
    (category === 'All' || (node.category || 'Other') === category)
    && `${node.name} ${node.description} ${node.category}`.toLowerCase().includes(search.toLowerCase()),
  )
  const sourceNode = source ? store.getState().rfNodes.find((node) => node.id === source.nodeId) : undefined

  const connect = async (id: string, port: string) => {
    if (source) {
      await store.getState().addEdge({ source: source.nodeId, sourceHandle: source.port, target: id, targetHandle: port })
    }
    store.getState().selectNode(id)
    onAdded()
  }

  const add = async (capability: CapabilityDefinition) => {
    if (adding || unconnectedNode) return
    setAdding(capability.key)
    setError(null)
    let created: string | undefined
    try {
      const state = store.getState()
      if (source && !state.rfNodes.some((node) => node.id === source.nodeId)) throw new Error('The previous step was removed. Close the picker and choose another step.')
      const currentSource = source ? state.rfNodes.find((node) => node.id === source.nodeId) : undefined
      const lastNode = currentSource ?? state.rfNodes.filter((node) => node.type !== 'sticky_note').at(-1)
      const portIndex = currentSource?.data.outputPorts.indexOf(source!.port) ?? 0
      const branchOffset = currentSource
        ? (Math.max(0, portIndex) - (currentSource.data.outputPorts.length - 1) / 2) * 320
        : 0
      const position = lastNode
        ? { x: lastNode.position.x + branchOffset, y: lastNode.position.y + (lastNode.measured?.height ?? 100) + 150 }
        : { x: 100, y: 150 }
      created = await state.addNode(capability.key, position, capability)
      if (!created) return
      void setCenter(position.x + 120, position.y + 50, { zoom: Math.min(getViewport().zoom, 1), duration: 300 })
      await connect(created, capability.input_ports[0] ?? 'main')
    } catch (cause) {
      if (created) {
        setUnconnectedNode({ id: created, port: capability.input_ports[0] ?? 'main' })
        setError('The action was added, but its connection could not be saved. Retry the connection or close this panel to connect it manually.')
      } else {
        setError(apiErrorMessage(cause, 'The action could not be added. Please try again.'))
      }
    } finally {
      setAdding(null)
    }
  }

  return (
    <div className="flex h-full flex-col">
      <p className="mb-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
        {sourceNode ? <>After <strong className="text-gray-700 dark:text-gray-200">{sourceNode.data.label}</strong> · {source?.port}</> : 'Build with the actions available in your application.'}
      </p>
      <label className="relative block">
        <Search size={16} className="absolute left-3 top-3 text-gray-400" />
        <input autoFocus value={search} onChange={(event) => setSearch(event.target.value)} aria-label="Search actions" placeholder="Search actions…" className="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/15 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
      </label>
      <div className="my-3 flex flex-wrap gap-1.5" aria-label="Action categories">
        {categories.map((item) => <button type="button" key={item} aria-pressed={category === item} onClick={() => setCategory(item)} className={`rounded-full px-2.5 py-1 text-[11px] font-medium ${category === item ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'}`}>{item}</button>)}
      </div>
      {error && <div role="alert" className="mb-3 rounded-lg bg-red-50 p-3 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
        {error}
        {unconnectedNode && <button type="button" disabled={!!adding} className="mt-2 block font-semibold underline disabled:opacity-50" onClick={async () => {
          setAdding('retry')
          try { await connect(unconnectedNode.id, unconnectedNode.port) } catch { setError('The connection still could not be saved. You can retry or connect the added action manually.') } finally { setAdding(null) }
        }}>Retry connection</button>}
      </div>}
      <div className="min-h-0 flex-1 space-y-1 overflow-y-auto">
        {filtered.map((capability) => <button
          type="button" key={capability.key} disabled={!!adding || !!unconnectedNode}
          draggable={!source && !adding && !unconnectedNode}
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
      </div>
      <p className="border-t border-gray-100 pt-3 text-[11px] text-gray-400 dark:border-gray-700">{source ? 'Choose an action to connect it to this output.' : 'Click to add, or drag an action onto the canvas.'}</p>
    </div>
  )
}
