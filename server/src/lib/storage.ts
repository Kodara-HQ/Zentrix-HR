import { createReadStream, createWriteStream } from 'node:fs'
import { mkdir, readFile, unlink, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { pipeline } from 'node:stream/promises'
import type { Readable } from 'node:stream'
import { GetObjectCommand, PutObjectCommand, S3Client } from '@aws-sdk/client-s3'

const root = path.resolve(process.cwd(), 'uploads')

function s3() {
  if (!process.env.S3_BUCKET) return null
  return new S3Client({
    region: process.env.S3_REGION || 'us-east-1',
    endpoint: process.env.S3_ENDPOINT || undefined,
    forcePathStyle: Boolean(process.env.S3_ENDPOINT),
    credentials: process.env.S3_ACCESS_KEY
      ? { accessKeyId: process.env.S3_ACCESS_KEY, secretAccessKey: process.env.S3_SECRET_KEY || '' }
      : undefined,
  })
}

export async function saveBuffer(storageKey: string, body: Buffer, mimeType: string) {
  const client = s3()
  if (client && process.env.S3_BUCKET) {
    await client.send(new PutObjectCommand({
      Bucket: process.env.S3_BUCKET,
      Key: storageKey,
      Body: body,
      ContentType: mimeType,
    }))
    return
  }
  const full = path.join(root, storageKey)
  await mkdir(path.dirname(full), { recursive: true })
  await writeFile(full, body)
}

export async function saveStream(storageKey: string, body: Readable, mimeType: string) {
  const client = s3()
  if (client && process.env.S3_BUCKET) {
    const chunks: Buffer[] = []
    for await (const chunk of body) chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk))
    await saveBuffer(storageKey, Buffer.concat(chunks), mimeType)
    return
  }
  const full = path.join(root, storageKey)
  await mkdir(path.dirname(full), { recursive: true })
  await pipeline(body, createWriteStream(full))
}

export async function readStored(storageKey: string) {
  const client = s3()
  if (client && process.env.S3_BUCKET) {
    const response = await client.send(new GetObjectCommand({ Bucket: process.env.S3_BUCKET, Key: storageKey }))
    const bytes = await response.Body?.transformToByteArray()
    if (!bytes) throw new Error('Empty file')
    return Buffer.from(bytes)
  }
  const full = path.join(root, storageKey)
  if (!full.startsWith(root)) throw new Error('Invalid storage key')
  return readFile(full)
}

export function readStoredStream(storageKey: string) {
  const full = path.join(root, storageKey)
  return createReadStream(full)
}

export async function removeStored(storageKey: string) {
  if (process.env.S3_BUCKET) return
  const full = path.join(root, storageKey)
  await unlink(full).catch(() => undefined)
}
