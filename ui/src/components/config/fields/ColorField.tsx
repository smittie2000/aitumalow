import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
}

export function ColorField({ field, value, onChange }: Props) {
  return (
    <div className="flex items-center gap-2">
      <input
        type="color"
        value={value || '#000000'}
        onChange={(e) => onChange(e.target.value)}
        className="h-8 w-8 shrink-0 cursor-pointer rounded border border-gray-300 dark:border-gray-600"
      />
      <input
        type="text"
        value={value ?? ''}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
        placeholder={field.placeholder ?? '#000000'}
      />
    </div>
  )
}
