import { type ReactNode, useState, useRef, useEffect, useCallback } from 'react'
import type { ConfigSchemaField, AvailableVariablesResponse } from '../../../api/types'

interface SuggestionItem {
  label: string
  value: string
  type: 'variable' | 'function'
  detail?: string
}

interface Props {
  field: ConfigSchemaField
  value: string
  onChange: (value: string) => void
  children: ReactNode
  variables?: AvailableVariablesResponse | null
}

export function ExpressionInput({ children, value, onChange, variables }: Props) {
  const [suggestions, setSuggestions] = useState<SuggestionItem[]>([])
  const [selectedIndex, setSelectedIndex] = useState(0)
  const [showDropdown, setShowDropdown] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement | HTMLTextAreaElement | null>(null)

  // Find the actual input/textarea inside children
  useEffect(() => {
    if (containerRef.current) {
      const el = containerRef.current.querySelector('input, textarea')
      if (el) inputRef.current = el as HTMLInputElement | HTMLTextAreaElement
    }
  }, [children])

  const buildSuggestions = useCallback((query: string): SuggestionItem[] => {
    if (!variables) return []
    const items: SuggestionItem[] = []
    const q = query.toLowerCase()

    // Globals
    for (const g of variables.globals) {
      if (g.path.toLowerCase().includes(q)) {
        items.push({ label: g.path, value: g.path, type: 'variable', detail: g.label })
      }
    }

    // Upstream nodes
    for (const node of variables.nodes) {
      for (const v of node.variables) {
        if (v.path.toLowerCase().includes(q) || node.node_name.toLowerCase().includes(q)) {
          items.push({ label: v.path, value: v.path, type: 'variable', detail: `${node.node_name} - ${v.label}` })
        }
      }
    }

    // Functions
    for (const fn of variables.functions) {
      if (fn.name.toLowerCase().includes(q)) {
        items.push({ label: `${fn.name}(${fn.args})`, value: `${fn.name}()`, type: 'function', detail: fn.label })
      }
    }

    return items.slice(0, 15)
  }, [variables])

  const handleInput = useCallback((e: Event) => {
    const target = e.target as HTMLInputElement | HTMLTextAreaElement
    const val = target.value
    const cursor = target.selectionStart ?? val.length

    // Find {{ before cursor
    const before = val.substring(0, cursor)
    const openIdx = before.lastIndexOf('{{')
    const closeIdx = before.lastIndexOf('}}')

    if (openIdx !== -1 && openIdx > closeIdx) {
      const query = before.substring(openIdx + 2).trim()
      const items = buildSuggestions(query)
      setSuggestions(items)
      setSelectedIndex(0)
      setShowDropdown(items.length > 0)
    } else {
      setShowDropdown(false)
    }
  }, [buildSuggestions])

  const insertSuggestion = useCallback((item: SuggestionItem) => {
    const el = inputRef.current
    if (!el) return

    const cursor = el.selectionStart ?? value.length
    const before = value.substring(0, cursor)
    const after = value.substring(cursor)
    const openIdx = before.lastIndexOf('{{')

    if (openIdx === -1) return

    const newValue = before.substring(0, openIdx) + `{{ ${item.value} }}` + after
    onChange(newValue)
    setShowDropdown(false)

    // Move cursor after inserted expression
    setTimeout(() => {
      const newPos = openIdx + item.value.length + 6 // {{ + space + value + space + }}
      el.setSelectionRange(newPos, newPos)
      el.focus()
    }, 0)
  }, [value, onChange])

  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    if (!showDropdown) return

    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSelectedIndex((i) => Math.min(i + 1, suggestions.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSelectedIndex((i) => Math.max(i - 1, 0))
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if (suggestions[selectedIndex]) {
        e.preventDefault()
        insertSuggestion(suggestions[selectedIndex])
      }
    } else if (e.key === 'Escape') {
      setShowDropdown(false)
    }
  }, [showDropdown, suggestions, selectedIndex, insertSuggestion])

  useEffect(() => {
    const el = inputRef.current
    if (!el) return

    el.addEventListener('input', handleInput)
    el.addEventListener('keydown', handleKeyDown as EventListener)

    return () => {
      el.removeEventListener('input', handleInput)
      el.removeEventListener('keydown', handleKeyDown as EventListener)
    }
  }, [handleInput, handleKeyDown])

  // Close dropdown on click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setShowDropdown(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <div className="relative" ref={containerRef}>
      {children}
      <span className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 rounded bg-amber-100 px-1 py-0.5 text-[9px] font-mono text-amber-700 dark:bg-amber-900/50 dark:text-amber-400">
        {'{{ }}'}
      </span>

      {showDropdown && (
        <div className="absolute left-0 top-full z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
          {suggestions.map((item, i) => (
            <button
              key={`${item.type}-${item.value}`}
              onMouseDown={(e) => {
                e.preventDefault()
                insertSuggestion(item)
              }}
              className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs ${
                i === selectedIndex
                  ? 'bg-blue-50 dark:bg-blue-900/30'
                  : 'hover:bg-gray-50 dark:hover:bg-gray-700'
              }`}
            >
              <span
                className={`rounded px-1 py-0.5 text-[9px] font-medium ${
                  item.type === 'function'
                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/50 dark:text-purple-400'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                }`}
              >
                {item.type === 'function' ? 'fn' : 'var'}
              </span>
              <span className="font-mono text-gray-800 dark:text-gray-200">{item.label}</span>
              {item.detail && (
                <span className="ml-auto truncate text-[10px] text-gray-400 dark:text-gray-500">
                  {item.detail}
                </span>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
