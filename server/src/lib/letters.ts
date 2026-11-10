import { randomUUID } from 'node:crypto'
import PDFDocument from 'pdfkit'
import { prisma } from './prisma.js'
import { saveBuffer } from './storage.js'
import { fullName } from './employees.js'
import type { LetterType } from '@prisma/client'

function formDate(value?: Date | string | null) {
  if (!value) return '—'
  const iso = value instanceof Date ? value.toISOString() : value
  const [year, month, day] = iso.slice(0, 10).split('-')
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  if (!year || !month || !day) return '—'
  return `${Number(day)} ${months[Number(month) - 1]} ${year}`
}

function labelValue(doc: PDFKit.PDFDocument, label: string, value: string, x: number, y: number, width: number) {
  doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text(label.toUpperCase(), x, y, { width, lineBreak: false })
  doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(11).text(value || '—', x, y + 12, { width, lineBreak: false, ellipsis: true })
}

export function renderLeaveForm(input: {
  company: string
  employeeNo: string
  name: string
  department: string
  job: string
  location: string
  phone: string
  balances: Array<{ name: string; left: number; used: number }>
  types: string[]
  request?: { type: string; start: Date | string; end: Date | string; days: number; reason?: string | null; status: string }
}) {
  return renderPdf((doc) => {
    const left = 48
    const width = doc.page.width - left * 2
    doc.rect(0, 0, doc.page.width, 78).fill('#1a3c34')
    doc.fillColor('#c4a574').font('Helvetica').fontSize(9).text('EMPLOYEE SELF SERVICE', left, 18, { lineBreak: false })
    doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(16).text(input.company, left, 32, { lineBreak: false })
    doc.font('Helvetica').fontSize(11).text('Leave application form', left, 54, { lineBreak: false })
    doc.fontSize(8).fillColor('#c4a574').text(formDate(new Date()), doc.page.width - left - 120, 24, { width: 120, align: 'right', lineBreak: false })

    let y = 98
    labelValue(doc, 'Employee', input.name, left, y, width * 0.46)
    labelValue(doc, 'Employee number', input.employeeNo, left + width * 0.5, y, width * 0.46)
    y += 40
    labelValue(doc, 'Department', input.department, left, y, width * 0.46)
    labelValue(doc, 'Job title', input.job, left + width * 0.5, y, width * 0.46)
    y += 40
    labelValue(doc, 'Location', input.location, left, y, width * 0.46)
    labelValue(doc, 'Phone', input.phone, left + width * 0.5, y, width * 0.46)

    y += 52
    doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(12).text('Leave balance this year', left, y, { lineBreak: false })
    y += 20
    doc.rect(left, y, width, 18).fill('#1a3c34')
    doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(8)
    doc.text('LEAVE', left + 8, y + 5, { width: width * 0.5, lineBreak: false })
    doc.text('DAYS LEFT', left + width * 0.55, y + 5, { width: 80, lineBreak: false })
    doc.text('USED', left + width * 0.78, y + 5, { width: 60, lineBreak: false })
    y += 18
    input.balances.forEach((balance, index) => {
      if (index % 2 === 0) doc.rect(left, y, width, 18).fill('#f6f1e8')
      doc.fillColor('#24312c').font('Helvetica').fontSize(9)
      doc.text(balance.name, left + 8, y + 4, { width: width * 0.5, lineBreak: false })
      doc.text(String(balance.left), left + width * 0.55, y + 4, { width: 80, lineBreak: false })
      doc.text(String(balance.used), left + width * 0.78, y + 4, { width: 60, lineBreak: false })
      y += 18
    })

    y += 22
    doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(12).text('Application', left, y, { lineBreak: false })
    y += 20
    if (input.request) {
      const status = input.request.status.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
      labelValue(doc, 'Leave type', input.request.type, left, y, width * 0.46)
      labelValue(doc, 'Status', status, left + width * 0.5, y, width * 0.46)
      y += 40
      labelValue(doc, 'From', formDate(input.request.start), left, y, width * 0.3)
      labelValue(doc, 'To', formDate(input.request.end), left + width * 0.34, y, width * 0.3)
      labelValue(doc, 'Days', String(input.request.days), left + width * 0.7, y, width * 0.25)
      y += 40
      doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text('REASON', left, y, { lineBreak: false })
      doc.fillColor('#24312c').font('Helvetica').fontSize(11).text(input.request.reason || '—', left, y + 14, { width })
      y += 48
    } else {
      doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text('LEAVE TYPE', left, y, { lineBreak: false })
      y += 16
      input.types.forEach((type, index) => {
        const column = index % 2
        const row = Math.floor(index / 2)
        const x = left + column * (width / 2)
        const boxY = y + row * 18
        doc.rect(x, boxY, 10, 10).lineWidth(1).strokeColor('#1a3c34').stroke()
        doc.fillColor('#24312c').font('Helvetica').fontSize(10).text(type, x + 16, boxY - 1, { lineBreak: false })
      })
      y += Math.ceil(input.types.length / 2) * 18 + 14
      doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text('FROM', left, y, { lineBreak: false })
      doc.text('TO', left + width * 0.34, y, { lineBreak: false })
      doc.text('DAYS', left + width * 0.7, y, { lineBreak: false })
      y += 16
      doc.moveTo(left, y + 14).lineTo(left + width * 0.28, y + 14).strokeColor('#c4a574').stroke()
      doc.moveTo(left + width * 0.34, y + 14).lineTo(left + width * 0.62, y + 14).stroke()
      doc.moveTo(left + width * 0.7, y + 14).lineTo(left + width, y + 14).stroke()
      y += 32
      doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text('REASON', left, y, { lineBreak: false })
      y += 22
      doc.moveTo(left, y).lineTo(left + width, y).strokeColor('#c4a574').stroke()
      y += 18
      doc.moveTo(left, y).lineTo(left + width, y).stroke()
      y += 28
    }

    doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text('CONTACT WHILE ON LEAVE', left, y, { lineBreak: false })
    y += 20
    doc.moveTo(left, y).lineTo(left + width, y).strokeColor('#c4a574').stroke()

    y = Math.max(y + 36, 640)
    const box = width / 3 - 8
    ;['Employee', 'Supervisor', 'Human Resources'].forEach((title, index) => {
      const x = left + index * (box + 12)
      doc.rect(x, y, box, 92).strokeColor('#e6dfd4').stroke()
      doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(9).text(title, x + 8, y + 8, { lineBreak: false })
      doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text('Signature', x + 8, y + 32, { lineBreak: false })
      doc.moveTo(x + 8, y + 58).lineTo(x + box - 8, y + 58).strokeColor('#c4a574').stroke()
      doc.fillColor('#6e675e').text('Date', x + 8, y + 64, { lineBreak: false })
      doc.moveTo(x + 36, y + 74).lineTo(x + box - 8, y + 74).stroke()
    })
  })
}

