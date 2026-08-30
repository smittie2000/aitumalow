import { memo } from 'react'
import { type NodeProps } from '@xyflow/react'
import type { CustomNodeData } from '../../lib/mappers'
import { StickyNote } from 'lucide-react'

const COLOR_CLASSES: Record<string, { bg: string; border: string; divider: string; darkBg: string }> = {
  yellow: { bg: 'bg-yellow-100', border: 'border-yellow-300', divider: 'border-yellow-200 dark:border-yellow-800/50', darkBg: 'dark:bg-yellow-900/40' },
  blue:   { bg: 'bg-blue-100',   border: 'border-blue-300',   divider: 'border-blue-200 dark:border-blue-800/50',     darkBg: 'dark:bg-blue-900/40' },
  green:  { bg: 'bg-green-100',  border: 'border-green-300',  divider: 'border-green-200 dark:border-green-800/50',   darkBg: 'dark:bg-green-900/40' },
  pink:   { bg: 'bg-pink-100',   border: 'border-pink-300',   divider: 'border-pink-200 dark:border-pink-800/50',     darkBg: 'dark:bg-pink-900/40' },
  purple: { bg: 'bg-purple-100', border: 'border-purple-300', divider: 'border-purple-200 dark:border-purple-800/50', darkBg: 'dark:bg-purple-900/40' },
}

function StickyNoteNodeComponent({ data, selected }: NodeProps) {
  const nodeData = data as unknown as CustomNodeData
  const config = nodeData.apiNode?.config ?? {}
  const color = (config.color as string) || 'yellow'
  const content = (config.content as string) || ''
  const colors = COLOR_CLASSES[color] ?? COLOR_CLASSES.yellow

  return (
    <div
      className={`min-w-[180px] max-w-[280px] rounded-md border shadow-md ${colors.bg} ${colors.darkBg} ${colors.border} ${
        selected ? 'ring-2 ring-blue-400' : ''
      }`}
    >
      <div className={`flex items-center gap-1.5 border-b px-3 py-1.5 ${colors.divider}`}>
        <StickyNote size={12} className="text-gray-500 dark:text-gray-400" />
        <span className="truncate text-xs font-medium text-gray-700 dark:text-gray-300">
          {nodeData.label}
        </span>
      </div>
      <div className="px-3 py-2">
        <p className="whitespace-pre-wrap text-xs text-gray-600 dark:text-gray-400">
          {content || 'Double-click to edit...'}
        </p>
      </div>
    </div>
  )
}

export const StickyNoteNode = memo(StickyNoteNodeComponent)
