import { useState } from 'react'
import { ChevronDown, ChevronRight } from 'lucide-react'
import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  children: React.ReactNode
}

export function SectionField({ field, children }: Props) {
  const [collapsed, setCollapsed] = useState(field.collapsed ?? false)
  const isCollapsible = field.collapsible ?? false

  return (
    <div className="mt-1">
      <button
        type="button"
        onClick={() => isCollapsible && setCollapsed(!collapsed)}
        className={`flex w-full items-center gap-1.5 border-b border-gray-200 pb-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400 ${isCollapsible ? 'cursor-pointer hover:text-gray-700 dark:hover:text-gray-300' : 'cursor-default'}`}
      >
        {isCollapsible && (
          collapsed ? <ChevronRight size={12} /> : <ChevronDown size={12} />
        )}
        {field.label}
      </button>
      {!collapsed && <div className="mt-2 space-y-3">{children}</div>}
    </div>
  )
}
