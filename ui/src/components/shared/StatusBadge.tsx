import { RUN_STATUS_COLORS, NODE_RUN_STATUS_COLORS } from '../../lib/constants'
import type { RunStatus, NodeRunStatus } from '../../api/types'

export function RunStatusBadge({ status }: { status: RunStatus }) {
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${RUN_STATUS_COLORS[status]}`}>
      {status}
    </span>
  )
}

export function NodeRunStatusBadge({ status }: { status: NodeRunStatus }) {
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${NODE_RUN_STATUS_COLORS[status]}`}>
      {status}
    </span>
  )
}
