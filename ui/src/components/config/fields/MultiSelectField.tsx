import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: string[] | null
  onChange: (value: string[]) => void
}

export function MultiSelectField({ field, value, onChange }: Props) {
  const selected = value ?? []
  const options = field.options ?? []

  const toggle = (opt: string) => {
    if (selected.includes(opt)) {
      onChange(selected.filter((v) => v !== opt))
    } else {
      onChange([...selected, opt])
    }
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {options.map((opt) => {
        const isActive = selected.includes(opt)
        return (
          <button
            key={opt}
            type="button"
            onClick={() => toggle(opt)}
            className={`rounded-md border px-2.5 py-1 text-xs transition-colors ${
              isActive
                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700'
            }`}
          >
            {opt}
          </button>
        )
      })}
    </div>
  )
}
