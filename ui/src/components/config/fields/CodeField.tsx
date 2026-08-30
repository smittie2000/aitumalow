import type { AvailableVariablesResponse, ConfigSchemaField } from '../../../api/types'
import { ExpressionInput } from './ExpressionInput'

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
  variables?: AvailableVariablesResponse | null
}

export function CodeField({ field, value, onChange, variables }: Props) {
  const input = (
    <textarea
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value)}
      rows={8}
      spellCheck={false}
      className="w-full rounded-md border border-gray-300 bg-gray-50 px-2.5 py-1.5 font-mono text-xs leading-relaxed focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
      placeholder={field.placeholder ?? field.label}
    />
  )

  if (field.supports_expression) {
    return <ExpressionInput value={value ?? ''} onChange={onChange} field={field} variables={variables}>{input}</ExpressionInput>
  }

  return input
}
