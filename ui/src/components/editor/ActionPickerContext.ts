import { createContext, useContext } from 'react'

export interface ActionSource {
  nodeId: string
  port: string
}

export const ActionPickerContext = createContext<(source?: ActionSource) => void>(() => {})
export const useActionPicker = () => useContext(ActionPickerContext)