export function renderPdf(build: (doc: PDFKit.PDFDocument) => void, margin = 56) {
  return new Promise<Buffer>((resolve, reject) => {
    const doc = new PDFDocument({ margin, size: 'A4' })
    const chunks: Buffer[] = []
    doc.on('data', (chunk: Buffer) => chunks.push(chunk))
    doc.on('end', () => resolve(Buffer.concat(chunks)))
    doc.on('error', reject)
    build(doc)
    doc.end()
  })
}

function presentCell(value: string) {
  if (/^[A-Z][A-Z0-9_]+$/.test(value)) {
    return value.toLowerCase().replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
  }
  return value
}

export function renderReportPdf(
  report: { title: string; columns: Array<{ key: string; label: string }>; rows: Array<Record<string, string | number>> },
  company?: { name: string; legalName?: string | null } | null,
) {
  return new Promise<Buffer>((resolve, reject) => {
    const doc = new PDFDocument({ size: 'A4', layout: 'landscape', margin: 28, bufferPages: true })
    const chunks: Buffer[] = []
    doc.on('data', (chunk: Buffer) => chunks.push(chunk))
    doc.on('end', () => resolve(Buffer.concat(chunks)))
    doc.on('error', reject)

    const margin = 28
    const companyName = company?.name || 'Human resources'
    const legalName = company?.legalName || companyName
    const generated = new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    const rows = report.rows.slice(0, 2000)
    const tableWidth = doc.page.width - margin * 2
    const samples = rows.slice(0, 40)
    const weights = report.columns.map((column) => {
      const longest = samples.reduce((max, row) => Math.max(max, String(row[column.key] ?? '').length), column.label.length)
      return Math.min(Math.max(longest, 8), 32)
    })
    const weightTotal = weights.reduce((sum, value) => sum + value, 0) || 1
    const widths = weights.map((value) => (value / weightTotal) * tableWidth)
    const rowHeight = 18

    const paintBand = (continued: boolean) => {
      const height = continued ? 34 : 70
      doc.save()
      doc.rect(0, 0, doc.page.width, height).fill('#1a3c34')
      doc.fillColor('#c4a574').font('Helvetica').fontSize(8)
      doc.text('HUMAN RESOURCES', margin, continued ? 8 : 16, { lineBreak: false })
      doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(continued ? 11 : 16)
      doc.text(companyName, margin, continued ? 18 : 30, { lineBreak: false, width: tableWidth * 0.55 })
      if (!continued) {
        doc.font('Helvetica').fontSize(11).fillColor('#e7dfd2')
        doc.text(report.title, margin, 50, { lineBreak: false })
        doc.fontSize(8).fillColor('#c4a574')
        doc.text(generated, doc.page.width - margin - 120, 20, { width: 120, align: 'right', lineBreak: false })
      }
      doc.restore()
      return height + 12
    }

    const paintColumns = (y: number) => {
      doc.save()
      doc.rect(margin, y, tableWidth, 22).fill('#1a3c34')
      let x = margin
      doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(8)
      report.columns.forEach((column, index) => {
        doc.text(column.label.toUpperCase(), x + 6, y + 7, { width: widths[index] - 10, lineBreak: false, ellipsis: true })
        x += widths[index]
      })
      doc.restore()
      return y + 22
    }

    let y = paintBand(false)
    doc.font('Helvetica').fontSize(8).fillColor('#6e675e')
    doc.text(`${rows.length} record${rows.length === 1 ? '' : 's'}`, margin, y, { lineBreak: false })
    y += 16
    y = paintColumns(y)

    const bottom = doc.page.height - 40
    rows.forEach((row, index) => {
      if (y + rowHeight > bottom) {
        doc.addPage()
        y = paintBand(true)
        y = paintColumns(y)
      }
      if (index % 2 === 0) {
        doc.save()
        doc.rect(margin, y, tableWidth, rowHeight).fill('#f6f1e8')
        doc.restore()
      }
      let x = margin
      doc.fillColor('#24312c').font('Helvetica').fontSize(8)
      report.columns.forEach((column, columnIndex) => {
        const text = presentCell(String(row[column.key] ?? ''))
        doc.text(text, x + 6, y + 5, { width: widths[columnIndex] - 10, height: rowHeight - 4, lineBreak: false, ellipsis: true })
        x += widths[columnIndex]
      })
      doc.save()
      doc.moveTo(margin, y + rowHeight).lineTo(margin + tableWidth, y + rowHeight).strokeColor('#e6dfd4').lineWidth(0.4).stroke()
      doc.restore()
      y += rowHeight
    })

    if (!rows.length) {
      doc.font('Helvetica').fontSize(10).fillColor('#6e675e').text('No records for this report.', margin, y + 12)
    }

    const range = doc.bufferedPageRange()
    for (let page = 0; page < range.count; page += 1) {
      doc.switchToPage(page)
      doc.page.margins.bottom = 0
      const footerY = doc.page.height - 22
      doc.save()
      doc.moveTo(margin, footerY - 8).lineTo(doc.page.width - margin, footerY - 8).strokeColor('#c4a574').lineWidth(1).stroke()
      doc.font('Helvetica').fontSize(8).fillColor('#6e675e')
      doc.text(legalName, margin, footerY, { lineBreak: false, width: tableWidth - 90, height: 12 })
      doc.text(`Page ${page + 1} of ${range.count}`, doc.page.width - margin - 90, footerY, { width: 90, align: 'right', lineBreak: false, height: 12 })
      doc.restore()
    }
    doc.end()
  })
}

