import { createElement, useRef, useState } from 'react'
import { Search, X } from 'lucide-react'
import { useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { capabilityIcon } from '../nodes/nodeStyles'
import type { NodeType } from '../../api/types'

export function NodeNavigator({ onLocate, onClose }: {
  onLocate: (nodeId: string) => void
  onClose: () => void
}) {
  const nodes = useWorkflowEditorStore((state) => state.rfNodes)
  const edges = useWorkflowEditorStore((state) => state.rfEdges)
  const [search, setSearch] = useState('')
  const resultsRef = useRef<HTMLDivElement>(null)
  const filtered = nodes.filter((node) => `${node.data.label} ${node.data.nodeKey} ${node.data.nodeType}`.toLowerCase().includes(search.trim().toLowerCase()))

  return (
    <section aria-label="Find a step" className="editor-floating-panel absolute bottom-16 left-4 top-16 z-30 flex w-80 max-w-[calc(100%-2rem)] flex-col rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800" onKeyDown={(event) => {
      event.stopPropagation()
      if (event.key === 'Escape') onClose()
      const target = event.target as HTMLElement
      const buttons = [...(resultsRef.current?.querySelectorAll<HTMLButtonElement>('button') ?? [])]
      const index = buttons.indexOf(target as HTMLButtonElement)
      if ((target.tagName === 'INPUT' || index >= 0) && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault()
        const next = index < 0 ? (event.key === 'ArrowDown' ? 0 : buttons.length - 1) : (index + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length
        buttons[next]?.focus()
      } else if (target.tagName === 'INPUT' && event.key === 'Enter' && filtered[0]) {
        event.preventDefault()
        onLocate(filtered[0].id)
      }
    }}>
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Find a step <span className="ml-1 font-normal text-gray-400">{nodes.length}</span></h2>
        <button type="button" aria-label="Close step search" onClick={onClose} className="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"><X size={16} /></button>
      </div>
      <label className="relative mb-3 block">
        <Search size={16} className="absolute left-3 top-3 text-gray-400" />
        <input autoFocus aria-label="Search workflow steps" placeholder="Find a step in this workflow…" value={search} onChange={(event) => setSearch(event.target.value)} className="w-full rounded-lg border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm outline-none focus:border-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" />
      </label>
      <div ref={resultsRef} className="min-h-0 flex-1 space-y-1 overflow-y-auto">
        {filtered.map((node) => {
          const upstream = edges.filter((edge) => edge.target === node.id).map((edge) => {
            const label = nodes.find((candidate) => candidate.id === edge.source)?.data.label ?? 'Unknown step'
            return edge.sourceHandle && edge.sourceHandle !== 'main' ? `${label} · ${edge.sourceHandle}` : label
          })
          const context = upstream.length ? `After ${upstream.join(', ')}` : node.data.nodeType === 'trigger' ? 'Workflow trigger' : node.type === 'sticky_note' ? 'Canvas note' : 'Unconnected step'
          return <button type="button" key={node.id} onClick={() => onLocate(node.id)} className="flex w-full items-center gap-3 rounded-lg p-3 text-left hover:bg-gray-100 focus-visible:outline-blue-500 dark:hover:bg-gray-700">
          <span className="text-gray-500 dark:text-gray-400">{createElement(capabilityIcon(node.data.registryNode?.icon ?? '', node.data.nodeType as NodeType), { size: 18 })}</span>
          <span className="min-w-0 flex-1"><span className="block truncate text-sm font-medium text-gray-800 dark:text-gray-100">{node.data.label}</span><span title={context} className="mt-0.5 block truncate text-[11px] text-gray-400">{context}</span></span>
        </button>})}
        {filtered.length === 0 && <p className="py-8 text-center text-sm text-gray-500">No matching steps in this workflow.</p>}
      </div>
      <p className="border-t border-gray-100 pt-3 text-[11px] text-gray-400 dark:border-gray-700">↑ ↓ to choose · Enter to jump · Esc to close</p>
    </section>
  )
}
