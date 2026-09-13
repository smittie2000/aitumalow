import { useCallback, useEffect, useRef, useState } from 'react'
import {
  ReactFlow,
  MiniMap,
  Controls,
  Background,
  BackgroundVariant,
  type OnSelectionChangeFunc,
  type OnNodeDrag,
  type Connection,
  type OnConnectEnd,
  type FitViewOptions,
  type Node,
  type Edge,
  useReactFlow,
  useStore,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import { LayoutGrid, Map, MapPinOff, Plus, Search, Workflow, Undo2, Redo2, Loader2 } from 'lucide-react'
import { useShallow } from 'zustand/react/shallow'

import {
  useRegistryStore,
  useThemeStore,
  useWorkflowEditorStore,
  useWorkflowEditorStoreApi,
} from '../../stores/EditorRuntimeProvider'
import { useActionPicker } from './ActionPickerContext'
import { WorkflowEdge } from './WorkflowEdge'
import { apiErrorMessage } from '../../api/client'
import type { CustomNodeData } from '../../lib/mappers'
import { CustomNode } from '../nodes/CustomNode'
import { StickyNoteNode } from '../nodes/StickyNoteNode'
import { ConfirmDialog } from '../shared/ConfirmDialog'
import { ElementContextMenu, type ElementContextTarget } from './ElementContextMenu'
import { NodeNavigator } from './NodeNavigator'
import { getNewNodePosition, getNodeDimensions } from '../../lib/nodePlacement'

const edgeTypes = { default: WorkflowEdge }
const nodeTypes = { custom: CustomNode, sticky_note: StickyNoteNode }
const proOptions = { hideAttribution: true }
const fitViewOptions: FitViewOptions = {
  padding: { top: '80px', bottom: '120px', left: '64px', right: '64px' },
  maxZoom: 1,
  duration: 250,
}
const panOnDrag = [0, 1, 2]

interface PendingDeletion {
  nodeIds: number[]
  edgeIds: number[]
  label: string
}

export function Canvas({ onOpenStep, showNavigator, onShowNavigator, onCloseNavigator }: {
  onOpenStep: (id: string) => void
  showNavigator: boolean
  onShowNavigator: () => void
  onCloseNavigator: () => void
}) {
  const openActionPicker = useActionPicker()
  const workflowEditorStore = useWorkflowEditorStoreApi()
  const {
    rfNodes,
    rfEdges,
    onNodesChange,
    onEdgesChange,
    addEdge,
    addNode,
    remove,
    moveNodes,
    undo, redo, canUndo, canRedo, blocked, isEditing, editError, failedEdit, editConflict, hasDrafts,
    selectNode,
    autoLayout,
    validationFocus,
  } = useWorkflowEditorStore(useShallow((state) => ({
    rfNodes: state.rfNodes,
    rfEdges: state.rfEdges,
    onNodesChange: state.onNodesChange,
    onEdgesChange: state.onEdgesChange,
    addEdge: state.addEdge,
    addNode: state.addNode,
    remove: state.remove,
    moveNodes: state.moveNodes,
    undo: state.undo, redo: state.redo,
    canUndo: state.undoStack.length > 0, canRedo: state.redoStack.length > 0,
    blocked: state.isEditing || !!state.failedEdit || state.editConflict,
    isEditing: state.isEditing, editError: state.editError, failedEdit: state.failedEdit, editConflict: state.editConflict,
    hasDrafts: Object.keys(state.nodeDrafts).length > 0,
    selectNode: state.selectNode,
    autoLayout: state.autoLayout,
    validationFocus: state.validationFocus,
  })))
  const getByKey = useRegistryStore((s) => s.getByKey)
  const theme = useThemeStore((state) => state.theme)
  const reactFlowWrapper = useRef<HTMLDivElement>(null)
  const { screenToFlowPosition, fitView, setCenter, getViewport } = useReactFlow()
  const width = useStore((state) => state.width)
  const [contextMenu, setContextMenu] = useState<ElementContextTarget | null>(null)
  const [pendingDeletion, setPendingDeletion] = useState<PendingDeletion | null>(null)
  const [showMiniMap, setShowMiniMap] = useState(false)
  const [canvasError, setCanvasError] = useState<string | null>(null)

  const openPickerAt = useCallback((event?: { clientX: number; clientY: number }) => {
    const bounds = reactFlowWrapper.current?.getBoundingClientRect()
    const position = event ?? (bounds ? { clientX: bounds.left + bounds.width / 2, clientY: bounds.top + bounds.height / 2 } : undefined)
    openActionPicker(position ? { position: screenToFlowPosition({ x: position.clientX, y: position.clientY }) } : undefined)
  }, [openActionPicker, screenToFlowPosition])

  useEffect(() => {
    if (!validationFocus) return

    const state = workflowEditorStore.getState()
    const { issue } = validationFocus
    if (issue.nodeId) {
      const node = state.rfNodes.find((candidate) => candidate.id === issue.nodeId)
      if (node) {
        void fitView({ nodes: [node], duration: 400, padding: 0.7 })
      }
      return
    }

    if (issue.edgeId) {
      const edge = state.rfEdges.find((candidate) => candidate.id === issue.edgeId)
      if (!edge) return
      const connectedNodes = state.rfNodes.filter(
        (node) => node.id === edge.source || node.id === edge.target,
      )
      if (connectedNodes.length > 0) {
        void fitView({ nodes: connectedNodes, duration: 400, padding: 0.7 })
      }
    }
  }, [fitView, validationFocus, workflowEditorStore])

  const onConnect = useCallback(
    (connection: Connection) => {
      setCanvasError(null)
      void addEdge(connection).catch(() => setCanvasError('The connection could not be saved. Drag between the steps to try again.'))
    },
    [addEdge],
  )

  const onConnectEnd: OnConnectEnd = useCallback((event, connection) => {
    if (connection.isValid || !connection.fromNode || connection.fromHandle?.type !== 'source') return
    const point = 'changedTouches' in event ? event.changedTouches[0] : event
    if (!point) return
    const target = document.elementFromPoint(point.clientX, point.clientY)
    if (!target?.classList.contains('react-flow__pane') || !reactFlowWrapper.current?.contains(target)) return
    openActionPicker({
      source: { nodeId: connection.fromNode.id, port: connection.fromHandle.id ?? 'main' },
      position: screenToFlowPosition({ x: point.clientX, y: point.clientY }),
    })
  }, [openActionPicker, screenToFlowPosition])

  const onNodeDragStop: OnNodeDrag<Node<CustomNodeData>> = useCallback(
    (_event, node, nodes) => {
      void moveNodes(nodes.length ? nodes : [node]).catch((error) => setCanvasError(apiErrorMessage(error, 'The move could not be saved.')))
    },
    [moveNodes],
  )

  const onSelectionChange: OnSelectionChangeFunc = useCallback(
    ({ nodes }) => {
      if (nodes.length === 1) {
        selectNode(nodes[0].id)
      } else {
        selectNode(null)
      }
    },
    [selectNode],
  )

  const onDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = 'move'
  }, [])

  const onDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault()
      const nodeKey = e.dataTransfer.getData('application/workflow-node-key')
      if (!nodeKey) return

      const registryNode = getByKey(nodeKey)
      if (!registryNode) return

      const anchor = screenToFlowPosition({
        x: e.clientX,
        y: e.clientY,
      })
      const position = getNewNodePosition(workflowEditorStore.getState().rfNodes, registryNode, { position: anchor }, anchor)
      setCanvasError(null)
      void addNode(nodeKey, position, registryNode).then((id) => {
        if (id) {
          selectNode(id)
          onCloseNavigator()
        }
      }).catch(() => setCanvasError('The step could not be added. Please drag it onto the canvas again.'))
    },
    [getByKey, screenToFlowPosition, addNode, workflowEditorStore, selectNode, onCloseNavigator],
  )

  const onKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      const target = e.target as HTMLElement
      if (target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="dialog"], [role="menu"]')) return
      if ((e.ctrlKey || e.metaKey) && (e.key.toLowerCase() === 'z' || e.key.toLowerCase() === 'y')) {
        e.preventDefault()
        if (!blocked && !hasDrafts) void (e.shiftKey || e.key.toLowerCase() === 'y' ? redo() : undo()).catch((error) => setCanvasError(apiErrorMessage(error, 'The edit could not be restored.')))
        return
      }
      if (e.ctrlKey || e.metaKey || e.altKey) return
      if (e.key === 'Escape') {
        onCloseNavigator()
        setContextMenu(null)
        return
      }
      if (e.key.toLowerCase() === 'n' || e.key === '/' || e.key.toLowerCase() === 'f') {
        e.preventDefault()
        if (e.key.toLowerCase() === 'n') openPickerAt()
        else if (e.key === '/') onShowNavigator()
        else void fitView(fitViewOptions)
        return
      }
      if (e.key === 'Delete' || e.key === 'Backspace') {
        const state = workflowEditorStore.getState()
        const nodeIds = state.rfNodes.filter((node) => node.selected).map((node) => parseInt(node.id))
        const edgeIds = nodeIds.length === 0
          ? state.rfEdges.filter((edge) => edge.selected).map((edge) => parseInt(edge.id))
          : []

        if (nodeIds.length === 0 && edgeIds.length === 0) return

        e.preventDefault()
        setPendingDeletion({
          nodeIds,
          edgeIds,
          label: nodeIds.length > 0
            ? `${nodeIds.length} selected node${nodeIds.length === 1 ? '' : 's'}`
            : `${edgeIds.length} selected connection${edgeIds.length === 1 ? '' : 's'}`,
        })
      }
    },
    [workflowEditorStore, openPickerAt, onShowNavigator, onCloseNavigator, fitView, blocked, hasDrafts, undo, redo],
  )

  const menuPosition = useCallback((event: React.MouseEvent, menuHeight: number) => {
    const bounds = reactFlowWrapper.current?.getBoundingClientRect()
    const menuWidth = 192
    const padding = 8

    if (!bounds) return { x: event.clientX, y: event.clientY }

    return {
      x: Math.max(bounds.left + padding, Math.min(event.clientX, bounds.right - menuWidth - padding)),
      y: Math.max(bounds.top + padding, Math.min(event.clientY, bounds.bottom - menuHeight - padding)),
    }
  }, [])

  const onNodeContextMenu = useCallback(
    (event: React.MouseEvent, node: Node<CustomNodeData>) => {
      event.preventDefault()
      selectNode(node.id)
      setContextMenu({
        kind: 'node',
        id: node.id,
        label: node.data.label,
        ...menuPosition(event, 88),
      })
    },
    [menuPosition, selectNode],
  )

  const onEdgeContextMenu = useCallback(
    (event: React.MouseEvent, edge: Edge) => {
      event.preventDefault()
      setContextMenu({
        kind: 'edge',
        id: edge.id,
        label: 'connection',
        ...menuPosition(event, 48),
      })
    },
    [menuPosition],
  )

  const closeContextMenu = useCallback(() => {
    setContextMenu(null)
  }, [])

  useEffect(() => {
    const handleClickOutside = () => setContextMenu(null)
    if (contextMenu) {
      document.addEventListener('click', handleClickOutside)
      return () => document.removeEventListener('click', handleClickOutside)
    }
  }, [contextMenu])

  return (
    <div ref={reactFlowWrapper} className="relative h-full w-full" onKeyDown={onKeyDown} onKeyUp={(event) => {
      const target = event.target as HTMLElement
      if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key) || !target.closest('.react-flow__node, .react-flow__nodesselection-rect') || target.closest('input, textarea, select, [contenteditable]') || blocked) return
      const state = workflowEditorStore.getState()
      const moved = state.rfNodes.filter((node) => {
        const saved = state.workflow?.nodes?.find((entry) => entry.id === Number(node.id))
        return node.selected && saved && (node.position.x !== saved.position_x || node.position.y !== saved.position_y)
      })
      if (moved.length) void moveNodes(moved).catch((error) => setCanvasError(apiErrorMessage(error, 'The move could not be saved.')))
    }} tabIndex={0} aria-label="Workflow canvas">
      <ReactFlow
        nodes={rfNodes}
        edges={rfEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onConnectEnd={onConnectEnd}
        onNodeDragStop={onNodeDragStop}
        onSelectionChange={onSelectionChange}
        onDragOver={onDragOver}
        onDrop={onDrop}
        onNodeClick={(event, node) => { if (!event.shiftKey && !event.ctrlKey && !event.metaKey) selectNode(node.id) }}
        onNodeContextMenu={onNodeContextMenu}
        onNodeDoubleClick={(_event, node) => onOpenStep(node.id)}
        onEdgeContextMenu={onEdgeContextMenu}
        onPaneClick={(event) => {
          closeContextMenu()
          reactFlowWrapper.current?.focus({ preventScroll: true })
          if (event.detail === 2) openPickerAt(event)
        }}
        onMoveStart={closeContextMenu}
        nodeTypes={nodeTypes}
        edgeTypes={edgeTypes}
        nodesDraggable={!blocked}
        nodesConnectable={!blocked}
        fitView
        fitViewOptions={fitViewOptions}
        minZoom={0.2}
        maxZoom={1.75}
        panOnDrag={panOnDrag}
        zoomOnDoubleClick={false}
        autoPanOnSelection
        onlyRenderVisibleElements
        paneClickDistance={4}
        nodeDragThreshold={3}
        connectionDragThreshold={4}
        deleteKeyCode={null}
        proOptions={proOptions}
        className="bg-gray-50 dark:bg-gray-900"
      >
        <Controls position="bottom-left" showInteractive={false} fitViewOptions={fitViewOptions} />
        <div className="absolute left-4 top-4 z-10 flex gap-2">
          <button type="button" disabled={blocked} onClick={() => openPickerAt()} aria-keyshortcuts="n" title="Add a step (N)" className="flex items-center gap-2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"><Plus size={16} /> Add step</button>
          <button type="button" onClick={onShowNavigator} aria-label="Find a step" aria-keyshortcuts="/" aria-expanded={showNavigator} title="Find a step (/)" disabled={rfNodes.length === 0} className="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"><Search size={15} /><span className="hidden sm:inline">Find step</span></button>
          <button
            type="button"
            disabled={blocked || rfNodes.length === 0}
            onClick={() => { setCanvasError(null); void autoLayout().then(() => window.requestAnimationFrame(() => fitView(fitViewOptions))).catch((error) => setCanvasError(apiErrorMessage(error, 'The layout could not be saved.'))) }}
            className="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            title="Auto Layout"
          >
            <LayoutGrid size={14} />
            <span className="hidden md:inline">Auto Layout</span>
          </button>
          <button
            type="button"
            onClick={() => setShowMiniMap((v) => !v)}
            className={`rounded-md border border-gray-200 bg-white p-1.5 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700 ${showMiniMap ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500'}`}
            title={showMiniMap ? 'Hide mini map' : 'Show mini map'}
            aria-label={showMiniMap ? 'Hide mini map' : 'Show mini map'}
            aria-pressed={showMiniMap}
          >
            {showMiniMap ? <Map size={14} /> : <MapPinOff size={14} />}
          </button>
          <div className="flex rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800">
            <button type="button" aria-label="Undo" title={hasDrafts ? 'Save or discard step settings to undo' : 'Undo (Ctrl/⌘ Z)'} disabled={blocked || !canUndo || hasDrafts} onClick={() => { setCanvasError(null); void undo().catch(() => {}) }} className="rounded-l-lg p-2 text-gray-600 hover:bg-gray-50 disabled:opacity-30 dark:text-gray-300 dark:hover:bg-gray-700"><Undo2 size={15} /></button>
            <button type="button" aria-label="Redo" title={hasDrafts ? 'Save or discard step settings to redo' : 'Redo (Ctrl/⌘ Shift Z)'} disabled={blocked || !canRedo || hasDrafts} onClick={() => { setCanvasError(null); void redo().catch(() => {}) }} className="rounded-r-lg p-2 text-gray-600 hover:bg-gray-50 disabled:opacity-30 dark:text-gray-300 dark:hover:bg-gray-700"><Redo2 size={15} /></button>
          </div>
          {isEditing && <span role="status" className="hidden items-center gap-1.5 text-xs text-gray-500 lg:flex"><Loader2 size={13} className="animate-spin" />Saving…</span>}
        </div>
        {showMiniMap && (
          <MiniMap
            position="bottom-right"
            pannable
            zoomable
            className="!rounded-lg !border !border-gray-200 dark:!border-gray-700 !shadow-sm dark:!bg-gray-800"
            nodeColor={theme === 'dark' ? '#64748b' : '#cbd5e1'}
            maskColor={theme === 'dark' ? 'rgb(17 19 27 / 0.65)' : 'rgb(240 240 240 / 0.7)'}
          />
        )}
        <Background variant={BackgroundVariant.Dots} gap={22} size={1} />
        {rfNodes.length === 0 && <div className="absolute inset-0 z-10 flex items-center justify-center pointer-events-none">
          <div className="max-w-sm px-6 text-center">
            <span className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-white text-blue-500 shadow-sm dark:border-gray-700 dark:bg-gray-800"><Workflow size={26} /></span>
            <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-100">What starts this workflow?</h2>
            <p className="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">Choose a trigger, then connect the steps that follow. Each output is a place to continue.</p>
            <button type="button" onClick={() => openPickerAt()} className="pointer-events-auto mt-5 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"><Plus size={16} /> Add trigger</button>
          </div>
        </div>}
        {rfNodes.length > 0 && <div className="pointer-events-none absolute bottom-6 left-36 hidden text-[11px] text-gray-400 lg:block">N add step · / find step · F fit workflow · Double-click to add here</div>}
      </ReactFlow>

      {showNavigator && <NodeNavigator onClose={() => { onCloseNavigator(); reactFlowWrapper.current?.focus() }} onLocate={(id) => {
        const node = workflowEditorStore.getState().rfNodes.find((candidate) => candidate.id === id)
        if (!node) return
        const size = getNodeDimensions(node)
        const zoom = Math.min(Math.max(getViewport().zoom, 0.75), 1)
        selectNode(id)
        onCloseNavigator()
        void setCenter(node.position.x + size.width / 2 + (width >= 768 ? 184 / zoom : 0), node.position.y + size.height / 2, { zoom, duration: 300 })
        reactFlowWrapper.current?.focus()
      }} />}

      {(canvasError || editError) && <div role="alert" className="absolute bottom-16 left-4 z-30 max-w-sm rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">{editError || canvasError}
        {failedEdit && <button type="button" disabled={isEditing} onClick={() => { setCanvasError(null); void workflowEditorStore.getState().retryEdit().catch(() => {}) }} className="ml-2 font-semibold underline">Retry edit</button>}
        {(failedEdit || editConflict) ? <button type="button" disabled={isEditing} onClick={() => { void workflowEditorStore.getState().refreshGraph().then(() => setCanvasError(null)).catch((error) => setCanvasError(apiErrorMessage(error, 'Could not reload the draft.'))) }} className="ml-2 underline">Reload saved draft</button> : <button type="button" onClick={() => { setCanvasError(null); workflowEditorStore.setState({ editError: null }) }} className="ml-2 underline">Dismiss</button>}
      </div>}

      {contextMenu && (
        <ElementContextMenu
          key={`${contextMenu.kind}-${contextMenu.id}`}
          target={contextMenu}
          onConfigure={(nodeId) => {
            selectNode(nodeId)
            setContextMenu(null)
          }}
          onDelete={(target) => {
            setPendingDeletion({
              nodeIds: target.kind === 'node' ? [parseInt(target.id)] : [],
              edgeIds: target.kind === 'edge' ? [parseInt(target.id)] : [],
              label: target.kind === 'node' ? `node “${target.label}”` : 'this connection',
            })
            setContextMenu(null)
          }}
          onClose={() => setContextMenu(null)}
        />
      )}

      <ConfirmDialog
        open={pendingDeletion !== null}
        title="Delete from workflow"
        message={`Delete ${pendingDeletion?.label ?? 'the selected item'}? You can undo this change.`}
        confirmLabel="Delete"
        onConfirm={async () => {
          const deletion = pendingDeletion
          setPendingDeletion(null)
          if (!deletion) return

          await remove(deletion.nodeIds, deletion.edgeIds).catch((error) => setCanvasError(apiErrorMessage(error, 'The selection could not be deleted.')))
        }}
        onCancel={() => setPendingDeletion(null)}
      />
    </div>
  )
}