function longDate(value: Date) {
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
  return `${value.getUTCDate()} ${months[value.getUTCMonth()]} ${value.getUTCFullYear()}`
}

function drawLetter(doc: PDFKit.PDFDocument, input: {
  company: { name: string; legalName?: string | null; phone?: string | null; email?: string | null; city?: string | null; country?: string | null }
  employee: { firstName: string; middleName?: string | null; lastName: string; employeeNo: string; dateOfEmployment: Date; jobTitle?: { name: string } | null; department?: { name: string } | null; location?: { name: string; city: string } | null }
  subject: string
  paragraphs: string[]
  issuedOn: Date
  appointment: boolean
}) {
  const left = 52
  const width = doc.page.width - left * 2
  doc.page.margins.bottom = 0
  doc.rect(0, 0, doc.page.width, 88).fill('#1a3c34')
  doc.rect(0, 88, doc.page.width, 4).fill('#c4a574')
  doc.roundedRect(left, 24, 36, 36, 6).fill('#c4622d')
  doc.fillColor('#ffffff').font('Helvetica-Bold').fontSize(18).text('G', left, 33, { width: 36, align: 'center', lineBreak: false })
  doc.fillColor('#c4a574').font('Helvetica').fontSize(8).text('HUMAN RESOURCES', left + 48, 26, { lineBreak: false })
  doc.fillColor('#f6f1e8').font('Helvetica-Bold').fontSize(16).text(input.company.name, left + 48, 38, { width: width - 160, lineBreak: false })
  doc.font('Helvetica').fontSize(10).text(input.subject, left + 48, 60, { width: width - 190, lineBreak: false })
  doc.fillColor('#c4a574').font('Helvetica').fontSize(9).text(longDate(input.issuedOn), left, 60, { width, align: 'right', lineBreak: false })

  let y = 112
  doc.fillColor('#24312c').font('Helvetica').fontSize(10.5)
  doc.text(`Dear ${fullName(input.employee)},`, left, y, { width, lineGap: 1 })
  y = doc.y + 8
  for (const paragraph of input.paragraphs) {
    doc.text(paragraph, left, y, { width, align: 'justify', lineGap: 1 })
    y = doc.y + 8
  }

  if (input.appointment) {
    y += 2
    doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(11).text('Particulars of appointment', left, y, { lineBreak: false })
    y += 16
    const station = [input.employee.location?.name, input.employee.location?.city].filter(Boolean).join(', ')
    const rows = [
      ['Position', input.employee.jobTitle?.name || '—'],
      ['Department', input.employee.department?.name || '—'],
      ['Duty station', station || '—'],
      ['Employee number', input.employee.employeeNo],
      ['Commencement', longDate(input.employee.dateOfEmployment)],
    ]
    const top = y
    rows.forEach((row, index) => {
      if (index % 2 === 0) doc.rect(left, y, width, 20).fill('#f6f1e8')
      doc.fillColor('#6e675e').font('Helvetica').fontSize(8).text(row[0].toUpperCase(), left + 10, y + 6, { width: 130, lineBreak: false })
      doc.fillColor('#1a3c34').font('Helvetica-Bold').fontSize(10).text(row[1], left + 148, y + 5, { width: width - 166, lineBreak: false })
      y += 20
    })
    doc.rect(left, top, width, rows.length * 20).lineWidth(0.6).strokeColor('#e6dfd4').stroke()
  }

  y += 18
  doc.fillColor('#24312c').font('Helvetica').fontSize(10.5).text('Yours faithfully,', left, y, { lineBreak: false })
  y += 32
  doc.moveTo(left, y).lineTo(left + 180, y).lineWidth(1).strokeColor('#c4a574').stroke()
  y += 8
  doc.font('Helvetica-Bold').fontSize(10.5).fillColor('#1a3c34').text('Human Resources', left, y, { lineBreak: false })
  y += 13
  doc.font('Helvetica').fontSize(9).fillColor('#6e675e').text(input.company.name, left, y, { lineBreak: false })

  doc.rect(0, doc.page.height - 28, doc.page.width, 28).fill('#1a3c34')
  const contact = [input.company.phone, input.company.email, [input.company.city, input.company.country].filter(Boolean).join(', ')].filter(Boolean).join('   ·   ')
  doc.fillColor('#c4a574').font('Helvetica').fontSize(8).text(contact || input.company.name, left, doc.page.height - 18, { width, align: 'center', lineBreak: false })
}

