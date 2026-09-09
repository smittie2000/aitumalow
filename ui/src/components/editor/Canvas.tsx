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
  type FitViewOptions,
  type Node,
  type Edge,
  useReactFlow,
} from '@xyflow/react'
import '@xyflow/react/dist/style.css'
import { LayoutGrid, Map, MapPinOff, Plus, Workflow } from 'lucide-react'
import { useShallow } from 'zustand/react/shallow'

import {
  useRegistryStore,
  useWorkflowEditorStore,
  useWorkflowEditorStoreApi,
} from '../../stores/EditorRuntimeProvider'
import { useActionPicker } from './ActionPickerContext'
import { useAutoSavePosition } from '../../hooks/useAutoSavePosition'
import type { CustomNodeData } from '../../lib/mappers'
import { CustomNode } from '../nodes/CustomNode'
import { StickyNoteNode } from '../nodes/StickyNoteNode'
import { ConfirmDialog } from '../shared/ConfirmDialog'
import { ElementContextMenu, type ElementContextTarget } from './ElementContextMenu'

const nodeTypes = { custom: CustomNode, sticky_note: StickyNoteNode }
const proOptions = { hideAttribution: true }
const fitViewOptions: FitViewOptions = { padding: 0.2, duration: 250 }
const panOnDrag = [0, 1, 2]

interface PendingDeletion {
  nodeIds: number[]
  edgeIds: number[]
  label: string
}

