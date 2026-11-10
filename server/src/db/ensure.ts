import { existsSync } from 'node:fs'
import path from 'node:path'
import pg from 'pg'
import EmbeddedPostgres from 'embedded-postgres'

const embeddedPort = 54330

function isHosted(connectionString: string) {
  return !/localhost|127\.0\.0\.1/.test(connectionString)
}

async function canConnect(connectionString: string, timeout: number) {
  const client = new pg.Client({ connectionString, connectionTimeoutMillis: timeout, query_timeout: timeout })
  try {
    await client.connect()
    await client.query('select 1')
    return true
  } catch {
    return false
  } finally {
    await client.end().catch(() => undefined)
  }
}

let instance: EmbeddedPostgres | null = null

export async function ensureDatabase() {
  const url = process.env.DATABASE_URL
  if (url) {
    const hosted = isHosted(url)
    const attempts = hosted ? 3 : 1
    for (let attempt = 0; attempt < attempts; attempt += 1) {
      if (await canConnect(url, hosted ? 5000 : 3000)) return
    }
    if (hosted) throw new Error('Cannot reach DATABASE_URL and embedded PostgreSQL is disabled.')
  }

  if (process.env.USE_EMBEDDED_POSTGRES !== 'true') {
    throw new Error('Cannot reach DATABASE_URL and embedded PostgreSQL is disabled.')
  }

  const databaseDir = path.resolve(process.cwd(), 'data', 'pg')
  const embedded = new EmbeddedPostgres({
    databaseDir,
    user: 'hris',
    password: 'hris',
    port: embeddedPort,
    persistent: true,
  })
  if (!existsSync(path.join(databaseDir, 'PG_VERSION'))) {
    await embedded.initialise()
  }
  await embedded.start()
  try {
    await embedded.createDatabase('hris')
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error)
    if (!/already exists/i.test(message)) throw error
  }
  process.env.DATABASE_URL = `postgresql://hris:hris@127.0.0.1:${embeddedPort}/hris?schema=public`
  instance = embedded
  const ready = await canConnect(process.env.DATABASE_URL, 3000)
  if (!ready) throw new Error('Embedded PostgreSQL started but the database is not accepting connections.')
}

export async function stopDatabase() {
  if (instance) await instance.stop()
}
