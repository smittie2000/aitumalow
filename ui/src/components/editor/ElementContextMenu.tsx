import { useEffect, useRef } from 'react'
import { Settings2, Trash2 } from 'lucide-react'

export interface ElementContextTarget {
  kind: 'node' | 'edge'
  id: string
  label: string
  x: number
  y: number
}

interface ElementContextMenuProps {
  target: ElementContextTarget
  onConfigure: (nodeId: string) => void
  onDelete: (target: ElementContextTarget) => void
  onClose: () => void
}

export function ElementContextMenu({ target, onConfigure, onDelete, onClose }: ElementContextMenuProps) {
  const menuRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    menuRef.current?.querySelector<HTMLButtonElement>('button')?.focus()
  }, [])

  return (
    <div
      ref={menuRef}
      role="menu"
      aria-label={`${target.kind === 'node' ? target.label : 'Connection'} actions`}
      className="fixed z-50 w-48 rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
      style={{ top: target.y, left: target.x }}
      onKeyDown={(event) => {
        const items = [...(menuRef.current?.querySelectorAll<HTMLButtonElement>('button') ?? [])]
        const currentIndex = items.indexOf(document.activeElement as HTMLButtonElement)

        if (event.key === 'Escape') {
          event.stopPropagation()
          onClose()
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault()
          const direction = event.key === 'ArrowDown' ? 1 : -1
          const nextIndex = (currentIndex + direction + items.length) % items.length
          items[nextIndex]?.focus()
        }
      }}
    >
      {target.kind === 'node' && (
        <button
          type="button"
          role="menuitem"
          className="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
          onClick={() => onConfigure(target.id)}
        >
          <Settings2 size={14} />
          Configure node
        </button>
      )}
      <button
        type="button"
        role="menuitem"
        className="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:text-red-400 dark:hover:bg-red-900/30 dark:focus:bg-red-900/30"
        onClick={() => onDelete(target)}
      >
        <Trash2 size={14} />
        Delete {target.kind}
      </button>
    </div>
  )
}
