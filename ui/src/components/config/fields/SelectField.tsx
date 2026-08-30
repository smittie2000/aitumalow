import { useId } from 'react'
import type { ConfigSchemaField } from '../../../api/types'

interface SelectOption {
  value: string
  label: string
}

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
}

function normalizeOptions(options: unknown[]): SelectOption[] {
  return options.map((opt) => {
    if (typeof opt === 'string') {
      return { value: opt, label: opt }
    }
    if (typeof opt === 'object' && opt !== null && 'value' in opt) {
      const o = opt as { value: string; label?: string }
      return { value: o.value, label: o.label ?? o.value }
    }
    return { value: String(opt), label: String(opt) }
  })
}

export function SelectField({ field, value, onChange }: Props) {
  const listId = useId()
  const options = normalizeOptions((field.options ?? []) as unknown[])

  return (
    <div className="relative">
      <input
        list={listId}
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        placeholder={field.placeholder ?? `Select ${field.label}`}
        className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
      />
      <datalist id={listId}>
        {options.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </datalist>
    </div>
  )
}
