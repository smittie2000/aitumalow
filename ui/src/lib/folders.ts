import type { WorkflowFolder } from '../api/types'

export interface WorkflowFolderTreeNode extends WorkflowFolder {
  children: WorkflowFolderTreeNode[]
}

export function buildFolderTree(folders: WorkflowFolder[]): WorkflowFolderTreeNode[] {
  const nodes = new Map<number, WorkflowFolderTreeNode>(
    folders.map((folder) => [folder.id, { ...folder, children: [] }]),
  )
  const roots: WorkflowFolderTreeNode[] = []

  for (const folder of folders) {
    const node = nodes.get(folder.id)
    if (!node) continue

    const parent = folder.parent_id == null ? undefined : nodes.get(folder.parent_id)
    if (parent) parent.children.push(node)
    else roots.push(node)
  }

  const sortNodes = (items: WorkflowFolderTreeNode[]) => {
    items.sort((left, right) => left.name.localeCompare(right.name))
    for (const item of items) sortNodes(item.children)
  }

  sortNodes(roots)

  return roots
}

export function folderPath(folders: WorkflowFolder[], folderId: number | null): WorkflowFolder[] {
  if (folderId == null) return []

  const byId = new Map(folders.map((folder) => [folder.id, folder]))
  const path: WorkflowFolder[] = []
  const visited = new Set<number>()
  let current = byId.get(folderId)

  while (current && !visited.has(current.id)) {
    visited.add(current.id)
    path.unshift(current)
    current = current.parent_id == null ? undefined : byId.get(current.parent_id)
  }

  return path
}

export function folderPathLabel(
  folders: WorkflowFolder[],
  folderId: number | null,
  emptyLabel = 'Unfiled',
): string {
  const path = folderPath(folders, folderId)

  return path.length > 0 ? path.map((folder) => folder.name).join(' / ') : emptyLabel
}

export function folderAndDescendantIds(folders: WorkflowFolder[], folderId: number): Set<number> {
  const childrenByParent = new Map<number, number[]>()

  for (const folder of folders) {
    if (folder.parent_id == null) continue
    const children = childrenByParent.get(folder.parent_id) ?? []
    children.push(folder.id)
    childrenByParent.set(folder.parent_id, children)
  }

  const ids = new Set<number>()
  const pending = [folderId]

  while (pending.length > 0) {
    const current = pending.pop()
    if (current == null || ids.has(current)) continue
    ids.add(current)
    pending.push(...(childrenByParent.get(current) ?? []))
  }

  return ids
}
