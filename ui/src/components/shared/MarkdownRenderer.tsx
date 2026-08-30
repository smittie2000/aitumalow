import { useMemo } from 'react'
import { marked } from 'marked'

interface Props {
  markdown: string
}

export function MarkdownRenderer({ markdown }: Props) {
  const html = useMemo(() => {
    const result = marked.parse(markdown)
    return typeof result === 'string' ? result : ''
  }, [markdown])

  return (
    <div
      className="node-doc-prose"
      dangerouslySetInnerHTML={{ __html: html }}
    />
  )
}
