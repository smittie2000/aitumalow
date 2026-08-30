import { useState, useEffect, useRef } from 'react'
import type { ConfigSchemaField, Workflow } from '../../../api/types'
import { useEditorSdk } from '../../../sdk/EditorSdkContext'

interface Props {
  field: ConfigSchemaField
  value: number | null
  onChange: (value: number | null) => void
}

export function WorkflowSelectField({ field, value, onChange }: Props) {
  const { workflows: workflowsApi } = useEditorSdk()
  const [workflows, setWorkflows] = useState<Workflow[]>([])
  const [loading, setLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    workflowsApi.list({ sort: 'name', direction: 'asc' })
      .then((res) => setWorkflows(res.data))
      .finally(() => setLoading(false))
  }, [workflowsApi])

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const selected = workflows.find((w) => w.id === value)
  const filtered = workflows.filter((w) =>
    w.name.toLowerCase().includes(search.toLowerCase()),
  )

  if (loading) {
    return (
      <div className="w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm text-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-500">
        Loading workflows...
      </div>
    )
  }

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex w-full items-center justify-between rounded-md border border-gray-300 px-2.5 py-1.5 text-left text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
      >
        <span className={selected ? '' : 'text-gray-400 dark:text-gray-500'}>
          {selected ? (
            <>
              {selected.name}
              {!selected.is_active && <span className="ml-1.5 rounded bg-yellow-100 px-1 py-0.5 text-[10px] text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">Inactive</span>}
            </>
          ) : (field.required ? 'Select workflow...' : 'Any workflow')}
        </span>
        <svg className="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {open && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
          <div className="p-1.5">
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search workflows..."
              autoFocus
              className="w-full rounded border border-gray-200 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            />
          </div>
          <div className="max-h-48 overflow-y-auto">
            {!field.required && (
              <button
                type="button"
                onClick={() => { onChange(null); setOpen(false); setSearch('') }}
                className={`w-full px-2.5 py-1.5 text-left text-sm hover:bg-blue-50 dark:hover:bg-gray-700 ${value === null ? 'bg-blue-50 font-medium text-blue-600 dark:bg-gray-700 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400'}`}
              >
                Any workflow
              </button>
            )}
            {filtered.length === 0 && (
              <div className="px-2.5 py-2 text-sm text-gray-400">No workflows found</div>
            )}
            {filtered.map((w) => (
              <button
                key={w.id}
                type="button"
                onClick={() => { onChange(w.id); setOpen(false); setSearch('') }}
                className={`w-full px-2.5 py-1.5 text-left text-sm hover:bg-blue-50 dark:hover:bg-gray-700 ${w.id === value ? 'bg-blue-50 font-medium text-blue-600 dark:bg-gray-700 dark:text-blue-400' : 'text-gray-700 dark:text-gray-200'}`}
              >
                <span className={w.is_active ? '' : 'text-gray-400 dark:text-gray-500'}>{w.name}</span>
                <span className="ml-1.5 text-xs text-gray-400">#{w.id}</span>
                {!w.is_active && <span className="ml-1.5 rounded bg-yellow-100 px-1 py-0.5 text-[10px] text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">Inactive</span>}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
