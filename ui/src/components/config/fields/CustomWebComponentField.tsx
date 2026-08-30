import { useRef, useEffect } from 'react'
import type { ConfigSchemaField } from '../../../api/types'

interface Props {
  field: ConfigSchemaField
  value: unknown
  onChange: (value: unknown) => void
}

export function CustomWebComponentField({ field, value, onChange }: Props) {
  const containerRef = useRef<HTMLDivElement>(null)
  const elementRef = useRef<HTMLElement | null>(null)

  useEffect(() => {
    const tag = field.custom_component
    if (!tag || !containerRef.current) return
    const container = containerRef.current

    const el = document.createElement(tag)
    el.setAttribute('field-config', JSON.stringify(field))
    elementRef.current = el
    container.appendChild(el)

    const handler = (e: Event) => {
      onChange((e as CustomEvent).detail?.value)
    }
    el.addEventListener('wf-change', handler)

    return () => {
      el.removeEventListener('wf-change', handler)
      if (container.contains(el)) {
        container.removeChild(el)
      }
    }
  }, [field.custom_component]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (elementRef.current) {
      (elementRef.current as unknown as Record<string, unknown>).value = value
    }
  }, [value])

  if (!field.custom_component) {
    return (
      <div className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600 dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
        Missing custom_component for field "{field.key}"
      </div>
    )
  }

  if (!customElements.get(field.custom_component)) {
    return (
      <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-600 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
        Custom component "{field.custom_component}" not loaded
      </div>
    )
  }

  return <div ref={containerRef} />
}
