import type { AvailableVariablesResponse, ConfigSchemaField } from '../../../api/types'
import { ExpressionInput } from './ExpressionInput'

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
  variables?: AvailableVariablesResponse | null
}

export function UrlField({ field, value, onChange, variables }: Props) {
  const input = (
    <input
      type="url"
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value)}
      className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
      placeholder={field.placeholder ?? 'https://'}
      readOnly={field.readonly}
      disabled={field.readonly}
    />
  )

  if (field.supports_expression) {
    return <ExpressionInput value={value ?? ''} onChange={onChange} field={field} variables={variables}>{input}</ExpressionInput>
  }

  return input
}
