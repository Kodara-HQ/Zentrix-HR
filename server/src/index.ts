import 'dotenv/config'
import { execSync } from 'node:child_process'
import { ensureDatabase, stopDatabase } from './db/ensure.js'

await ensureDatabase()
execSync('npx prisma generate', { stdio: 'inherit', env: process.env })
execSync('npx prisma db push --skip-generate --accept-data-loss', {
  stdio: 'inherit',
  env: process.env,
})

const { prisma } = await import('./lib/prisma.js')

const { createApp } = await import('./app.js')
const { runReminders } = await import('./jobs/reminders.js')
await runReminders().catch((error: unknown) => console.error('Reminders failed', error))

const port = Number(process.env.PORT || 4000)
const server = createApp().listen(port, () => {
  console.log(`HRIS API listening on http://localhost:${port}`)
})

setInterval(() => {
  runReminders().catch((error: unknown) => console.error('Reminders failed', error))
}, 60 * 60 * 1000)

async function shutdown() {
  server.close()
  await prisma.$disconnect()
  await stopDatabase()
  process.exit(0)
}

process.on('SIGINT', () => { void shutdown() })
process.on('SIGTERM', () => { void shutdown() })
