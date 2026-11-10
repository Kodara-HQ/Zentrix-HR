import type { Prisma } from '@prisma/client'
import type { Request } from 'express'
import { prisma } from './prisma.js'

export async function audit(
  req: Request,
  action: string,
  entity: string,
  entityId: string | null,
  summary: string,
  before?: Prisma.InputJsonValue,
  after?: Prisma.InputJsonValue,
) {
  await prisma.auditLog.create({
    data: {
      userId: req.user?.id,
      action,
      entity,
      entityId,
      summary,
      before,
      after,
      ip: req.ip,
    },
  })
}
