import type { ConfigSchemaField } from '../../../api/types'
import { Info } from 'lucide-react'

interface Props {
  field: ConfigSchemaField
}

export function InfoField({ field }: Props) {
  return (
    <div className="flex gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-800 dark:bg-blue-900/30">
      <Info size={14} className="mt-0.5 shrink-0 text-blue-500 dark:text-blue-400" />
      <p className="text-xs leading-relaxed text-blue-700 dark:text-blue-300">
        {field.description ?? field.label}
      </p>
    </div>
  )
}
