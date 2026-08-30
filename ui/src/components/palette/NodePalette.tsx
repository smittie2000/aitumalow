import { ChevronDown, ChevronRight, GripVertical, Plus } from 'lucide-react'
import { createElement, useState } from 'react'
import { useRegistryStore, useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'
import { NODE_TYPE_COLORS } from '../../lib/constants'
import { capabilityIcon } from '../nodes/nodeStyles'
import type { CapabilityDefinition } from '../../api/types'

export function NodePalette() {
  const nodes = useRegistryStore((state) => state.nodes)
  const grouped = new Map<string, CapabilityDefinition[]>()

  for (const node of nodes) {
    const category = node.category || 'Other'
    grouped.set(category, [...(grouped.get(category) ?? []), node])
  }

  return (
    <div className="space-y-1">
      {[...grouped.entries()].map(([category, capabilities]) => (
        <PaletteCategory key={category} category={category} capabilities={capabilities} />
      ))}
    </div>
  )
}

function PaletteCategory({ category, capabilities }: { category: string; capabilities: CapabilityDefinition[] }) {
  const [open, setOpen] = useState(true)

  return (
    <div>
      <button
        onClick={() => setOpen(!open)}
        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
      >
        {open ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        {category}
        <span className="ml-auto text-[10px] text-gray-400 dark:text-gray-500">{capabilities.length}</span>
      </button>
      {open && (
        <div className="ml-2 space-y-0.5">
          {capabilities.map((capability) => (
            <PaletteItem key={capability.key} capability={capability} />
          ))}
        </div>
      )}
    </div>
  )
}

function PaletteItem({ capability }: { capability: CapabilityDefinition }) {
  const colors = NODE_TYPE_COLORS[capability.type]
  const icon = createElement(capabilityIcon(capability.icon, capability.type), { size: 12 })
  const addNode = useWorkflowEditorStore((state) => state.addNode)
  const rfNodes = useWorkflowEditorStore((state) => state.rfNodes)

  const onDragStart = (event: React.DragEvent) => {
    event.dataTransfer.setData('application/workflow-node-key', capability.key)
    event.dataTransfer.effectAllowed = 'move'
  }

  const handleClick = () => {
    const lastNode = rfNodes[rfNodes.length - 1]
    const position = lastNode
      ? { x: lastNode.position.x + 50, y: lastNode.position.y + 100 }
      : { x: 250, y: 150 }
    addNode(capability.key, position, capability)
  }

  return (
    <div
      draggable
      onDragStart={onDragStart}
      title={capability.description || capability.key}
      className={`flex cursor-grab items-center gap-2 rounded-md border border-transparent px-2 py-1.5 text-xs transition hover:border-gray-200 hover:bg-gray-50 dark:hover:border-gray-700 dark:hover:bg-gray-700 active:cursor-grabbing ${colors.text}`}
    >
      <GripVertical size={10} className="text-gray-300 dark:text-gray-600" />
      {icon}
      <span className="flex-1 text-gray-700 dark:text-gray-300">{capability.name}</span>
      <button
        onClick={handleClick}
        className="rounded p-0.5 text-gray-300 hover:bg-gray-200 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
        title="Add to canvas"
      >
        <Plus size={12} />
      </button>
    </div>
  )
}
