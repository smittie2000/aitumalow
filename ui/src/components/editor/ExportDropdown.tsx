import { useState, useRef, useEffect, useCallback } from 'react'
import { useReactFlow, getNodesBounds, getViewportForBounds } from '@xyflow/react'
import { toPng } from 'html-to-image'
import { Download, FileJson, Image } from 'lucide-react'
import type { Workflow } from '../../api/types'
import { exportAsJson } from '../../lib/exportWorkflow'

const IMAGE_WIDTH = 1024
const IMAGE_HEIGHT = 768

interface ExportDropdownProps {
  workflow: Workflow
}

export function ExportDropdown({ workflow }: ExportDropdownProps) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const { getNodes } = useReactFlow()

  useEffect(() => {
    if (!open) return
    const handleClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [open])

  const handleExportJson = useCallback(() => {
    exportAsJson(workflow)
    setOpen(false)
  }, [workflow])

  const handleExportPng = useCallback(() => {
    const nodesBounds = getNodesBounds(getNodes())
    const viewport = getViewportForBounds(
      nodesBounds,
      IMAGE_WIDTH,
      IMAGE_HEIGHT,
      0.5,
      2,
      0.1,
    )

    const viewportEl = document.querySelector('.react-flow__viewport') as HTMLElement
    if (!viewportEl) return

    toPng(viewportEl, {
      backgroundColor: '#f9fafb',
      width: IMAGE_WIDTH,
      height: IMAGE_HEIGHT,
      style: {
        width: String(IMAGE_WIDTH),
        height: String(IMAGE_HEIGHT),
        transform: `translate(${viewport.x}px, ${viewport.y}px) scale(${viewport.zoom})`,
      },
    }).then((dataUrl) => {
      const a = document.createElement('a')
      const slug = workflow.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
      a.setAttribute('download', `${slug}.png`)
      a.setAttribute('href', dataUrl)
      a.click()
    })

    setOpen(false)
  }, [getNodes, workflow.name])

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
        title="Export"
      >
        <Download size={14} />
        Export
      </button>

      {open && (
        <div className="absolute right-0 top-full z-50 mt-1 min-w-[160px] rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
          <button
            onClick={handleExportJson}
            className="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            <FileJson size={14} />
            Export as JSON
          </button>
          <button
            onClick={handleExportPng}
            className="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            <Image size={14} />
            Export as PNG
          </button>
        </div>
      )}
    </div>
  )
}
