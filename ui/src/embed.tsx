import { createRoot, type Root } from 'react-dom/client'
import { WorkflowEditorPage } from './components/editor/WorkflowEditorPage'
import { createEditorSdk, type AitumalowEditorSdk, type CreateEditorSdkOptions } from './sdk/editorSdk'
import { EditorRuntimeProvider } from './stores/EditorRuntimeProvider'
import './index.css'

export interface AitumalowEditorOptions extends CreateEditorSdkOptions {
  workflowId: number
  sdk?: AitumalowEditorSdk
  onExit?: () => void
  onOpenWorkflow?: (workflowId: number) => void
}

export interface MountedAitumalowEditor {
  unmount: () => void
}

export function mountAitumalowEditor(
  target: HTMLElement,
  options: AitumalowEditorOptions,
): MountedAitumalowEditor {
  const { workflowId, sdk: injectedSdk, onExit, onOpenWorkflow, ...sdkOptions } = options

  if (!Number.isInteger(workflowId) || workflowId < 1) {
    throw new TypeError('workflowId must be a positive integer.')
  }

  const sdk = injectedSdk ?? createEditorSdk(sdkOptions)
  const root: Root = createRoot(target)

  root.render(
    <EditorRuntimeProvider sdk={sdk}>
      <WorkflowEditorPage
        workflowId={workflowId}
        onExit={onExit}
        onOpenWorkflow={onOpenWorkflow}
      />
    </EditorRuntimeProvider>,
  )

  return { unmount: () => root.unmount() }
}

export { createEditorSdk }
export type {
  AitumalowEditorSdk,
  CreateEditorSdkOptions,
  HttpTransport,
  HttpTransportOptions,
  CapabilityDefinition,
} from './sdk'
