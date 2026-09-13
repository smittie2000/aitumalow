import { BaseEdge, EdgeLabelRenderer, getBezierPath, useReactFlow, type EdgeProps } from '@xyflow/react'
import { Plus } from 'lucide-react'
import { useActionPicker } from './ActionPickerContext'
import { useWorkflowEditorStore } from '../../stores/EditorRuntimeProvider'

export function WorkflowEdge(props: EdgeProps) {
  const [path, x, y] = getBezierPath(props)
  const openPicker = useActionPicker()
  const { getNode } = useReactFlow()
  const blocked = useWorkflowEditorStore((state) => state.isEditing || !!state.failedEdit || state.editConflict)
  const source = getNode(props.source)?.data.label ?? 'previous step'
  const target = getNode(props.target)?.data.label ?? 'next step'

  return <>
    <BaseEdge id={props.id} path={path} markerEnd={props.markerEnd} style={props.style} interactionWidth={24} />
    <EdgeLabelRenderer>
      <button type="button" disabled={blocked} aria-label={`Insert step between ${source} and ${target}`} title="Insert a step into this connection"
        onClick={(event) => { event.stopPropagation(); openPicker({ edgeId: props.id, position: { x, y } }) }}
        className={`nodrag nopan pointer-events-auto absolute flex h-6 w-6 items-center justify-center rounded-full border bg-white text-gray-500 shadow-sm transition hover:border-blue-400 hover:text-blue-600 focus-visible:outline-blue-500 disabled:opacity-40 dark:bg-gray-800 dark:text-gray-300 ${props.selected ? 'border-blue-400' : 'border-gray-200 dark:border-gray-600'}`}
        style={{ transform: `translate(-50%, -50%) translate(${x}px, ${y}px)` }}>
        <Plus size={13} />
      </button>
    </EdgeLabelRenderer>
  </>
}
