import { createStore } from 'zustand/vanilla'
import type { Workflow, WorkflowTag, WorkflowFolder } from '../api/types'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'

export interface WorkflowListStore {
  workflows: Workflow[]
  currentPage: number
  lastPage: number
  total: number
  isLoading: boolean
  search: string
  sort: 'name' | 'created_at' | 'updated_at'
  direction: 'asc' | 'desc'

  // Tags & Folders
  tags: WorkflowTag[]
  folders: WorkflowFolder[]
  selectedTagId: number | null
  selectedFolderId: number | null | 'uncategorized'

  fetchWorkflows: (page?: number) => Promise<void>
  setSearch: (search: string) => void
  setSort: (sort: WorkflowListStore['sort'], direction: WorkflowListStore['direction']) => void
  setSelectedTagId: (id: number | null) => void
  setSelectedFolderId: (id: number | null | 'uncategorized') => void
  fetchTags: () => Promise<void>
  fetchFolders: () => Promise<void>
  createTag: (name: string, color?: string) => Promise<WorkflowTag>
  deleteTag: (id: number) => Promise<void>
  createFolder: (name: string, parentId?: number | null) => Promise<WorkflowFolder>
  deleteFolder: (id: number) => Promise<void>
  createWorkflow: (name: string, description?: string) => Promise<Workflow>
  deleteWorkflow: (id: number) => Promise<void>
  duplicateWorkflow: (id: number) => Promise<void>
  toggleActive: (id: number, currentlyActive: boolean) => Promise<void>
}

export const createWorkflowListStore = (sdk: AitumalowEditorSdk) => createStore<WorkflowListStore>((set, get) => ({
  workflows: [],
  currentPage: 1,
  lastPage: 1,
  total: 0,
  isLoading: false,
  search: '',
  sort: 'created_at',
  direction: 'desc',
  tags: [],
  folders: [],
  selectedTagId: null,
  selectedFolderId: null,

  fetchWorkflows: async (page = 1) => {
    const { search, sort, direction, selectedTagId, selectedFolderId } = get()
    set({ isLoading: true })
    try {
      const res = await sdk.workflows.list({
        page,
        search: search || undefined,
        sort,
        direction,
        tag_id: selectedTagId ?? undefined,
        folder_id: selectedFolderId === 'uncategorized' ? undefined : (selectedFolderId ?? undefined),
        uncategorized: selectedFolderId === 'uncategorized' ? true : undefined,
      })
      set({
        workflows: res.data,
        currentPage: res.meta.current_page,
        lastPage: res.meta.last_page,
        total: res.meta.total,
      })
    } finally {
      set({ isLoading: false })
    }
  },

  setSearch: (search) => {
    set({ search })
    get().fetchWorkflows(1)
  },

  setSort: (sort, direction) => {
    set({ sort, direction })
    get().fetchWorkflows(1)
  },

  setSelectedTagId: (id) => {
    set({ selectedTagId: id })
    get().fetchWorkflows(1)
  },

  setSelectedFolderId: (id) => {
    set({ selectedFolderId: id })
    get().fetchWorkflows(1)
  },

  fetchTags: async () => {
    const res = await sdk.tags.list()
    set({ tags: res.data })
  },

  fetchFolders: async () => {
    const res = await sdk.folders.list(true)
    set({ folders: res.data })
  },

  createTag: async (name, color) => {
    const res = await sdk.tags.create({ name, color })
    await get().fetchTags()
    return res.data
  },

  deleteTag: async (id) => {
    await sdk.tags.destroy(id)
    if (get().selectedTagId === id) set({ selectedTagId: null })
    await get().fetchTags()
    await get().fetchWorkflows(1)
  },

  createFolder: async (name, parentId) => {
    const res = await sdk.folders.create({ name, parent_id: parentId })
    await get().fetchFolders()
    return res.data
  },

  deleteFolder: async (id) => {
    await sdk.folders.destroy(id)
    if (get().selectedFolderId === id) set({ selectedFolderId: null })
    await get().fetchFolders()
    await get().fetchWorkflows(1)
  },

  createWorkflow: async (name, description) => {
    const res = await sdk.workflows.create({ name, description, created_via: 'editor' })
    await get().fetchWorkflows(get().currentPage)
    return res.data
  },

  deleteWorkflow: async (id) => {
    await sdk.workflows.destroy(id)
    await get().fetchWorkflows(get().currentPage)
  },

  duplicateWorkflow: async (id) => {
    await sdk.workflows.duplicate(id)
    await get().fetchWorkflows(get().currentPage)
  },

  toggleActive: async (id, currentlyActive) => {
    if (currentlyActive) {
      await sdk.workflows.deactivate(id)
    } else {
      await sdk.workflows.activate(id)
    }
    await get().fetchWorkflows(get().currentPage)
  },
}))
