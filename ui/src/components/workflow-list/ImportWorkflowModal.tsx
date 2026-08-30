import { useState, useCallback, useMemo, useRef } from 'react'
import { createPortal } from 'react-dom'
import { Upload, AlertCircle, AlertTriangle, CheckCircle2 } from 'lucide-react'
import type { ExportedWorkflow } from '../../lib/exportWorkflow'
import type { Workflow, CapabilityDefinition } from '../../api/types'
import {
  validateWorkflowJson,
  checkRegistryCompatibility,
  checkNameConflict,
  importWorkflow,
  type ImportIssue,
} from '../../lib/importWorkflow'
import { useEditorSdk } from '../../sdk/EditorSdkContext'
import { useEditorPortalTarget } from '../../stores/EditorRuntimeProvider'

interface ImportWorkflowModalProps {
  registryNodes: CapabilityDefinition[]
  existingWorkflows: Workflow[]
  onImported: (workflow: Workflow) => void
  onClose: () => void
}

export function ImportWorkflowModal({
  registryNodes,
  existingWorkflows,
  onImported,
  onClose,
}: ImportWorkflowModalProps) {
  const sdk = useEditorSdk()
  const portalTarget = useEditorPortalTarget()
  const [parsedData, setParsedData] = useState<ExportedWorkflow | null>(null)
  const [structuralIssues, setStructuralIssues] = useState<ImportIssue[]>([])
  const [importName, setImportName] = useState('')
  const [parseError, setParseError] = useState<string | null>(null)
  const [isImporting, setIsImporting] = useState(false)
  const [importError, setImportError] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const issues = useMemo(() => {
    if (structuralIssues.length > 0 || !parsedData || !importName.trim()) {
      return structuralIssues
    }

    return [
      ...checkRegistryCompatibility(parsedData, registryNodes),
      ...checkNameConflict(importName.trim(), existingWorkflows),
    ]
  }, [existingWorkflows, importName, parsedData, registryNodes, structuralIssues])
  const errors = issues.filter((i) => i.type === 'error')
  const warnings = issues.filter((i) => i.type === 'warning')
  const hasErrors = errors.length > 0

  const handleFileChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0]
      if (!file) return

      setParseError(null)
      setImportError(null)
      setStructuralIssues([])
      setParsedData(null)

      const reader = new FileReader()
      reader.onload = () => {
        try {
          const json = JSON.parse(reader.result as string)

          // Structural validation
          const structIssues = validateWorkflowJson(json)
          if (structIssues.some((i) => i.type === 'error')) {
            setStructuralIssues(structIssues)
            return
          }

          const data = json as ExportedWorkflow
          setParsedData(data)
          setImportName(data.name)
        } catch {
          setParseError('Invalid JSON file. Please select a valid .workflow.json file.')
        }
      }
      reader.readAsText(file)
    },
    [],
  )

  const handleImport = useCallback(async () => {
    if (!parsedData || hasErrors || !importName.trim()) return

    setIsImporting(true)
    setImportError(null)

    try {
      const workflow = await importWorkflow(sdk, parsedData, importName.trim())
      onImported(workflow)
    } catch (err) {
      setImportError(err instanceof Error ? err.message : 'Import failed. Please try again.')
    } finally {
      setIsImporting(false)
    }
  }, [sdk, parsedData, hasErrors, importName, onImported])

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800 dark:shadow-2xl dark:shadow-black/40">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Import Workflow</h2>
        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Select a .workflow.json file exported from this system.
        </p>

        {/* File Input */}
        <div className="mt-4">
          <div
            onClick={() => fileInputRef.current?.click()}
            className="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed border-gray-300 px-6 py-8 transition hover:border-blue-400 dark:border-gray-600 dark:hover:border-blue-500"
          >
            <Upload size={24} className="text-gray-400" />
            <span className="text-sm text-gray-600 dark:text-gray-400">
              {parsedData ? `Selected: ${parsedData.name}` : 'Click to select a .workflow.json file'}
            </span>
          </div>
          <input
            ref={fileInputRef}
            type="file"
            accept=".json"
            onChange={handleFileChange}
            className="hidden"
          />
        </div>

        {/* Parse Error */}
        {parseError && (
          <div className="mt-3 flex items-start gap-2 rounded-md bg-red-50 px-3 py-2 dark:bg-red-900/20">
            <AlertCircle size={14} className="mt-0.5 shrink-0 text-red-500" />
            <span className="text-xs text-red-700 dark:text-red-400">{parseError}</span>
          </div>
        )}

        {/* Validation Results */}
        {parsedData && !parseError && (
          <div className="mt-4 space-y-3">
            {/* Name input */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Workflow Name
              </label>
              <input
                type="text"
                value={importName}
                onChange={(e) => setImportName(e.target.value)}
                className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
              />
            </div>

            {/* Summary */}
            <div className="text-xs text-gray-500 dark:text-gray-400">
              {parsedData.nodes.length} node{parsedData.nodes.length !== 1 ? 's' : ''},{' '}
              {parsedData.edges.length} edge{parsedData.edges.length !== 1 ? 's' : ''}
            </div>

            {/* Errors */}
            {errors.length > 0 && (
              <div className="max-h-32 space-y-1 overflow-y-auto rounded-md bg-red-50 p-3 dark:bg-red-900/20">
                {errors.map((issue, i) => (
                  <div key={i} className="flex items-start gap-2">
                    <AlertCircle size={12} className="mt-0.5 shrink-0 text-red-500" />
                    <span className="text-xs text-red-700 dark:text-red-400">{issue.message}</span>
                  </div>
                ))}
              </div>
            )}

            {/* Warnings */}
            {warnings.length > 0 && (
              <div className="max-h-32 space-y-1 overflow-y-auto rounded-md bg-amber-50 p-3 dark:bg-amber-900/20">
                {warnings.map((issue, i) => (
                  <div key={i} className="flex items-start gap-2">
                    <AlertTriangle size={12} className="mt-0.5 shrink-0 text-amber-500" />
                    <span className="text-xs text-amber-700 dark:text-amber-400">{issue.message}</span>
                  </div>
                ))}
              </div>
            )}

            {/* Ready */}
            {issues.length === 0 && (
              <div className="flex items-center gap-2 rounded-md bg-green-50 px-3 py-2 dark:bg-green-900/20">
                <CheckCircle2 size={14} className="text-green-500" />
                <span className="text-xs text-green-700 dark:text-green-400">Ready to import</span>
              </div>
            )}
          </div>
        )}

        {/* Import Error */}
        {importError && (
          <div className="mt-3 flex items-start gap-2 rounded-md bg-red-50 px-3 py-2 dark:bg-red-900/20">
            <AlertCircle size={14} className="mt-0.5 shrink-0 text-red-500" />
            <span className="text-xs text-red-700 dark:text-red-400">{importError}</span>
          </div>
        )}

        {/* Actions */}
        <div className="mt-5 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={isImporting}
            className="rounded-md px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleImport}
            disabled={!parsedData || hasErrors || !importName.trim() || isImporting}
            className="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {isImporting ? 'Importing...' : 'Import'}
          </button>
        </div>
      </div>
    </div>,
    portalTarget,
  )
}
