
import 'dotenv/config'
import { exec, execSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { ensureDatabase, stopDatabase } from './db/ensure.js'

const hosted = Boolean(process.env.DATABASE_URL && !/localhost|127\.0\.0\.1/.test(process.env.DATABASE_URL))

await ensureDatabase()

const clientReady = ['node_modules/.prisma/client/index.js', '../node_modules/.prisma/client/index.js'].some((file) => existsSync(file))
if (!hosted || !clientReady) {
  execSync('npx prisma generate', { stdio: 'inherit', env: process.env })
}
if (!hosted) {
  execSync('npx prisma db push --skip-generate --accept-data-loss', { stdio: 'inherit', env: process.env })
}

const { prisma } = await import('./lib/prisma.js')
const { hashPassword } = await import('./lib/auth.js')

const resetEmail = process.env.RESET_USER_EMAIL
const resetPassword = process.env.RESET_USER_PASSWORD
if (resetEmail && resetPassword) {
  const passwordHash = await hashPassword(resetPassword)
  const existing = await prisma.user.findFirst({
    where: { email: { equals: resetEmail, mode: 'insensitive' } },
  })
  if (existing) {
    await prisma.user.update({
      where: { id: existing.id },
      data: { passwordHash, isActive: true, mustChangePassword: false },
    })
  } else {
    const [firstName, ...rest] = resetEmail.split('@')[0].split('.')
    await prisma.user.create({
      data: {
        email: resetEmail,
        passwordHash,
        role: 'SUPER_ADMIN',
        firstName: firstName || 'Admin',
        lastName: rest.join(' ') || 'User',
        isActive: true,
        mustChangePassword: false,
      },
    })
  }
  console.log('Sign-in password was reset.')
}

const { createApp } = await import('./app.js')
const { runReminders } = await import('./jobs/reminders.js')

function startBackgroundWork() {
  const reminders = () => runReminders().catch((error: unknown) => console.error('Reminders failed', error))
  if (!hosted) {
    reminders()
    return
  }
  exec('npx prisma db push --skip-generate --accept-data-loss', { env: process.env }, (error) => {
    if (error) console.error('Schema update failed', error)
    reminders()
  })
}

const port = Number(process.env.PORT || 4000)

const server = createApp().listen(port, () => {
  console.log(`HRIS API listening on http://localhost:${port}`)
  startBackgroundWork()
})

setInterval(() => {
  runReminders().catch((error: unknown) => {
    console.error('Reminders failed', error)
  })
}, 60 * 60 * 1000)

async function shutdown() {
  server.close()
  await prisma.$disconnect()
  await stopDatabase()
  process.exit(0)
}

process.on('SIGINT', () => {
  void shutdown()
})

process.on('SIGTERM', () => {
  void shutdown()
})
