import type { NextFunction, Request, Response } from 'express'
import { ZodError, type ZodType } from 'zod'

export class HttpError extends Error {
  status: number
  details?: unknown
  constructor(status: number, message: string, details?: unknown) {
    super(message)
    this.status = status
    this.details = details
  }
}

export function asyncHandler(
  fn: (req: Request, res: Response, next: NextFunction) => Promise<unknown>,
) {
  return (req: Request, res: Response, next: NextFunction) => {
    Promise.resolve(fn(req, res, next)).catch(next)
  }
}

export function parseBody<T>(schema: ZodType<T>, body: unknown): T {
  const result = schema.safeParse(body)
  if (!result.success) {
    throw new HttpError(400, 'Check the form and try again.', flatten(result.error))
  }
  return result.data
}

function flatten(error: ZodError) {
  return error.issues.map((issue) => ({
    path: issue.path.join('.'),
    message: issue.message,
  }))
}

export function pageParams(req: Request) {
  const page = Math.max(1, Number(req.query.page) || 1)
  const pageSize = Math.min(100, Math.max(1, Number(req.query.pageSize) || 20))
  const search = String(req.query.search || '').trim()
  const sort = String(req.query.sort || '').trim()
  const dir = String(req.query.dir || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc'
  return { page, pageSize, search, sort, dir, skip: (page - 1) * pageSize }
}

export function paged<T>(items: T[], total: number, page: number, pageSize: number) {
  return { items, total, page, pageSize }
}

export function asDate(value: string | Date) {
  if (value instanceof Date) return value
  const [year, month, day] = value.slice(0, 10).split('-').map(Number)
  if (!year || !month || !day) throw new HttpError(400, 'Enter a valid date.')
  return new Date(Date.UTC(year, month - 1, day))
}

export function dateKey(value: Date | string) {
  const date = value instanceof Date ? value : new Date(value)
  return date.toISOString().slice(0, 10)
}

export function todayUtc() {
  const now = new Date()
  return new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()))
}

export function addDays(date: Date, days: number) {
  const next = new Date(date.getTime())
  next.setUTCDate(next.getUTCDate() + days)
  return next
}

export function money(value: { toString(): string } | number | null | undefined) {
  if (value == null) return 0
  return Math.round(Number(value.toString()) * 100) / 100
}

export function round2(value: number) {
  return Math.round((value + Number.EPSILON) * 100) / 100
}

export function errorMiddleware(error: unknown, _req: Request, res: Response, _next: NextFunction) {
  if (error instanceof HttpError) {
    res.status(error.status).json({ error: error.message, details: error.details })
    return
  }
  const prisma = error as { code?: string; meta?: { target?: string[] } }
  if (prisma.code === 'P2002') {
    res.status(409).json({ error: 'A record with those details already exists.' })
    return
  }
  if (prisma.code === 'P2003') {
    res.status(409).json({ error: 'This record is still in use and cannot be removed.' })
    return
  }
  if (prisma.code === 'P2025') {
    res.status(404).json({ error: 'Record not found.' })
    return
  }
  console.error(error)
  res.status(500).json({ error: 'Something went wrong. Please try again.' })
}
