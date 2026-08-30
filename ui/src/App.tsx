import { BrowserRouter, Routes, Route, useNavigate, useParams } from 'react-router-dom'
import { WorkflowListPage } from './components/workflow-list/WorkflowListPage'
import { WorkflowEditorPage } from './components/editor/WorkflowEditorPage'
import { createEditorSdk } from './sdk/editorSdk'
import { EditorRuntimeProvider } from './stores/EditorRuntimeProvider'

const sdk = createEditorSdk()

function RoutedWorkflowEditor() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const workflowId = Number(id)

  if (!Number.isInteger(workflowId) || workflowId < 1) return null

  return (
    <WorkflowEditorPage
      workflowId={workflowId}
      onExit={() => navigate('/')}
      onOpenWorkflow={(nextWorkflowId) => navigate(`/${nextWorkflowId}`)}
    />
  )
}

export default function App() {
  return (
    <EditorRuntimeProvider sdk={sdk}>
      <BrowserRouter basename={import.meta.env.BASE_URL}>
        <Routes>
          <Route path="/" element={<WorkflowListPage />} />
          <Route path="/:id" element={<RoutedWorkflowEditor />} />
        </Routes>
      </BrowserRouter>
    </EditorRuntimeProvider>
  )
}
