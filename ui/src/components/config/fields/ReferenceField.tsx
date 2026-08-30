import { useEffect, useState } from 'react'
import type { ConfigSchemaField, ReferenceOption } from '../../../api/types'
import { useEditorSdk } from '../../../sdk/EditorSdkContext'

interface Props {
  field: ConfigSchemaField
  workflowId: number
  value: string
  onChange: (value: string) => void
}

export function ReferenceField({ field, workflowId, value, onChange }: Props) {
  const { references } = useEditorSdk()
  const [options, setOptions] = useState<ReferenceOption[]>([])
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    if (!field.source) return

    let cancelled = false

    references.list(workflowId, field.source)
      .then((response) => {
        if (!cancelled) {
          setOptions(response.data)
          setFailed(false)
        }
      })
      .catch(() => {
        if (!cancelled) setFailed(true)
      })

    return () => {
      cancelled = true
    }
  }, [field.source, references, workflowId])

  return (
    <div>
      <select
        value={value ?? ''}
        onChange={(event) => onChange(event.target.value)}
        disabled={!field.source || failed}
        className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
      >
        <option value="">Select {field.label}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value} title={option.description ?? undefined}>
            {option.label}
          </option>
        ))}
      </select>
      {failed && (
        <p className="mt-1 text-[10px] text-red-500">References are unavailable for this workflow.</p>
      )}
    </div>
  )
}
