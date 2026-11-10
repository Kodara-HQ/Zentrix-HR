
import 'dotenv/config'
import { execSync } from 'node:child_process'
import { ensureDatabase, stopDatabase } from './db/ensure.js'

await ensureDatabase()

execSync('npx prisma generate', {
  stdio: 'inherit',
  env: process.env,
})

execSync('npx prisma db push --skip-generate --accept-data-loss', {
  stdio: 'inherit',
  env: process.env,
})

const { prisma } = await import('./lib/prisma.js')
const { hashPassword } = await import('./lib/auth.js')

// TEMPORARY USER PASSWORD RESET
const resetEmail = process.env.RESET_USER_EMAIL
const resetPassword = process.env.RESET_USER_PASSWORD

if (resetEmail && resetPassword) {
  const user = await prisma.user.findFirst({
    where: {
      email: {
        equals: resetEmail,
        mode: 'insensitive',
      },
    },
  })

  if (user) {
    await prisma.user.update({
      where: { id: user.id },
      data: {
        passwordHash: await hashPassword(resetPassword),
        isActive: true,
        mustChangePassword: true,
      },
    })

    console.log('User password reset successfully.')
  } else {
    console.log('Password reset user not found.')
  }
}

const { createApp } = await import('./app.js')
const { runReminders } = await import('./jobs/reminders.js')

await runReminders().catch((error: unknown) => {
  console.error('Reminders failed', error)
})

const port = Number(process.env.PORT || 4000)

const server = createApp().listen(port, () => {
  console.log(`HRIS API listening on http://localhost:${port}`)
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
