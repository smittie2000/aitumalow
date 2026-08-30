import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: number | string
  onChange: (value: number) => void
}

export function SliderField({ field, value, onChange }: Props) {
  const min = field.min ?? 0
  const max = field.max ?? 100
  const step = field.step ?? 1
  const numValue = typeof value === 'string' ? parseFloat(value) || min : (value ?? min)

  return (
    <div className="flex items-center gap-3">
      <input
        type="range"
        min={min}
        max={max}
        step={step}
        value={numValue}
        onChange={(e) => onChange(parseFloat(e.target.value))}
        className="h-1.5 w-full cursor-pointer appearance-none rounded-full bg-gray-200 accent-blue-600 dark:bg-gray-600"
      />
      <span className="min-w-[3ch] text-right text-xs font-medium text-gray-600 dark:text-gray-400">
        {numValue}
      </span>
    </div>
  )
}
