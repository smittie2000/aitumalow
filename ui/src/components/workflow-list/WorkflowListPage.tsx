import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Plus,
  Upload,
  Copy,
  Trash2,
  ToggleLeft,
  ToggleRight,
  ChevronLeft,
  ChevronRight,
  Sun,
  Moon,
  Search,
  ArrowUpDown,
  Folder,
  FolderOpen,
  Tag,
  X,
  ChevronDown,
  ChevronRight as ChevronRightIcon,
} from 'lucide-react'
import {
  useRegistryStore,
  useThemeStore,
  useWorkflowListStore,
} from '../../stores/EditorRuntimeProvider'
import { LoadingSpinner } from '../shared/LoadingSpinner'
import { ConfirmDialog } from '../shared/ConfirmDialog'
import { ImportWorkflowModal } from './ImportWorkflowModal'
import type { Workflow, WorkflowFolder } from '../../api/types'

const sortOptions = [
  { label: 'Newest first', sort: 'created_at' as const, direction: 'desc' as const },
  { label: 'Oldest first', sort: 'created_at' as const, direction: 'asc' as const },
  { label: 'Recently updated', sort: 'updated_at' as const, direction: 'desc' as const },
  { label: 'Name A-Z', sort: 'name' as const, direction: 'asc' as const },
  { label: 'Name Z-A', sort: 'name' as const, direction: 'desc' as const },
]

