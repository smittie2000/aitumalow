import { Plus, X } from 'lucide-react'
import type { ConfigSchemaField } from '../../../api/types'
import { DynamicForm } from '../DynamicForm'

interface Props {
  field: ConfigSchemaField
  value: Record<string, unknown>[] | null
  onChange: (value: Record<string, unknown>[]) => void
}

export function ArrayOfObjectsField({ field, value, onChange }: Props) {
  const items = value ?? []
  const subSchema = field.schema ?? []

  const addItem = () => {
    const empty: Record<string, unknown> = {}
    for (const f of subSchema) {
      empty[f.key] = f.type === 'boolean' ? false : ''
    }
    onChange([...items, empty])
  }

  const removeItem = (index: number) => {
    onChange(items.filter((_, i) => i !== index))
  }

  const updateItem = (index: number, key: string, val: unknown) => {
    onChange(
      items.map((item, i) => (i === index ? { ...item, [key]: val } : item)),
    )
  }

  return (
    <div className="space-y-3">
      {items.map((item, index) => (
        <div key={index} className="relative rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900">
          <button
            onClick={() => removeItem(index)}
            className="absolute right-2 top-2 rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500 dark:text-gray-500 dark:hover:bg-red-900/30 dark:hover:text-red-400"
          >
            <X size={12} />
          </button>
          <DynamicForm
            schema={subSchema}
            values={item}
            onChange={(key, val) => updateItem(index, key, val)}
          />
        </div>
      ))}
      <button
        onClick={addItem}
        className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700"
      >
        <Plus size={12} /> Add {field.label || 'Item'}
      </button>
    </div>
  )
}
