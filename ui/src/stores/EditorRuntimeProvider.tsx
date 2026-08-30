/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, useState, type ReactNode } from 'react'
import { useStore } from 'zustand'
import type { StoreApi } from 'zustand/vanilla'
import { EditorSdkProvider } from '../sdk/EditorSdkContext'
import type { AitumalowEditorSdk } from '../sdk/editorSdk'
import {
  createWorkflowEditorStore,
  type WorkflowEditorStore,
} from './useWorkflowEditorStore'
import { createRegistryStore, type RegistryStore } from './useRegistryStore'
import { createRunStore, type RunStore } from './useRunStore'
import { createThemeStore, type ThemeState } from './useThemeStore'
import { createWorkflowListStore, type WorkflowListStore } from './useWorkflowListStore'

interface EditorStores {
  workflowEditor: StoreApi<WorkflowEditorStore>
  registry: StoreApi<RegistryStore>
  runs: StoreApi<RunStore>
  theme: StoreApi<ThemeState>
  workflowList: StoreApi<WorkflowListStore>
}

const EditorStoresContext = createContext<EditorStores | null>(null)
const EditorPortalContext = createContext<HTMLElement | null>(null)

function createEditorStores(sdk: AitumalowEditorSdk): EditorStores {
  const workflowEditor = createWorkflowEditorStore(sdk)

  return {
    workflowEditor,
    registry: createRegistryStore(sdk),
    runs: createRunStore(sdk, workflowEditor),
    theme: createThemeStore(),
    workflowList: createWorkflowListStore(sdk),
  }
}

export function EditorRuntimeProvider({
  sdk,
  children,
}: {
  sdk: AitumalowEditorSdk
  children: ReactNode
}) {
  const [stores] = useState(() => createEditorStores(sdk))

  return (
    <EditorSdkProvider sdk={sdk}>
      <EditorStoresContext.Provider value={stores}>
        <EditorBoundary>{children}</EditorBoundary>
      </EditorStoresContext.Provider>
    </EditorSdkProvider>
  )
}

function EditorBoundary({ children }: { children: ReactNode }) {
  const theme = useThemeStore((state) => state.theme)
  const [portalTarget, setPortalTarget] = useState<HTMLDivElement | null>(null)

  return (
    <EditorPortalContext.Provider value={portalTarget}>
      <div className={`h-full w-full ${theme === 'dark' ? 'dark' : ''}`}>
        {children}
        <div ref={setPortalTarget} data-aitumalow-portal />
      </div>
    </EditorPortalContext.Provider>
  )
}

function useEditorStores(): EditorStores {
  const stores = useContext(EditorStoresContext)
  if (!stores) throw new Error('Aitumalow editor components must be rendered inside EditorRuntimeProvider.')
  return stores
}

const identity = <T,>(state: T): T => state

export function useWorkflowEditorStore<T = WorkflowEditorStore>(
  selector: (state: WorkflowEditorStore) => T = identity as (state: WorkflowEditorStore) => T,
): T {
  return useStore(useEditorStores().workflowEditor, selector)
}

export function useWorkflowEditorStoreApi(): StoreApi<WorkflowEditorStore> {
  return useEditorStores().workflowEditor
}

export function useRegistryStore<T = RegistryStore>(
  selector: (state: RegistryStore) => T = identity as (state: RegistryStore) => T,
): T {
  return useStore(useEditorStores().registry, selector)
}

export function useRegistryStoreApi(): StoreApi<RegistryStore> {
  return useEditorStores().registry
}

export function useRunStore<T = RunStore>(
  selector: (state: RunStore) => T = identity as (state: RunStore) => T,
): T {
  return useStore(useEditorStores().runs, selector)
}

export function useThemeStore<T = ThemeState>(
  selector: (state: ThemeState) => T = identity as (state: ThemeState) => T,
): T {
  return useStore(useEditorStores().theme, selector)
}

export function useWorkflowListStore<T = WorkflowListStore>(
  selector: (state: WorkflowListStore) => T = identity as (state: WorkflowListStore) => T,
): T {
  return useStore(useEditorStores().workflowList, selector)
}

export function useEditorPortalTarget(): HTMLElement {
  const target = useContext(EditorPortalContext)
  if (target) return target
  if (typeof document !== 'undefined') return document.body
  throw new Error('The Aitumalow editor portal is unavailable outside a browser.')
}
