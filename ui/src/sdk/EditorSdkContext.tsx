/* eslint-disable react-refresh/only-export-components */
import { createContext, useContext, type ReactNode } from 'react'
import type { AitumalowEditorSdk } from './editorSdk'

const EditorSdkContext = createContext<AitumalowEditorSdk | null>(null)

export function EditorSdkProvider({
  sdk,
  children,
}: {
  sdk: AitumalowEditorSdk
  children: ReactNode
}) {
  return <EditorSdkContext.Provider value={sdk}>{children}</EditorSdkContext.Provider>
}

export function useEditorSdk(): AitumalowEditorSdk {
  const sdk = useContext(EditorSdkContext)
  if (!sdk) throw new Error('Aitumalow editor components must be rendered inside EditorRuntimeProvider.')
  return sdk
}
