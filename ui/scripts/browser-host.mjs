import { spawn, spawnSync } from 'node:child_process'
import { createServer } from 'node:net'
import { randomUUID } from 'node:crypto'
import { existsSync, writeFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const root = fileURLToPath(new URL('../../', import.meta.url))
// No credentials or external SMTP destination: this capture server only binds loopback.
const smtp = createServer((socket) => {
  let buffer = '', data = null, recipients = []
  socket.setEncoding('utf8')
  socket.write('220 browser-testing ESMTP\r\n')
  socket.on('data', (chunk) => {
    buffer += chunk
    while (buffer.includes('\r\n')) {
      const end = buffer.indexOf('\r\n')
      const line = buffer.slice(0, end)
      buffer = buffer.slice(end + 2)
      if (data !== null) {
        if (line === '.') {
          writeFileSync(`${root}storage/browser-testing/inbox/${randomUUID()}.json`, JSON.stringify({ recipients, data: data.join('\n') }))
          data = null
          socket.write('250 Message captured\r\n')
        } else data.push(line.startsWith('..') ? line.slice(1) : line)
      } else if (/^(EHLO|HELO)/i.test(line)) socket.write('250 browser-testing\r\n')
      else if (/^MAIL FROM:/i.test(line)) { recipients = []; socket.write('250 OK\r\n') }
      else if (/^RCPT TO:/i.test(line)) { recipients.push(line.match(/<([^>]+)>/)?.[1] ?? line.slice(8).trim()); socket.write('250 OK\r\n') }
      else if (/^DATA$/i.test(line)) { data = []; socket.write('354 Send message\r\n') }
      else if (/^QUIT$/i.test(line)) socket.end('221 Bye\r\n')
      else if (/^RSET$/i.test(line)) { data = null; recipients = []; socket.write('250 OK\r\n') }
      else socket.write('250 OK\r\n')
    }
  })
  socket.on('error', () => {})
})

// Refuse port conflicts before resetting this test area's database.
await new Promise((resolve, reject) => { smtp.once('error', reject); smtp.listen(1026, '127.0.0.1', resolve) })
const probe = createServer()
try {
  await new Promise((resolve, reject) => { probe.once('error', reject); probe.listen(8099, '127.0.0.1', resolve) })
  await new Promise((resolve) => probe.close(resolve))
} catch (error) { smtp.close(); throw error }
if (process.argv.includes('--reset') || !existsSync(`${root}storage/browser-testing/database.sqlite`)) {
  const prepare = spawnSync('php', ['tests/Browser/prepare.php'], { cwd: root, stdio: 'inherit' })
  if (prepare.status !== 0) { smtp.close(); process.exit(prepare.status ?? 1) }
}
const children = [
  spawn('php', ['-S', '127.0.0.1:8099', 'tests/Browser/router.php'], { cwd: root, stdio: 'inherit' }),
  spawn('php', ['tests/Browser/artisan.php', 'queue:work', 'database', '--queue=browser-workflows', '--sleep=1', '--tries=1'], { cwd: root, stdio: 'inherit' }),
]
let stopping = false
function stop(code = 0) {
  if (stopping) return
  stopping = true
  for (const child of children) child.kill('SIGTERM')
  smtp.close()
  process.exitCode = code
}
for (const child of children) {
  child.on('error', (error) => { console.error(error); stop(1) })
  child.on('exit', (code) => { if (!stopping) stop(code || 1) })
}
process.on('SIGTERM', () => stop())
process.on('SIGINT', () => stop())
console.log('Test area: http://127.0.0.1:8099 — leads: /testing/leads — inbox: /testing/inbox')
