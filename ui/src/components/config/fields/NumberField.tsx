import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: number | string
  onChange: (value: number) => void
}

export function NumberField({ field, value, onChange }: Props) {
  return (
    <input
      type="number"
      value={value ?? ''}
      onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
      step={field.step ?? 'any'}
      min={field.min}
      max={field.max}
      className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
      placeholder={field.placeholder ?? field.label}
    />
  )
}
