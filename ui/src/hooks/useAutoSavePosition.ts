import { useRef, useCallback } from 'react'
import { useWorkflowEditorStore } from '../stores/EditorRuntimeProvider'

const DEBOUNCE_MS = 300

export function useAutoSavePosition() {
  const timers = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map())
  const updateNodePosition = useWorkflowEditorStore((s) => s.updateNodePosition)

  const savePosition = useCallback(
    (nodeId: string, x: number, y: number) => {
      const existing = timers.current.get(nodeId)
      if (existing) clearTimeout(existing)

      const timer = setTimeout(() => {
        updateNodePosition(parseInt(nodeId), x, y)
        timers.current.delete(nodeId)
      }, DEBOUNCE_MS)

      timers.current.set(nodeId, timer)
    },
    [updateNodePosition],
  )

  return savePosition
}
