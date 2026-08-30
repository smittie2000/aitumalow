import type { NodeType } from '../../api/types'
import {
  Zap,
  Play,
  GitBranch,
  ArrowRightLeft,
  Settings,
  Wrench,
  Code,
  StickyNote,
  CreditCard,
  MessageCircle,
  Ticket,
  type LucideIcon,
} from 'lucide-react'

export const NODE_TYPE_ICON: Record<NodeType, LucideIcon> = {
  trigger:     Zap,
  action:      Play,
  condition:   GitBranch,
  transformer: ArrowRightLeft,
  control:     Settings,
  utility:     Wrench,
  code:        Code,
  annotation:  StickyNote,
}

const CAPABILITY_ICON: Record<string, LucideIcon> = {
  'credit-card': CreditCard,
  'message-circle': MessageCircle,
  ticket: Ticket,
}

export function capabilityIcon(icon: string, type: NodeType): LucideIcon {
  return CAPABILITY_ICON[icon] ?? NODE_TYPE_ICON[type]
}
