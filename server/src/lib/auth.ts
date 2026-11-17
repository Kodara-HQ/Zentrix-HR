import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'
import type { NextFunction, Request, Response } from 'express'
import { prisma } from './prisma.js'
import type { AuthUser } from './access.js'

const HOURS = 12

function secret() {
  const value = process.env.JWT_SECRET
  if (value) return value
  if (process.env.NODE_ENV === 'production') return 'zentrix-hris-hosted-session'
  throw new Error('JWT_SECRET is not set')
}

export function signToken(userId: string) {
  return jwt.sign({ sub: userId }, secret(), { expiresIn: `${HOURS}h` })
}

export function authCookieOptions() {
  const production = process.env.NODE_ENV === 'production'
  return {
    httpOnly: true,
    sameSite: production ? 'none' as const : 'lax' as const,
    secure: production,
    path: '/',
    maxAge: HOURS * 60 * 60 * 1000,
  }
}

export function setAuthCookie(res: Response, userId: string) {
  res.cookie('token', signToken(userId), authCookieOptions())
}

export async function hashPassword(password: string) {
  return bcrypt.hash(password, 12)
}

export async function verifyPassword(password: string, hash: string) {
  return bcrypt.compare(password, hash)
}

export async function loadUser(userId: string): Promise<AuthUser | null> {
  const user = await prisma.user.findUnique({ where: { id: userId } })
  if (!user || !user.isActive) return null
  return {
    id: user.id,
    email: user.email,
    role: user.role,
    firstName: user.firstName,
    lastName: user.lastName,
    employeeId: user.employeeId,
    departmentId: user.departmentId,
    locationId: user.locationId,
    mustChangePassword: user.mustChangePassword,
  }
}

export async function authMiddleware(req: Request, _res: Response, next: NextFunction) {
  try {
    const header = req.headers.authorization
    const token = header?.startsWith('Bearer ') ? header.slice(7) : req.cookies?.token
    if (!token) {
      next()
      return
    }
    const payload = jwt.verify(token, secret()) as { sub?: string }
    if (!payload.sub) {
      next()
      return
    }
    req.user = (await loadUser(payload.sub)) || undefined
    next()
  } catch {
    next()
  }
}

declare global {
  namespace Express {
    interface Request {
      user?: AuthUser
    }
  }
}
