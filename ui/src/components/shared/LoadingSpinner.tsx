import { Loader2 } from 'lucide-react'

export function LoadingSpinner({ className = '' }: { className?: string }) {
  return <Loader2 className={`animate-spin text-gray-400 dark:text-gray-500 ${className}`} size={24} />
}