export async function issueLetter(input: {
  employeeId: string
  type: LetterType
  subject: string
  paragraphs: string[]
  issuedOn: Date
  uploadedById?: string
}) {
  const [employee, company] = await Promise.all([
    prisma.employee.findUnique({
      where: { id: input.employeeId },
      include: { department: true, jobTitle: true, location: true },
    }),
    prisma.company.findFirst(),
  ])
  if (!employee || !company) return null
  const buffer = await renderPdf((doc) => drawLetter(doc, {
    company,
    employee,
    subject: input.subject,
    paragraphs: input.paragraphs,
    issuedOn: input.issuedOn,
    appointment: input.type === 'APPOINTMENT',
  }), 0)
  const fileId = randomUUID()
  const storageKey = `letters/${fileId}.pdf`
  await saveBuffer(storageKey, buffer, 'application/pdf')
  const file = await prisma.fileAsset.create({
    data: {
      id: fileId,
      storageKey,
      fileName: `${input.type.toLowerCase()}-${employee.employeeNo}.pdf`,
      mimeType: 'application/pdf',
      size: buffer.length,
      ownerType: 'EMPLOYEE',
      ownerId: employee.id,
      confidential: input.type === 'WARNING',
      uploadedById: input.uploadedById,
    },
  })
  return prisma.hrLetter.create({
    data: {
      employeeId: employee.id,
      type: input.type,
      subject: input.subject,
      body: input.paragraphs.join('\n\n'),
      issuedOn: input.issuedOn,
      fileAssetId: file.id,
    },
  })
}
