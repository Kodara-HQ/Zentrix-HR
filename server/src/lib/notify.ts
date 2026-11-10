import nodemailer from 'nodemailer'
import { prisma } from './prisma.js'

export async function notify(input: {
  userIds: Array<string | null | undefined>
  title: string
  body: string
  type: string
  link?: string
  dedupeKey?: string
}) {
  const ids = [...new Set(input.userIds.filter((id): id is string => Boolean(id)))]
  if (!ids.length) return
  const setting = await prisma.notificationSetting.findUnique({ where: { eventKey: input.type } })
  const inApp = setting?.inApp ?? true
  const email = setting?.email ?? false
  if (inApp) {
    for (const userId of ids) {
      const dedupeKey = input.dedupeKey ? `${input.dedupeKey}:${userId}` : undefined
      if (dedupeKey) {
        const existing = await prisma.notification.findUnique({ where: { dedupeKey } })
        if (existing) continue
      }
      await prisma.notification.create({
        data: {
          userId,
          title: input.title,
          body: input.body,
          type: input.type,
          link: input.link,
          dedupeKey,
        },
      })
    }
  }
  if (email && process.env.SMTP_HOST) {
    const users = await prisma.user.findMany({ where: { id: { in: ids }, isActive: true } })
    const transport = nodemailer.createTransport({
      host: process.env.SMTP_HOST,
      port: Number(process.env.SMTP_PORT || 587),
      secure: Number(process.env.SMTP_PORT || 587) === 465,
      auth: process.env.SMTP_USER
        ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS }
        : undefined,
    })
    await Promise.all(
      users.flatMap((user) => user.email ? [
        transport.sendMail({
          from: process.env.SMTP_FROM || 'HRIS <hr@localhost>',
          to: user.email,
          subject: input.title,
          text: input.body,
        }).catch((error: unknown) => {
          console.error('Email notification failed', error)
        }),
      ] : []),
    )
  }
}

export async function usersByRoles(roles: Array<'SUPER_ADMIN' | 'HR_ADMIN' | 'HR_MANAGER' | 'PAYROLL_OFFICER' | 'RECRUITMENT_OFFICER' | 'DEPARTMENT_MANAGER' | 'SUPERVISOR' | 'EMPLOYEE'>, departmentId?: string | null) {
  const users = await prisma.user.findMany({
    where: {
      isActive: true,
      role: { in: roles },
      ...(departmentId ? { OR: [{ departmentId }, { role: { in: ['SUPER_ADMIN', 'HR_ADMIN', 'HR_MANAGER'] } }] } : {}),
    },
  })
  return users.map((user) => user.id)
}
