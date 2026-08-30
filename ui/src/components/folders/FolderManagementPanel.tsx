import { useState } from 'react'
import {
  Folder,
  Inbox,
  MoreHorizontal,
  Plus,
  X,
} from 'lucide-react'
import type { WorkflowFolder } from '../../api/types'
import {
  folderAndDescendantIds,
  folderPathLabel,
} from '../../lib/folders'
import { ConfirmDialog } from '../shared/ConfirmDialog'
import { FolderTree } from './FolderTree'

type FolderSelection = number | null | 'uncategorized'

type FolderEditor =
  | { mode: 'create'; parentId: number | null; name: string }
  | { mode: 'rename'; folderId: number; name: string }
  | { mode: 'move'; folderId: number; parentId: number | null }

interface FolderManagementPanelProps {
  folders: WorkflowFolder[]
  selectedFolderId: FolderSelection
  total: number
  onSelect: (folderId: FolderSelection) => void
  onCreate: (name: string, parentId?: number | null) => Promise<WorkflowFolder>
  onUpdate: (
    folderId: number,
    data: { name?: string; parent_id?: number | null },
  ) => Promise<WorkflowFolder>
  onDelete: (folderId: number) => Promise<void>
}

export function FolderManagementPanel({
  folders,
  selectedFolderId,
  total,
  onSelect,
  onCreate,
  onUpdate,
  onDelete,
}: FolderManagementPanelProps) {
  const [editor, setEditor] = useState<FolderEditor | null>(null)
  const [menuFolderId, setMenuFolderId] = useState<number | null>(null)
  const [deleteFolder, setDeleteFolder] = useState<WorkflowFolder | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)

  const closeEditor = () => {
    setEditor(null)
    setError(null)
  }

  const saveEditor = async () => {
    if (!editor || saving) return
    setSaving(true)
    setError(null)

    try {
      if (editor.mode === 'create') {
        const name = editor.name.trim()
        if (!name) return
        const created = await onCreate(name, editor.parentId)
        onSelect(created.id)
      } else if (editor.mode === 'rename') {
        const name = editor.name.trim()
        if (!name) return
        await onUpdate(editor.folderId, { name })
      } else {
        await onUpdate(editor.folderId, { parent_id: editor.parentId })
      }
      setEditor(null)
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'The folder could not be saved.')
    } finally {
      setSaving(false)
    }
  }

  const requestDelete = (folder: WorkflowFolder) => {
    setMenuFolderId(null)
    const hasChildren = folders.some((item) => item.parent_id === folder.id)
    if (hasChildren || (folder.workflows_count ?? 0) > 0) {
      setError('Move this folder’s workflows and subfolders before deleting it.')
      return
    }
    setError(null)
    setDeleteFolder(folder)
  }

  const confirmDelete = async () => {
    if (!deleteFolder) return
    setError(null)
    try {
      await onDelete(deleteFolder.id)
      setDeleteFolder(null)
    } catch (caught) {
      setDeleteFolder(null)
      setError(caught instanceof Error ? caught.message : 'The folder could not be deleted.')
    }
  }

  const moveTargets = editor?.mode === 'move'
    ? (() => {
        const excludedIds = folderAndDescendantIds(folders, editor.folderId)
        return folders.filter((folder) => !excludedIds.has(folder.id))
      })()
    : []

  return (
    <div>
      <div className="flex items-center justify-between">
        <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
          Folders
        </h3>
        <button
          type="button"
          onClick={() => {
            setEditor({ mode: 'create', parentId: null, name: '' })
            setMenuFolderId(null)
            setError(null)
          }}
          className="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
          aria-label="Create root folder"
          title="New folder"
        >
          <Plus size={14} />
        </button>
      </div>

      {editor && (
        <div className="mt-2 rounded-md border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800">
          <div className="mb-1 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {editor.mode === 'create' && (
              editor.parentId == null
                ? 'New root folder'
                : 'New folder in ' + folderPathLabel(folders, editor.parentId)
            )}
            {editor.mode === 'rename' && 'Rename folder'}
            {editor.mode === 'move' && 'Move folder'}
          </div>
          {editor.mode === 'move' ? (
            <select
              value={editor.parentId ?? ''}
              onChange={(event) => setEditor({
                ...editor,
                parentId: event.target.value ? Number(event.target.value) : null,
              })}
              className="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
              aria-label="Parent folder"
            >
              <option value="">Root level</option>
              {moveTargets.map((folder) => (
                <option key={folder.id} value={folder.id}>
                  {folderPathLabel(folders, folder.id)}
                </option>
              ))}
            </select>
          ) : (
            <input
              type="text"
              value={editor.name}
              onChange={(event) => setEditor({ ...editor, name: event.target.value })}
              onKeyDown={(event) => {
                if (event.key === 'Enter') saveEditor()
                if (event.key === 'Escape') closeEditor()
              }}
              placeholder="Folder name"
              className="w-full rounded border border-gray-300 bg-white px-2 py-1.5 text-xs dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
              autoFocus
            />
          )}
          <div className="mt-2 flex justify-end gap-1">
            <button
              type="button"
              onClick={closeEditor}
              className="rounded px-2 py-1 text-xs text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700"
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={saveEditor}
              disabled={saving || (editor.mode !== 'move' && !editor.name.trim())}
              className="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {saving ? 'Saving…' : 'Save'}
            </button>
          </div>
        </div>
      )}

      {error && (
        <div className="mt-2 flex items-start gap-1 rounded-md bg-red-50 px-2 py-1.5 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-300" role="alert">
          <span className="min-w-0 flex-1">{error}</span>
          <button type="button" onClick={() => setError(null)} className="shrink-0 rounded p-0.5" aria-label="Dismiss folder error">
            <X size={11} />
          </button>
        </div>
      )}

      <div className="mt-2 space-y-0.5">
        <button
          type="button"
          onClick={() => onSelect(null)}
          className={'flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-left text-sm transition ' + (
            selectedFolderId === null
              ? 'bg-blue-50 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
              : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
          )}
        >
          <Folder size={14} />
          <span>All workflows</span>
          {selectedFolderId === null && total > 0 && (
            <span className="ml-auto text-[10px] text-gray-400">{total}</span>
          )}
        </button>

        <FolderTree
          folders={folders}
          selectedFolderId={typeof selectedFolderId === 'number' ? selectedFolderId : null}
          onSelect={(folderId) => onSelect(folderId)}
          showCounts
          actions={(folder) => (
            <div className="relative shrink-0">
              <button
                type="button"
                onClick={() => setMenuFolderId(menuFolderId === folder.id ? null : folder.id)}
                className="mr-1 rounded p-1 text-gray-400 opacity-60 hover:bg-gray-200 hover:text-gray-700 focus:opacity-100 group-hover/folder:opacity-100 dark:hover:bg-gray-600 dark:hover:text-gray-200"
                aria-label={'Actions for ' + folder.name}
                aria-expanded={menuFolderId === folder.id}
              >
                <MoreHorizontal size={14} />
              </button>
              {menuFolderId === folder.id && (
                <div className="absolute right-1 top-7 z-20 w-36 rounded-md border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-600 dark:bg-gray-800">
                  <button
                    type="button"
                    onClick={() => {
                      setEditor({ mode: 'create', parentId: folder.id, name: '' })
                      setMenuFolderId(null)
                    }}
                    className="w-full rounded px-2 py-1.5 text-left text-xs text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                  >
                    New subfolder
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setEditor({ mode: 'rename', folderId: folder.id, name: folder.name })
                      setMenuFolderId(null)
                    }}
                    className="w-full rounded px-2 py-1.5 text-left text-xs text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                  >
                    Rename
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setEditor({ mode: 'move', folderId: folder.id, parentId: folder.parent_id })
                      setMenuFolderId(null)
                    }}
                    className="w-full rounded px-2 py-1.5 text-left text-xs text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                  >
                    Move…
                  </button>
                  <button
                    type="button"
                    onClick={() => requestDelete(folder)}
                    className="w-full rounded px-2 py-1.5 text-left text-xs text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30"
                  >
                    Delete…
                  </button>
                </div>
              )}
            </div>
          )}
        />

        <button
          type="button"
          onClick={() => onSelect('uncategorized')}
          className={'flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-left text-sm transition ' + (
            selectedFolderId === 'uncategorized'
              ? 'bg-blue-50 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
              : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
          )}
        >
          <Inbox size={14} />
          <span>Unfiled</span>
        </button>
      </div>

      <ConfirmDialog
        open={deleteFolder !== null}
        title="Delete Folder"
        message={'Delete “' + (deleteFolder?.name ?? '') + '”? Only empty folders can be deleted.'}
        onConfirm={confirmDelete}
        onCancel={() => setDeleteFolder(null)}
      />
    </div>
  )
}
