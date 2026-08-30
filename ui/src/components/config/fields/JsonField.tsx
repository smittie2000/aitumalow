import { useState } from 'react'
import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: unknown
  onChange: (value: unknown) => void
}

export function JsonField({ field, value, onChange }: Props) {
  const [raw, setRaw] = useState(() => {
    if (typeof value === 'string') return value
    return value != null ? JSON.stringify(value, null, 2) : ''
  })
  const [error, setError] = useState<string | null>(null)

  const handleChange = (text: string) => {
    setRaw(text)
    if (!text.trim()) {
      setError(null)
      onChange(null)
      return
    }
    try {
      const parsed = JSON.parse(text)
      setError(null)
      onChange(parsed)
    } catch {
      setError('Invalid JSON')
    }
  }

  return (
    <div>
      <textarea
        value={raw}
        onChange={(e) => handleChange(e.target.value)}
        className={`w-full rounded-md border px-2.5 py-1.5 font-mono text-xs focus:outline-none focus:ring-1 ${
          error
            ? 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-600'
            : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100'
        }`}
        rows={6}
        placeholder={field.label}
      />
      {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
    </div>
  )
}
