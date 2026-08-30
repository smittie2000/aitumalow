import { Fragment } from 'react'
import type { AvailableVariablesResponse, ConfigSchemaField } from '../../api/types'
import { StringField } from './fields/StringField'
import { TextareaField } from './fields/TextareaField'
import { SelectField } from './fields/SelectField'
import { BooleanField } from './fields/BooleanField'
import { IntegerField } from './fields/IntegerField'
import { JsonField } from './fields/JsonField'
import { KeyValueField } from './fields/KeyValueField'
import { ArrayOfObjectsField } from './fields/ArrayOfObjectsField'
import { MultiSelectField } from './fields/MultiSelectField'
import { NumberField } from './fields/NumberField'
import { ColorField } from './fields/ColorField'
import { UrlField } from './fields/UrlField'
import { SliderField } from './fields/SliderField'
import { CodeField } from './fields/CodeField'
import { InfoField } from './fields/InfoField'
import { SectionField } from './fields/SectionField'
import { CustomWebComponentField } from './fields/CustomWebComponentField'
import { WorkflowSelectField } from './fields/WorkflowSelectField'
import { ReferenceField } from './fields/ReferenceField'

interface Props {
  schema: ConfigSchemaField[]
  values: Record<string, unknown>
  onChange: (key: string, value: unknown) => void
  variables?: AvailableVariablesResponse | null
  workflowId?: number
}

export function DynamicForm({ schema, values, onChange, variables, workflowId }: Props) {
  const visible = schema.filter((field) => {
    if (!field.show_when) return true
    const current = values[field.show_when.key]
    const expected = field.show_when.value
    return Array.isArray(expected) ? expected.includes(current as string) : current === expected
  })

  // Group fields into sections
  const groups: { section: ConfigSchemaField | null; fields: ConfigSchemaField[] }[] = []
  let current: { section: ConfigSchemaField | null; fields: ConfigSchemaField[] } = { section: null, fields: [] }

  for (const field of visible) {
    if (field.type === 'section') {
      if (current.section || current.fields.length > 0) {
        groups.push(current)
      }
      current = { section: field, fields: [] }
    } else {
      current.fields.push(field)
    }
  }
  if (current.section || current.fields.length > 0) {
    groups.push(current)
  }

  return (
    <div className="space-y-3">
      {groups.map((group, gi) => {
        const fields = group.fields.map((field) => {
          const resolvedField = field.depends_on && field.options_map
            ? { ...field, options: field.options_map[values[field.depends_on] as string] ?? [] }
            : field
          const value = values[field.key]
          const hideLabel = field.type === 'boolean' || field.type === 'info'

          return (
            <div key={field.key}>
              {!hideLabel && (
                <label className="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-400">
                  {field.label}
                  {field.required && <span className="ml-0.5 text-red-400">*</span>}
                </label>
              )}
              <FieldRenderer field={resolvedField} value={value} onChange={(v) => onChange(field.key, v)} variables={variables} workflowId={workflowId} />
              {field.description && (
                <p className="mt-1 text-[10px] leading-tight text-gray-400 dark:text-gray-500">{field.description}</p>
              )}
            </div>
          )
        })

        if (group.section) {
          return (
            <SectionField key={group.section.key} field={group.section}>
              {fields}
            </SectionField>
          )
        }

        return <Fragment key={`group-${gi}`}>{fields}</Fragment>
      })}
    </div>
  )
}

function FieldRenderer({
  field,
  value,
  onChange,
  variables,
  workflowId,
}: {
  field: ConfigSchemaField
  value: unknown
  onChange: (value: unknown) => void
  variables?: AvailableVariablesResponse | null
  workflowId?: number
}) {
  switch (field.type) {
    case 'string':
    case 'cron':
    case 'timezone':
      return <StringField field={field} value={value as string} onChange={onChange} variables={variables} />
    case 'reference':
      return workflowId == null
        ? <StringField field={field} value={value as string} onChange={onChange} variables={variables} />
        : <ReferenceField field={field} workflowId={workflowId} value={value as string} onChange={onChange} />
    case 'textarea':
      return <TextareaField field={field} value={value as string} onChange={onChange} variables={variables} />
    case 'select':
      return <SelectField field={field} value={value as string} onChange={onChange} />
    case 'boolean':
      return <BooleanField field={field} value={value as boolean} onChange={onChange} />
    case 'integer':
      return <IntegerField field={field} value={value as number} onChange={onChange} />
    case 'json':
      return <JsonField field={field} value={value} onChange={onChange} />
    case 'keyvalue':
      return (
        <KeyValueField
          field={field}
          value={value as Record<string, string> | null}
          onChange={onChange as (v: Record<string, string>) => void}
        />
      )
    case 'array_of_objects':
      return (
        <ArrayOfObjectsField
          field={field}
          value={value as Record<string, unknown>[] | null}
          onChange={onChange as (v: Record<string, unknown>[]) => void}
        />
      )
    case 'multiselect':
      return (
        <MultiSelectField
          field={field}
          value={value as string[] | null}
          onChange={onChange as (v: string[]) => void}
        />
      )
    case 'number':
      return <NumberField field={field} value={value as number} onChange={onChange as (v: number) => void} />
    case 'color':
      return <ColorField field={field} value={value as string} onChange={onChange} />
    case 'url':
      return <UrlField field={field} value={value as string} onChange={onChange} variables={variables} />
    case 'slider':
      return <SliderField field={field} value={value as number} onChange={onChange as (v: number) => void} />
    case 'code':
      return <CodeField field={field} value={value as string} onChange={onChange} variables={variables} />
    case 'info':
      return <InfoField field={field} />
    case 'workflow_select':
      return <WorkflowSelectField field={field} value={value as number | null} onChange={onChange as (v: number | null) => void} />
    case 'custom':
      return <CustomWebComponentField field={field} value={value} onChange={onChange} />
    default:
      return <StringField field={field} value={String(value ?? '')} onChange={onChange} />
  }
}
