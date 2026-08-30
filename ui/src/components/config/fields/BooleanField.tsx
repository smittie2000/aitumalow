import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: boolean
  onChange: (value: boolean) => void
}

export function BooleanField({ field, value, onChange }: Props) {
  return (
    <label className="flex cursor-pointer items-center gap-2">
      <button
        type="button"
        role="switch"
        aria-checked={!!value}
        onClick={() => onChange(!value)}
        className={`relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors ${
          value ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'
        }`}
      >
        <span
          className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform ${
            value ? 'translate-x-4.5' : 'translate-x-0.5'
          }`}
        />
      </button>
      <span className="text-sm text-gray-700 dark:text-gray-300">{field.label}</span>
    </label>
  )
}
