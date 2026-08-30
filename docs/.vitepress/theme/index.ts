import DefaultTheme from 'vitepress/theme'
import HomePage from './components/HomePage.vue'
import type { Theme } from 'vitepress'
import './style.css'

export default {
  extends: DefaultTheme,
  enhanceApp({ app }) {
    app.component('HomePage', HomePage)
  },
} satisfies Theme
