import { createContext, useContext } from 'react'
import type { XYPosition } from '@xyflow/react'

export interface ActionSource {
  nodeId: string
  port: string
}

export interface ActionPickerRequest {
  source?: ActionSource
  position?: XYPosition
  triggersOnly?: boolean
  edgeId?: string
}

export const ActionPickerContext = createContext<(request?: ActionPickerRequest) => void>(() => {})
export const useActionPicker = () => useContext(ActionPickerContext)
