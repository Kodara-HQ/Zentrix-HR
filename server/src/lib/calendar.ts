import { prisma } from './prisma.js'
import { addDays, dateKey } from './http.js'

export async function activeCalendar() {
  const calendar = await prisma.workingCalendar.findFirst({ where: { isActive: true } })
  if (!calendar) {
    return {
      workDays: [1, 2, 3, 4, 5],
      startTime: '08:00',
      endTime: '17:00',
      hoursPerDay: 8,
      hoursPerMonth: 176,
      graceMinutes: 15,
      overtimeWeekday: 1.5,
      overtimeWeekend: 2,
    }
  }
  return {
    workDays: calendar.workDays as number[],
    startTime: calendar.startTime,
    endTime: calendar.endTime,
    hoursPerDay: Number(calendar.hoursPerDay),
    hoursPerMonth: Number(calendar.hoursPerMonth),
    graceMinutes: calendar.graceMinutes,
    overtimeWeekday: Number(calendar.overtimeWeekday),
    overtimeWeekend: Number(calendar.overtimeWeekend),
  }
}

export async function holidaySet(from: Date, to: Date) {
  const holidays = await prisma.holiday.findMany({
    where: { date: { gte: from, lte: to } },
  })
  return new Set(holidays.map((holiday) => dateKey(holiday.date)))
}

export function isWorkingDay(date: Date, workDays: number[], holidays: Set<string>) {
  if (!workDays.includes(date.getUTCDay())) return false
  return !holidays.has(dateKey(date))
}

export function countDays(start: Date, end: Date, mode: 'WORKING_DAYS' | 'CALENDAR_DAYS', workDays: number[], holidays: Set<string>) {
  let days = 0
  for (let cursor = new Date(start); cursor <= end; cursor = addDays(cursor, 1)) {
    if (mode === 'CALENDAR_DAYS' || isWorkingDay(cursor, workDays, holidays)) days += 1
  }
  return days
}

export function workingDaysInMonth(year: number, month: number, workDays: number[], holidays: Set<string>) {
  const start = new Date(Date.UTC(year, month - 1, 1))
  const end = new Date(Date.UTC(year, month, 0))
  return countDays(start, end, 'WORKING_DAYS', workDays, holidays)
}

export function combineDateAndTime(date: Date, time: string) {
  const [hours, minutes] = time.split(':').map(Number)
  return new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate(), hours || 0, minutes || 0))
}

export function minutesLate(clockIn: Date, expected: Date, grace: number) {
  const diff = Math.round((clockIn.getTime() - expected.getTime()) / 60000)
  return diff > grace ? diff : 0
}

export function minutesEarly(clockOut: Date, expected: Date) {
  const diff = Math.round((expected.getTime() - clockOut.getTime()) / 60000)
  return diff > 0 ? diff : 0
}

export function minutesOvertime(clockOut: Date, expected: Date) {
  const diff = Math.round((clockOut.getTime() - expected.getTime()) / 60000)
  return diff > 15 ? diff : 0
}