export function Canvas() {
  const openActionPicker = useActionPicker()
  const workflowEditorStore = useWorkflowEditorStoreApi()
  const {
    rfNodes,
    rfEdges,
    onNodesChange,
    onEdgesChange,
    addEdge,
    addNode,
    deleteNode,
    deleteEdge,
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
    deleteNode: state.deleteNode,
    deleteEdge: state.deleteEdge,
    selectNode: state.selectNode,
    autoLayout: state.autoLayout,
    validationFocus: state.validationFocus,
  })))
  const getByKey = useRegistryStore((s) => s.getByKey)
  const savePosition = useAutoSavePosition()
  const reactFlowWrapper = useRef<HTMLDivElement>(null)
  const { screenToFlowPosition, fitView } = useReactFlow()
  const [contextMenu, setContextMenu] = useState<ElementContextTarget | null>(null)
  const [pendingDeletion, setPendingDeletion] = useState<PendingDeletion | null>(null)
  const [showMiniMap, setShowMiniMap] = useState(false)
  const [showLayoutConfirm, setShowLayoutConfirm] = useState(false)

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
      addEdge(connection)
    },
    [addEdge],
  )

  const onNodeDragStop: OnNodeDrag<Node<CustomNodeData>> = useCallback(
    (_event, node) => {
      savePosition(node.id, node.position.x, node.position.y)
    },
    [savePosition],
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

      const position = screenToFlowPosition({
        x: e.clientX,
        y: e.clientY,
      })

      addNode(nodeKey, position, registryNode)
    },
    [getByKey, screenToFlowPosition, addNode],
  )

  const onKeyDown = useCallback(
    (e: React.KeyboardEvent) => {
      if (e.key === 'Delete' || e.key === 'Backspace') {
        const target = e.target as HTMLElement
        if (target.matches('input, textarea, select, [contenteditable="true"]')) {
          return
        }

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
    [workflowEditorStore],
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

  const selectCanvasNode = useCallback((nodeId: string) => {
    const state = workflowEditorStore.getState()
    workflowEditorStore.setState({
      rfNodes: state.rfNodes.map((node) => ({
        ...node,
        selected: node.id === nodeId,
      })),
    })
    state.selectNode(nodeId)
  }, [workflowEditorStore])

  const onNodeContextMenu = useCallback(
    (event: React.MouseEvent, node: Node<CustomNodeData>) => {
      event.preventDefault()
      selectCanvasNode(node.id)
      setContextMenu({
        kind: 'node',
        id: node.id,
        label: node.data.label,
        ...menuPosition(event, 88),
      })
    },
    [menuPosition, selectCanvasNode],
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
    <div ref={reactFlowWrapper} className="h-full w-full" onKeyDown={onKeyDown} tabIndex={0}>
      <ReactFlow
        nodes={rfNodes}
        edges={rfEdges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        onNodeDragStop={onNodeDragStop}
        onSelectionChange={onSelectionChange}
        onDragOver={onDragOver}
        onDrop={onDrop}
        onNodeClick={(_event, node) => selectCanvasNode(node.id)}
        onNodeContextMenu={onNodeContextMenu}
        onEdgeContextMenu={onEdgeContextMenu}
        onPaneClick={closeContextMenu}
        onMoveStart={closeContextMenu}
        nodeTypes={nodeTypes}
        fitView
        fitViewOptions={fitViewOptions}
        minZoom={0.2}
        maxZoom={1.75}
        panOnDrag={panOnDrag}
        autoPanOnSelection
        onlyRenderVisibleElements
        paneClickDistance={4}
        nodeDragThreshold={3}
        connectionDragThreshold={4}
        deleteKeyCode={null}
        proOptions={proOptions}
        className="bg-gray-50 dark:bg-gray-900"
      >
        <Controls position="bottom-right" showInteractive={false} />
        <div className="absolute left-4 top-4 z-10 flex gap-2">
          <button type="button" onClick={() => openActionPicker()} className="flex items-center gap-2 rounded-lg bg-gray-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"><Plus size={16} /> Add action</button>
          <button
            type="button"
            onClick={() => setShowLayoutConfirm(true)}
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
          >
            {showMiniMap ? <Map size={14} /> : <MapPinOff size={14} />}
          </button>
        </div>
        {showMiniMap && (
          <MiniMap
            position="bottom-right"
            className="!rounded-lg !border !border-gray-200 dark:!border-gray-700 !shadow-sm dark:!bg-gray-800"
            maskColor="rgb(240 240 240 / 0.7)"
          />
        )}
        <Background variant={BackgroundVariant.Dots} gap={22} size={1} />
        {rfNodes.length === 0 && <div className="absolute inset-0 z-10 flex items-center justify-center pointer-events-none">
          <div className="max-w-sm px-6 text-center">
            <span className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-gray-200 bg-white text-blue-500 shadow-sm dark:border-gray-700 dark:bg-gray-800"><Workflow size={26} /></span>
            <h2 className="text-lg font-semibold text-gray-800 dark:text-gray-100">Build your workflow</h2>
            <p className="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">Start with an internal entry point, then add actions and branches to define what happens next.</p>
            <button type="button" onClick={() => openActionPicker()} className="pointer-events-auto mt-5 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700"><Plus size={16} /> Add first step</button>
          </div>
        </div>}
        {rfNodes.length > 0 && <div className="pointer-events-none absolute bottom-5 left-4 text-[11px] text-gray-400">Drag to pan · Scroll to zoom · Click a step to configure</div>}
      </ReactFlow>

      {contextMenu && (
        <ElementContextMenu
          key={`${contextMenu.kind}-${contextMenu.id}`}
          target={contextMenu}
          onConfigure={(nodeId) => {
            selectCanvasNode(nodeId)
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
        open={showLayoutConfirm}
        title="Auto Layout"
        message="This will rearrange all nodes automatically. Your current manual layout will be lost."
        confirmLabel="Rearrange"
        variant="primary"
        onConfirm={async () => {
          setShowLayoutConfirm(false)
          await autoLayout()
          window.requestAnimationFrame(() => fitView({ padding: 0.2 }))
        }}
        onCancel={() => setShowLayoutConfirm(false)}
      />
      <ConfirmDialog
        open={pendingDeletion !== null}
        title="Delete from workflow"
        message={`Delete ${pendingDeletion?.label ?? 'the selected item'}? This cannot be undone.`}
        confirmLabel="Delete"
        onConfirm={async () => {
          const deletion = pendingDeletion
          setPendingDeletion(null)
          if (!deletion) return

          for (const nodeId of deletion.nodeIds) {
            await deleteNode(nodeId)
          }
          for (const edgeId of deletion.edgeIds) {
            await deleteEdge(edgeId)
          }
        }}
        onCancel={() => setPendingDeletion(null)}
      />
    </div>
  )
}
