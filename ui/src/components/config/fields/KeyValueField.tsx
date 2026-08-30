import { Plus, X } from 'lucide-react'
import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: Record<string, string> | null
  onChange: (value: Record<string, string>) => void
}

export function KeyValueField({ value, onChange }: Props) {
  const pairs = Object.entries(value ?? {})

  const updateKey = (oldKey: string, newKey: string) => {
    const entries = Object.entries(value ?? {})
    const updated = Object.fromEntries(
      entries.map(([k, v]) => (k === oldKey ? [newKey, v] : [k, v])),
    )
    onChange(updated)
  }

  const updateValue = (key: string, newValue: string) => {
    onChange({ ...(value ?? {}), [key]: newValue })
  }

  const addPair = () => {
    const newKey = `key_${pairs.length}`
    onChange({ ...(value ?? {}), [newKey]: '' })
  }

  const removePair = (key: string) => {
    const copy = { ...(value ?? {}) }
    delete copy[key]
    onChange(copy)
  }

  return (
    <div className="space-y-2">
      {pairs.map(([k, v]) => (
        <div key={k} className="flex gap-2">
          <input
            type="text"
            value={k}
            onChange={(e) => updateKey(k, e.target.value)}
            className="w-1/3 rounded-md border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            placeholder="Key"
          />
          <input
            type="text"
            value={v}
            onChange={(e) => updateValue(k, e.target.value)}
            className="flex-1 rounded-md border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            placeholder="Value"
          />
          <button
            onClick={() => removePair(k)}
            className="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:text-gray-500 dark:hover:bg-red-900/30 dark:hover:text-red-400"
          >
            <X size={14} />
          </button>
        </div>
      ))}
      <button
        onClick={addPair}
        className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700"
      >
        <Plus size={12} /> Add Entry
      </button>
    </div>
  )
}
