import { useEffect, useState } from 'react'
import { ChevronDown, ChevronRight, Copy, Variable, Braces, FunctionSquare } from 'lucide-react'
import { useEditorSdk } from '../../sdk/EditorSdkContext'
import type { AvailableVariablesResponse, AvailableVariable, UpstreamNode, AvailableFunction } from '../../api/types'

interface Props {
  workflowId?: number
  nodeId?: number
  data?: AvailableVariablesResponse | null
  onInsert: (expression: string) => void
}

export function VariablePanel({ workflowId, nodeId, data: externalData, onInsert }: Props) {
  const { nodes } = useEditorSdk()
  const [internalData, setInternalData] = useState<AvailableVariablesResponse | null>(null)
  const [isLoading, setIsLoading] = useState(
    externalData === undefined && Boolean(workflowId && nodeId),
  )
  const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>({ globals: true, nodes: true })

  const data = externalData ?? internalData

  useEffect(() => {
    if (externalData !== undefined) return
    if (!workflowId || !nodeId) return
    nodes.availableVariables(workflowId, nodeId)
      .then(setInternalData)
      .catch(() => setInternalData(null))
      .finally(() => setIsLoading(false))
  }, [workflowId, nodeId, externalData, nodes])

  const toggleSection = (key: string) => {
    setExpandedSections((prev) => ({ ...prev, [key]: !prev[key] }))
  }

  const handleInsert = (path: string) => {
    onInsert(`{{ ${path} }}`)
  }

  const handleInsertFunction = (fn: AvailableFunction) => {
    onInsert(`{{ ${fn.name}(${fn.args}) }}`)
  }

  if (isLoading) {
    return (
      <div className="py-3 text-center text-xs text-gray-400 dark:text-gray-500">
        Loading variables...
      </div>
    )
  }

  if (!data) return null

  return (
    <div className="space-y-1">
      <div className="flex items-center gap-1 px-1 text-xs font-semibold text-gray-600 dark:text-gray-400">
        <Variable size={12} />
        Available Variables
      </div>

      {/* Globals */}
      <CollapsibleSection
        title="Globals"
        icon={<Braces size={11} />}
        expanded={expandedSections.globals}
        onToggle={() => toggleSection('globals')}
      >
        {data.globals.map((v) => (
          <VariableItem key={v.path} variable={v} onInsert={handleInsert} />
        ))}
      </CollapsibleSection>

      {/* Upstream Nodes */}
      {data.nodes.length > 0 && (
        <CollapsibleSection
          title="Upstream Nodes"
          icon={<Braces size={11} />}
          expanded={expandedSections.nodes}
          onToggle={() => toggleSection('nodes')}
        >
          {data.nodes.map((node) => (
            <NodeVariableGroup key={node.node_id} node={node} onInsert={handleInsert} />
          ))}
        </CollapsibleSection>
      )}

      {/* Functions */}
      <CollapsibleSection
        title="Functions"
        icon={<FunctionSquare size={11} />}
        expanded={expandedSections.functions ?? false}
        onToggle={() => toggleSection('functions')}
      >
        {data.functions.map((fn) => (
          <button
            type="button"
            key={fn.name}
            onClick={() => handleInsertFunction(fn)}
            className="flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-[11px] hover:bg-gray-100 dark:hover:bg-gray-700"
            title={`${fn.name}(${fn.args})`}
          >
            <span className="font-mono text-purple-600 dark:text-purple-400">{fn.name}</span>
            <span className="truncate text-gray-400 dark:text-gray-500">({fn.args})</span>
          </button>
        ))}
      </CollapsibleSection>
    </div>
  )
}

function CollapsibleSection({
  title,
  icon,
  expanded,
  onToggle,
  children,
}: {
  title: string
  icon: React.ReactNode
  expanded: boolean
  onToggle: () => void
  children: React.ReactNode
}) {
  return (
    <div>
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full items-center gap-1 rounded px-1 py-1 text-[11px] font-medium text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
      >
        {expanded ? <ChevronDown size={12} /> : <ChevronRight size={12} />}
        {icon}
        {title}
      </button>
      {expanded && <div className="ml-2">{children}</div>}
    </div>
  )
}

function NodeVariableGroup({ node, onInsert }: { node: UpstreamNode; onInsert: (path: string) => void }) {
  const [expanded, setExpanded] = useState(true)

  if (node.variables.length === 0) return null

  return (
    <div>
      <button
        type="button"
        onClick={() => setExpanded(!expanded)}
        className="flex w-full items-center gap-1 rounded px-1 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
      >
        {expanded ? <ChevronDown size={10} /> : <ChevronRight size={10} />}
        <span className="truncate">{node.node_name}</span>
        <span className="ml-auto text-[10px] text-gray-400">{node.node_key}</span>
      </button>
      {expanded && (
        <div className="ml-2">
          {node.variables.map((v) => (
            <VariableItem key={v.path} variable={v} onInsert={onInsert} />
          ))}
        </div>
      )}
    </div>
  )
}

function VariableItem({ variable, onInsert }: { variable: AvailableVariable; onInsert: (path: string) => void }) {
  return (
    <button
      type="button"
      onClick={() => onInsert(variable.path)}
      className="group flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-[11px] hover:bg-gray-100 dark:hover:bg-gray-700"
      title={`{{ ${variable.path} }}`}
    >
      <span className="font-mono text-amber-600 dark:text-amber-400">{variable.path}</span>
      <span className="ml-auto hidden text-[10px] text-gray-400 group-hover:inline">
        <Copy size={10} />
      </span>
    </button>
  )
}
