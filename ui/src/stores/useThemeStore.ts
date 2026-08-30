import { createStore } from 'zustand/vanilla'

export type Theme = 'light' | 'dark'

const STORAGE_KEY = 'workflow-editor-theme'

function getInitialTheme(): Theme {
  if (typeof localStorage === 'undefined') return 'light'
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'dark' || stored === 'light') return stored
  return 'light'
}

export interface ThemeState {
  theme: Theme
  toggle: () => void
}

export const createThemeStore = () => createStore<ThemeState>((set, get) => ({
  theme: getInitialTheme(),
  toggle: () => {
    const next = get().theme === 'light' ? 'dark' : 'light'
    if (typeof localStorage !== 'undefined') localStorage.setItem(STORAGE_KEY, next)
    set({ theme: next })
  },
}))
