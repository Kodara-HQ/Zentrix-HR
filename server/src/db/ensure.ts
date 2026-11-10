import { existsSync } from 'node:fs'
import path from 'node:path'
import pg from 'pg'
import EmbeddedPostgres from 'embedded-postgres'

const embeddedPort = 54330

async function canConnect(connectionString: string) {
  const client = new pg.Client({ connectionString, connectionTimeoutMillis: 3000, query_timeout: 3000 })
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
  if (url && (await canConnect(url))) return

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
  const ready = await canConnect(process.env.DATABASE_URL)
  if (!ready) throw new Error('Embedded PostgreSQL started but the database is not accepting connections.')
}

export async function stopDatabase() {
  if (instance) await instance.stop()
}
