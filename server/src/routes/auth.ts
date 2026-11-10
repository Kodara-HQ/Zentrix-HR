import { Router } from 'express'
import { z } from 'zod'
import { prisma } from '../lib/prisma.js'
import { asyncHandler, HttpError, parseBody } from '../lib/http.js'
import { authCookieOptions, hashPassword, setAuthCookie, verifyPassword } from '../lib/auth.js'
import { requireUser } from '../lib/access.js'
import { audit } from '../lib/audit.js'

const router = Router()

const passwordRule = z.string().min(8).regex(/[A-Za-z]/, 'Include a letter.').regex(/[0-9]/, 'Include a number.')

router.post('/login', asyncHandler(async (req, res) => {
  const body = parseBody(z.object({
    email: z.string().trim().min(1),
    password: z.string().min(1),
  }), req.body)
  const key = body.email.toLowerCase()
  const user = await prisma.user.findFirst({
    where: {
      OR: [
        { email: { equals: key, mode: 'insensitive' } },
        { username: { equals: body.email.trim(), mode: 'insensitive' } },
      ],
    },
  })
  if (!user || !user.isActive || !(await verifyPassword(body.password, user.passwordHash))) {
    throw new HttpError(401, 'Email or password is incorrect.')
  }
  await prisma.user.update({ where: { id: user.id }, data: { lastLoginAt: new Date() } })
  setAuthCookie(res, user.id)
  await audit(req, 'LOGIN', 'User', user.id, `${user.username || user.email} signed in`)
  res.json({
    id: user.id,
    email: user.email,
    role: user.role,
    firstName: user.firstName,
    lastName: user.lastName,
    employeeId: user.employeeId,
    departmentId: user.departmentId,
    mustChangePassword: user.mustChangePassword,
  })
}))

router.post('/logout', (_req, res) => {
  const { maxAge: _maxAge, ...cookie } = authCookieOptions()
  res.clearCookie('token', cookie)
  res.json({ ok: true })
})

router.get('/me', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  res.json(user)
}))

router.post('/change-password', asyncHandler(async (req, res) => {
  const user = requireUser(req)
  const body = parseBody(z.object({
    currentPassword: z.string().min(1),
    newPassword: passwordRule,
  }), req.body)
  const record = await prisma.user.findUnique({ where: { id: user.id } })
  if (!record || !(await verifyPassword(body.currentPassword, record.passwordHash))) {
    throw new HttpError(400, 'Current password is incorrect.')
  }
  await prisma.user.update({
    where: { id: user.id },
    data: { passwordHash: await hashPassword(body.newPassword), mustChangePassword: false },
  })
  await audit(req, 'UPDATE', 'User', user.id, 'Password changed')
  res.json({ ok: true })
}))

router.get('/demo', asyncHandler(async (_req, res) => {
  if (process.env.DEMO_MODE !== 'true') {
    res.json({ enabled: false, accounts: [] })
    return
  }
  const users = await prisma.user.findMany({
    where: { isActive: true },
    select: { email: true, role: true, firstName: true, lastName: true },
    orderBy: { role: 'asc' },
  })
  res.json({ enabled: true, password: 'Hris@2026', accounts: users })
}))

export default router
