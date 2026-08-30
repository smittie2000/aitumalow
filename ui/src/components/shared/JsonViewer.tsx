interface JsonViewerProps {
  data: unknown
  maxHeight?: string
}

export function JsonViewer({ data, maxHeight = '300px' }: JsonViewerProps) {
  return (
    <pre
      className="overflow-auto rounded-md bg-gray-900 p-3 text-xs text-green-300"
      style={{ maxHeight }}
    >
      {JSON.stringify(data, null, 2)}
    </pre>
  )
}