export function WorkflowListPage() {
  const navigate = useNavigate()
  const {
    workflows,
    currentPage,
    lastPage,
    total,
    isLoading,
    search,
    sort,
    direction,
    tags,
    folders,
    selectedTagId,
    selectedFolderId,
    fetchWorkflows,
    setSearch,
    setSort,
    setSelectedTagId,
    setSelectedFolderId,
    fetchTags,
    fetchFolders,
    createTag,
    deleteTag,
    createFolder,
    deleteFolder,
    createWorkflow,
    deleteWorkflow,
    duplicateWorkflow,
    toggleActive,
  } = useWorkflowListStore()
  const { theme, toggle: toggleTheme } = useThemeStore()
  const { nodes: registryNodes, fetchRegistry } = useRegistryStore()

  const [showCreate, setShowCreate] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [newName, setNewName] = useState('')
  const [newDesc, setNewDesc] = useState('')
  const [deleteId, setDeleteId] = useState<number | null>(null)
  const [duplicateId, setDuplicateId] = useState<number | null>(null)
  const [searchInput, setSearchInput] = useState(search)
  const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined)
  const [showNewTag, setShowNewTag] = useState(false)
  const [newTagName, setNewTagName] = useState('')
  const [newTagColor, setNewTagColor] = useState('#3B82F6')
  const [showNewFolder, setShowNewFolder] = useState(false)
  const [newFolderName, setNewFolderName] = useState('')
  const [expandedFolders, setExpandedFolders] = useState<Set<number>>(new Set())
  const [sidebarOpen] = useState(true)

  // search/sort/direction are intentionally excluded — setSearch/setSort call fetchWorkflows directly
  useEffect(() => {
    fetchWorkflows()
    fetchTags()
    fetchFolders()
  }, [fetchWorkflows, fetchTags, fetchFolders])

  useEffect(() => {
    return () => clearTimeout(debounceRef.current)
  }, [])

  const handleSearchChange = useCallback((value: string) => {
    setSearchInput(value)
    clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => setSearch(value), 300)
  }, [setSearch])

  const handleCreate = async () => {
    if (!newName.trim()) return
    const wf = await createWorkflow(newName.trim(), newDesc.trim() || undefined)
    setShowCreate(false)
    setNewName('')
    setNewDesc('')
    navigate(`/${wf.id}`)
  }

  const handleDelete = async () => {
    if (deleteId === null) return
    await deleteWorkflow(deleteId)
    setDeleteId(null)
  }

  const handleDuplicate = async () => {
    if (duplicateId === null) return
    await duplicateWorkflow(duplicateId)
    setDuplicateId(null)
  }

  const toggleFolderExpand = (id: number) => {
    setExpandedFolders(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const handleCreateTag = async () => {
    if (!newTagName.trim()) return
    await createTag(newTagName.trim(), newTagColor)
    setNewTagName('')
    setShowNewTag(false)
  }

  const handleCreateFolder = async () => {
    if (!newFolderName.trim()) return
    await createFolder(newFolderName.trim())
    setNewFolderName('')
    setShowNewFolder(false)
  }

  const renderFolderTree = (items: WorkflowFolder[], depth = 0) =>
    items.map((folder) => (
      <div key={folder.id}>
        <div
          className={`flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-sm transition ${
            selectedFolderId === folder.id
              ? 'bg-blue-50 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
              : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
          }`}
          style={{ paddingLeft: `${depth * 16 + 8}px` }}
          onClick={() => setSelectedFolderId(selectedFolderId === folder.id ? null : folder.id)}
        >
          {folder.children && folder.children.length > 0 ? (
            <button
              onClick={(e) => { e.stopPropagation(); toggleFolderExpand(folder.id) }}
              className="shrink-0 p-0.5"
            >
              {expandedFolders.has(folder.id) ? <ChevronDown size={12} /> : <ChevronRightIcon size={12} />}
            </button>
          ) : (
            <span className="w-4" />
          )}
          {selectedFolderId === folder.id ? <FolderOpen size={14} /> : <Folder size={14} />}
          <span className="truncate">{folder.name}</span>
          {folder.workflows_count != null && folder.workflows_count > 0 && (
            <span className="ml-auto shrink-0 rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px] font-medium leading-none text-gray-600 dark:bg-gray-600 dark:text-gray-300">
              {folder.workflows_count}
            </span>
          )}
          <button
            onClick={(e) => { e.stopPropagation(); deleteFolder(folder.id) }}
            className="hidden shrink-0 rounded p-0.5 text-gray-400 hover:text-red-500 group-hover/folder:block"
          >
            <X size={12} />
          </button>
        </div>
        {folder.children && folder.children.length > 0 && expandedFolders.has(folder.id) && (
          renderFolderTree(folder.children, depth + 1)
        )}
      </div>
    ))

  return (
    <div className="mx-auto flex max-w-7xl gap-6 px-4 py-6 md:px-6 md:py-8">
      {/* Sidebar */}
      {sidebarOpen && (
        <div className="hidden w-56 shrink-0 md:block">
          <div className="sticky top-6 space-y-6">
            {/* Folders */}
            <div>
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Folders</h3>
                <button
                  onClick={() => setShowNewFolder(true)}
                  className="rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  title="New folder"
                >
                  <Plus size={14} />
                </button>
              </div>
              {showNewFolder && (
                <div className="mt-2 flex gap-1">
                  <input
                    type="text"
                    value={newFolderName}
                    onChange={(e) => setNewFolderName(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && handleCreateFolder()}
                    placeholder="Folder name"
                    className="w-full rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    autoFocus
                  />
                  <button onClick={handleCreateFolder} className="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">
                    Add
                  </button>
                </div>
              )}
              <div className="mt-2 space-y-0.5">
                <div
                  className={`flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-sm transition ${
                    selectedFolderId === null
                      ? 'bg-blue-50 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                      : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                  }`}
                  onClick={() => setSelectedFolderId(null)}
                >
                  <Folder size={14} />
                  <span>All Workflows</span>
                  {total > 0 && (
                    <span className="ml-auto shrink-0 rounded-full bg-gray-200 px-1.5 py-0.5 text-[10px] font-medium leading-none text-gray-600 dark:bg-gray-600 dark:text-gray-300">
                      {total}
                    </span>
                  )}
                </div>
                {renderFolderTree(folders)}
                <div
                  className={`flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1 text-sm transition ${
                    selectedFolderId === 'uncategorized'
                      ? 'bg-blue-50 font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
                      : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700'
                  }`}
                  onClick={() => setSelectedFolderId(selectedFolderId === 'uncategorized' ? null : 'uncategorized')}
                >
                  <Folder size={14} />
                  <span>Uncategorized</span>
                </div>
              </div>
            </div>

            {/* Tags */}
            <div>
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tags</h3>
                <button
                  onClick={() => setShowNewTag(true)}
                  className="rounded p-0.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                  title="New tag"
                >
                  <Plus size={14} />
                </button>
              </div>
              {showNewTag && (
                <div className="mt-2 space-y-1">
                  <div className="flex gap-1">
                    <input
                      type="color"
                      value={newTagColor}
                      onChange={(e) => setNewTagColor(e.target.value)}
                      className="h-7 w-7 shrink-0 cursor-pointer rounded border border-gray-300 dark:border-gray-600"
                    />
                    <input
                      type="text"
                      value={newTagName}
                      onChange={(e) => setNewTagName(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && handleCreateTag()}
                      placeholder="Tag name"
                      className="w-full rounded border border-gray-300 px-2 py-1 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                      autoFocus
                    />
                    <button onClick={handleCreateTag} className="rounded bg-blue-600 px-2 py-1 text-xs text-white hover:bg-blue-700">
                      Add
                    </button>
                  </div>
                </div>
              )}
              <div className="mt-2 flex flex-wrap gap-1.5">
                {tags.map((tag) => (
                  <button
                    key={tag.id}
                    onClick={() => setSelectedTagId(selectedTagId === tag.id ? null : tag.id)}
                    className={`group/tag inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium transition ${
                      selectedTagId === tag.id
                        ? 'ring-2 ring-blue-400 ring-offset-1 dark:ring-offset-gray-900'
                        : 'hover:opacity-80'
                    }`}
                    style={{
                      backgroundColor: (tag.color ?? '#6B7280') + '20',
                      color: tag.color ?? '#6B7280',
                    }}
                  >
                    <span
                      className="inline-block h-2 w-2 rounded-full"
                      style={{ backgroundColor: tag.color ?? '#6B7280' }}
                    />
                    {tag.name}
                    {tag.workflows_count != null && tag.workflows_count > 0 && (
                      <span className="ml-0.5 rounded-full bg-black/10 px-1.5 py-0.5 text-[10px] font-medium leading-none">
                        {tag.workflows_count}
                      </span>
                    )}
                    <span
                      onClick={(e) => { e.stopPropagation(); deleteTag(tag.id) }}
                      className="ml-0.5 hidden cursor-pointer rounded-full p-0.5 hover:bg-black/10 group-hover/tag:inline-flex"
                    >
                      <X size={10} />
                    </span>
                  </button>
                ))}
                {tags.length === 0 && !showNewTag && (
                  <p className="text-xs text-gray-400 dark:text-gray-500">No tags yet</p>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Main Content */}
      <div className="min-w-0 flex-1">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Workflows</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{total} workflow{total !== 1 ? 's' : ''}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <button
              onClick={toggleTheme}
              className="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
              title={theme === 'light' ? 'Dark mode' : 'Light mode'}
            >
              {theme === 'light' ? <Moon size={16} /> : <Sun size={16} />}
            </button>
            <button
              onClick={async () => {
                await fetchRegistry()
                setShowImport(true)
              }}
              className="flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              <Upload size={16} />
              <span className="hidden sm:inline">Import</span>
            </button>
            <button
              onClick={() => setShowCreate(true)}
              className="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
            >
              <Plus size={16} />
              <span className="hidden sm:inline">New Workflow</span>
            </button>
          </div>
        </div>

        {/* Active Filters */}
        {(selectedTagId || selectedFolderId) && (
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <span className="text-xs text-gray-500 dark:text-gray-400">Filters:</span>
            {selectedFolderId && (
              <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                <Folder size={12} />
                {selectedFolderId === 'uncategorized' ? 'Uncategorized' : folders.find(f => f.id === selectedFolderId)?.name ?? 'Folder'}
                <button onClick={() => setSelectedFolderId(null)} className="ml-0.5 rounded-full p-0.5 hover:bg-gray-200 dark:hover:bg-gray-600">
                  <X size={10} />
                </button>
              </span>
            )}
            {selectedTagId && (() => {
              const tag = tags.find(t => t.id === selectedTagId)
              return tag ? (
                <span
                  className="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                  style={{
                    backgroundColor: (tag.color ?? '#6B7280') + '20',
                    color: tag.color ?? '#6B7280',
                  }}
                >
                  <Tag size={12} />
                  {tag.name}
                  <button onClick={() => setSelectedTagId(null)} className="ml-0.5 rounded-full p-0.5 hover:bg-black/10">
                    <X size={10} />
                  </button>
                </span>
              ) : null
            })()}
          </div>
        )}

        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500" />
            <input
              type="text"
              value={searchInput}
              onChange={(e) => handleSearchChange(e.target.value)}
              placeholder="Search workflows..."
              className="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500"
            />
          </div>
          <div className="relative">
            <div className="flex items-center gap-1.5">
              <ArrowUpDown size={14} className="text-gray-400 dark:text-gray-500" />
              <select
                value={`${sort}:${direction}`}
                onChange={(e) => {
                  const opt = sortOptions.find(o => `${o.sort}:${o.direction}` === e.target.value)
                  if (opt) setSort(opt.sort, opt.direction)
                }}
                className="appearance-none rounded-lg border border-gray-300 bg-white py-2 pl-2 pr-8 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
              >
                {sortOptions.map((opt) => (
                  <option key={`${opt.sort}:${opt.direction}`} value={`${opt.sort}:${opt.direction}`}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        {isLoading ? (
          <div className="mt-16 flex justify-center">
            <LoadingSpinner />
          </div>
        ) : workflows.length === 0 ? (
          <div className="mt-16 text-center text-gray-500 dark:text-gray-400">
            <p className="text-lg">No workflows found</p>
            <p className="mt-1 text-sm">
              {selectedTagId || selectedFolderId
                ? 'Try adjusting your filters.'
                : 'Create your first workflow to get started.'}
            </p>
          </div>
        ) : (
          <>
            <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {workflows.map((wf) => (
                <div
                  key={wf.id}
                  className="group cursor-pointer rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-500"
                  onClick={() => navigate(`/${wf.id}`)}
                >
                  <div className="flex items-start justify-between">
                    <div className="min-w-0 flex-1">
                      <h3 className="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{wf.name}</h3>
                      {wf.description && (
                        <p className="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{wf.description}</p>
                      )}
                    </div>
                    <span
                      className={`ml-2 inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${
                        wf.is_active
                          ? 'bg-green-600 text-white'
                          : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                      }`}
                    >
                      {wf.is_active ? 'Active' : 'Inactive'}
                    </span>
                  </div>

                  {/* Tag badges */}
                  {wf.tags && wf.tags.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1" onClick={(e) => e.stopPropagation()}>
                      {wf.tags.map((tag) => (
                        <span
                          key={tag.id}
                          className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
                          style={{
                            backgroundColor: (tag.color ?? '#6B7280') + '20',
                            color: tag.color ?? '#6B7280',
                          }}
                          onClick={() => setSelectedTagId(selectedTagId === tag.id ? null : tag.id)}
                          title={`Filter by "${tag.name}"`}
                        >
                          <span
                            className="inline-block h-1.5 w-1.5 rounded-full"
                            style={{ backgroundColor: tag.color ?? '#6B7280' }}
                          />
                          {tag.name}
                        </span>
                      ))}
                    </div>
                  )}

                  <div className="mt-3 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
                    <span>Updated {new Date(wf.updated_at).toLocaleDateString()}</span>
                    {wf.folder && (
                      <span className="inline-flex items-center gap-0.5">
                        <Folder size={10} />
                        {wf.folder.name}
                      </span>
                    )}
                  </div>

                  <div
                    className="mt-3 flex gap-1"
                    onClick={(e) => e.stopPropagation()}
                  >
                    <button
                      onClick={() => toggleActive(wf.id, wf.is_active)}
                      className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                      title={wf.is_active ? 'Deactivate' : 'Activate'}
                    >
                      {wf.is_active ? <ToggleRight size={14} /> : <ToggleLeft size={14} />}
                    </button>
                    <button
                      onClick={() => setDuplicateId(wf.id)}
                      className="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                      title="Duplicate"
                    >
                      <Copy size={14} />
                    </button>
                    <button
                      onClick={() => setDeleteId(wf.id)}
                      className="rounded p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:text-gray-500 dark:hover:bg-red-900/30 dark:hover:text-red-400"
                      title="Delete"
                    >
                      <Trash2 size={14} />
                    </button>
                  </div>
                </div>
              ))}
            </div>

            {lastPage > 1 && (
              <div className="mt-6 flex items-center justify-center gap-2">
                <button
                  disabled={currentPage <= 1}
                  onClick={() => fetchWorkflows(currentPage - 1)}
                  className="rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-30 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                  <ChevronLeft size={18} />
                </button>
                <span className="text-sm text-gray-600 dark:text-gray-400">
                  Page {currentPage} of {lastPage}
                </span>
                <button
                  disabled={currentPage >= lastPage}
                  onClick={() => fetchWorkflows(currentPage + 1)}
                  className="rounded p-1 text-gray-500 hover:bg-gray-100 disabled:opacity-30 dark:text-gray-400 dark:hover:bg-gray-700"
                >
                  <ChevronRight size={18} />
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {/* Create Modal */}
      {showCreate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
          <div className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:shadow-2xl dark:shadow-black/40">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">New Workflow</h2>
            <div className="mt-4 space-y-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="My Workflow"
                  autoFocus
                  onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                <input
                  type="text"
                  value={newDesc}
                  onChange={(e) => setNewDesc(e.target.value)}
                  className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="Optional description"
                  onKeyDown={(e) => e.key === 'Enter' && handleCreate()}
                />
              </div>
            </div>
            <div className="mt-5 flex justify-end gap-2">
              <button
                onClick={() => setShowCreate(false)}
                className="rounded-md px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
              >
                Cancel
              </button>
              <button
                onClick={handleCreate}
                disabled={!newName.trim()}
                className="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
              >
                Create
              </button>
            </div>
          </div>
        </div>
      )}

      {showImport && (
        <ImportWorkflowModal
          registryNodes={registryNodes}
          existingWorkflows={workflows}
          onImported={(workflow: Workflow) => {
            setShowImport(false)
            fetchWorkflows()
            navigate(`/${workflow.id}`)
          }}
          onClose={() => setShowImport(false)}
        />
      )}

      <ConfirmDialog
        open={duplicateId !== null}
        title="Duplicate Workflow"
        message={`Create a copy of "${workflows.find((w) => w.id === duplicateId)?.name ?? ''}"? The duplicate will be inactive by default.`}
        confirmLabel="Duplicate"
        variant="primary"
        onConfirm={handleDuplicate}
        onCancel={() => setDuplicateId(null)}
      />

      <ConfirmDialog
        open={deleteId !== null}
        title="Delete Workflow"
        message="This action cannot be undone. All nodes, edges, and run history will be permanently deleted."
        onConfirm={handleDelete}
        onCancel={() => setDeleteId(null)}
      />
    </div>
  )
}
