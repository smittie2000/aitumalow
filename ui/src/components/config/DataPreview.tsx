import { useState } from 'react'
import { JsonViewer } from '../shared/JsonViewer'

export function DataPreview({ data }: { data: unknown }) {
  const [view, setView] = useState<'table' | 'json'>('table')
  const items = Array.isArray(data) ? data : []
  const tabular = items.length > 0 && items.every((item) => item && typeof item === 'object' && !Array.isArray(item))
  const fields = tabular ? [...new Set(items.slice(0, 100).flatMap((item) => Object.keys(item)))] : []
  const columns = fields.slice(0, 20)

  return <div className="min-w-0">
    <div className="mb-2 flex items-center justify-between gap-2 text-[11px] text-gray-500 dark:text-gray-400">
      <span>{Array.isArray(data) ? `${items.length} item${items.length === 1 ? '' : 's'}` : 'Value'}</span>
      {tabular && <div className="flex rounded-md bg-gray-100 p-0.5 dark:bg-gray-900">{(['table', 'json'] as const).map((mode) => <button type="button" key={mode} aria-pressed={view === mode} onClick={() => setView(mode)} className={`rounded px-2 py-1 ${view === mode ? 'bg-white text-gray-800 shadow-sm dark:bg-gray-700 dark:text-gray-100' : ''}`}>{mode === 'table' ? 'Table' : 'JSON'}</button>)}</div>}
    </div>
    {tabular && view === 'table' ? <>
      <div className="max-h-80 overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table className="w-full border-collapse text-left text-xs">
          <thead className="sticky top-0 bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-300"><tr>{columns.map((key) => <th key={key} className="whitespace-nowrap border-b border-gray-200 px-3 py-2 font-medium dark:border-gray-700">{key}</th>)}</tr></thead>
          <tbody>{items.slice(0, 100).map((item, index) => <tr key={index} className="border-b border-gray-100 last:border-0 dark:border-gray-700/50">{columns.map((key) => <td key={key} className="max-w-64 truncate px-3 py-2 font-mono text-gray-600 dark:text-gray-300" title={JSON.stringify(item[key])}>{typeof item[key] === 'object' ? JSON.stringify(item[key]) : String(item[key] ?? '—')}</td>)}</tr>)}</tbody>
        </table>
      </div>
      {(items.length > 100 || fields.length > 20) && <p className="mt-2 text-[11px] text-gray-400">Table preview shows up to 100 items and 20 fields. JSON contains the full result.</p>}
    </> : <JsonViewer data={data} maxHeight="320px" />}
  </div>
}
