import assert from 'node:assert/strict'
import test from 'node:test'
import type { WorkflowFolder } from '../src/api/types.ts'
import {
  buildFolderTree,
  folderAndDescendantIds,
  folderPathLabel,
} from '../src/lib/folders.ts'

const folder = (
  id: number,
  name: string,
  parentId: number | null = null,
): WorkflowFolder => ({
  id,
  name,
  parent_id: parentId,
  created_at: '2026-08-30T00:00:00.000Z',
  updated_at: '2026-08-30T00:00:00.000Z',
})

const folders = [
  folder(3, 'Onboarding', 2),
  folder(1, 'Sales'),
  folder(2, 'Operations'),
  folder(4, 'Approvals', 2),
]

test('buildFolderTree creates a sorted hierarchy from the flat API response', () => {
  const tree = buildFolderTree(folders)

  assert.deepEqual(tree.map((item) => item.name), ['Operations', 'Sales'])
  assert.deepEqual(tree[0]?.children.map((item) => item.name), ['Approvals', 'Onboarding'])
})

test('folderPathLabel disambiguates nested folders', () => {
  assert.equal(folderPathLabel(folders, 3), 'Operations / Onboarding')
  assert.equal(folderPathLabel(folders, null), 'Unfiled')
})

test('folderAndDescendantIds protects move targets from cycles', () => {
  assert.deepEqual([...folderAndDescendantIds(folders, 2)].sort(), [2, 3, 4])
})
