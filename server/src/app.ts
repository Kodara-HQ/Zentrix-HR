import express from 'express'
import cors from 'cors'
import helmet from 'helmet'
import cookieParser from 'cookie-parser'
import rateLimit from 'express-rate-limit'
import { authMiddleware } from './lib/auth.js'
import { errorMiddleware } from './lib/http.js'
import authRoutes from './routes/auth.js'
import settingsRoutes from './routes/settings.js'
import employeeRoutes from './routes/employees.js'
import recruitmentRoutes from './routes/recruitment.js'
import timeRoutes from './routes/time.js'
import payrollRoutes from './routes/payroll.js'
import talentRoutes from './routes/talent.js'
import workplaceRoutes from './routes/workplace.js'
import insightRoutes from './routes/insights.js'

function isLanOrigin(origin: string) {
  try {
    const url = new URL(origin)
    if (!['http:', 'https:'].includes(url.protocol)) return false
    const host = url.hostname
    if (host === 'localhost' || host === '127.0.0.1') return true
    const parts = host.split('.').map(Number)
    if (parts.length !== 4 || parts.some((part) => Number.isNaN(part))) return false
    if (parts[0] === 10) return true
    if (parts[0] === 192 && parts[1] === 168) return true
    if (parts[0] === 172 && parts[1] >= 16 && parts[1] <= 31) return true
    return false
  } catch {
    return false
  }
}

export function createApp() {
  const app = express()
  app.set('trust proxy', 1)
  app.use(helmet({ crossOriginResourcePolicy: { policy: 'cross-origin' } }))
  const configuredOrigins = (process.env.CLIENT_ORIGIN || 'http://localhost:5173')
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean)
  app.use(cors({
    origin(origin, callback) {
      if (!origin || configuredOrigins.includes(origin) || isLanOrigin(origin)) callback(null, true)
      else callback(null, false)
    },
    credentials: true,
  }))
  app.use(express.json({ limit: '12mb' }))
  app.use(cookieParser())
  app.use('/api/auth/login', rateLimit({ windowMs: 15 * 60 * 1000, limit: 30, standardHeaders: true, legacyHeaders: false }))
  app.get('/api/health', (_req, res) => {
    res.json({ ok: true, service: 'hris' })
  })
  app.use(authMiddleware)
  app.use('/api/auth', authRoutes)
  app.use('/api/settings', settingsRoutes)
  app.use('/api/employees', employeeRoutes)
  app.use('/api/recruitment', recruitmentRoutes)
  app.use('/api/time', timeRoutes)
  app.use('/api/payroll', payrollRoutes)
  app.use('/api/talent', talentRoutes)
  app.use('/api/workplace', workplaceRoutes)
  app.use('/api', insightRoutes)
  app.use('/api', (_req, res) => {
    res.status(404).json({ error: 'That address is not part of the HRIS API.' })
  })
  app.use(errorMiddleware)
  return app
}
