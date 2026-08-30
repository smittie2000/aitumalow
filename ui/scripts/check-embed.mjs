import { readFile } from 'node:fs/promises'

const assetUrl = new URL('../dist/embed/aitumalow-editor.js', import.meta.url)
const asset = await readFile(assetUrl, 'utf8')

if (/\bprocess\.env\.NODE_ENV\b/.test(asset)) {
  throw new Error('The editor bundle contains an unresolved process.env.NODE_ENV reference.')
}

if (!/\bAitumalowEditor\b/.test(asset)) {
  throw new Error('The editor bundle does not expose the AitumalowEditor global.')
}
