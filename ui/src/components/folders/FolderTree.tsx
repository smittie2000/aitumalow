import { useState, type ReactNode } from 'react'
import {
  ChevronDown,
  ChevronRight,
  Folder,
  FolderOpen,
} from 'lucide-react'
import type { WorkflowFolder } from '../../api/types'
import { buildFolderTree, type WorkflowFolderTreeNode } from '../../lib/folders'

interface FolderTreeProps {
  folders: WorkflowFolder[]
  selectedFolderId: number | null
  onSelect: (folderId: number) => void
  actions?: (folder: WorkflowFolder) => ReactNode
  showCounts?: boolean
}

export function FolderTree({
  folders,
  selectedFolderId,
  onSelect,
  actions,
  showCounts = false,
}: FolderTreeProps) {
  const [collapsedFolderIds, setCollapsedFolderIds] = useState<Set<number>>(new Set())
  const tree = buildFolderTree(folders)

  const toggle = (folderId: number) => {
    setCollapsedFolderIds((current) => {
      const next = new Set(current)
      if (next.has(folderId)) next.delete(folderId)
      else next.add(folderId)
      return next
    })
  }

  const renderNodes = (nodes: WorkflowFolderTreeNode[], depth = 0): ReactNode =>
    nodes.map((folder) => {
      const selected = selectedFolderId === folder.id
      const expanded = !collapsedFolderIds.has(folder.id)
      const hasChildren = folder.children.length > 0

      return (
        <div key={folder.id}>
          <div
            className={'group/folder relative flex items-center rounded-md text-sm transition ' + (
              selected
                ? 'bg-blue-50 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
            )}
            style={{ paddingLeft: (depth * 16 + 4) + 'px' }}
          >
            {hasChildren ? (
              <button
                type="button"
                onClick={() => toggle(folder.id)}
                className="flex h-8 w-6 shrink-0 items-center justify-center rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                aria-label={(expanded ? 'Collapse' : 'Expand') + ' ' + folder.name}
                aria-expanded={expanded}
              >
                {expanded ? <ChevronDown size={13} /> : <ChevronRight size={13} />}
              </button>
            ) : (
              <span className="w-6 shrink-0" aria-hidden="true" />
            )}
            <button
              type="button"
              onClick={() => onSelect(folder.id)}
              className="flex min-w-0 flex-1 items-center gap-1.5 py-1.5 text-left"
              title={folder.name}
            >
              {selected ? <FolderOpen size={14} /> : <Folder size={14} />}
              <span className="truncate">{folder.name}</span>
              {showCounts && folder.workflows_count != null && folder.workflows_count > 0 && (
                <span className="ml-auto shrink-0 text-[10px] text-gray-400 dark:text-gray-500">
                  {folder.workflows_count}
                </span>
              )}
            </button>
            {actions?.(folder)}
          </div>
          {hasChildren && expanded && renderNodes(folder.children, depth + 1)}
        </div>
      )
    })

  return <div className="space-y-0.5">{renderNodes(tree)}</div>
}
