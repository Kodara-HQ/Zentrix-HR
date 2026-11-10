import { useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

export function Button({
  children, onClick, type = 'button', tone = 'primary', disabled, small,
}: {
  children: ReactNode
  onClick?: () => void
  type?: 'button' | 'submit'
  tone?: 'primary' | 'ghost' | 'danger' | 'navy'
  disabled?: boolean
  small?: boolean
}) {
  const tones = {
    primary: 'bg-[#c4622d] text-white hover:bg-[#a85122]',
    navy: 'bg-[#1a3c34] text-[#f6f1e8] hover:bg-[#142e28]',
    ghost: 'bg-white text-[#24312c] border border-[#e4ddd2] hover:bg-[#fbf8f3]',
    danger: 'bg-white text-[#9f2d2d] border border-[#e7c7c7] hover:bg-[#fdf6f6]',
  }
  return (
    <button
      type={type}
      disabled={disabled}
      onClick={onClick}
      className={`inline-flex items-center justify-center gap-2 rounded-lg font-semibold disabled:opacity-50 ${small ? 'px-2.5 py-1.5 text-sm' : 'px-3.5 py-2 text-sm'} ${tones[tone]}`}
    >
      {children}
    </button>
  )
}

export function Badge({ children, tone = 'slate' }: { children: ReactNode; tone?: 'slate' | 'green' | 'amber' | 'red' | 'blue' }) {
  const tones = {
    slate: 'bg-[#f3efe6] text-[#4a524c]',
    green: 'bg-[#e7f0ea] text-[#1a3c34]',
    amber: 'bg-[#f8f0df] text-[#8c6a32]',
    red: 'bg-[#fdf1ee] text-[#9f2d2d]',
    blue: 'bg-[#f6efe3] text-[#8c6a32]',
  }
  return <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ${tones[tone]}`}>{children}</span>
}

export function statusTone(status?: string) {
  if (!status) return 'slate' as const
  if (['ACTIVE', 'APPROVED', 'FULFILLED', 'HIRED', 'COMPLETED', 'PRESENT', 'OPEN'].includes(status)) return 'green' as const
  if (['PENDING', 'PENDING_MANAGER', 'PENDING_HR', 'PROBATION', 'NOTICE', 'DRAFT', 'SENT', 'LATE'].includes(status)) return 'amber' as const
  if (['REJECTED', 'SUSPENDED', 'TERMINATED', 'ABSENT', 'CANCELLED'].includes(status)) return 'red' as const
  return 'blue' as const
}

export function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block font-semibold text-[#4a524c]">{label}</span>
      {children}
    </label>
  )
}

export const controlClass = 'w-full rounded-lg border border-[#e4ddd2] bg-white px-3 py-2 text-sm outline-none focus:border-[#c4622d]'

export function SearchSelect({
  value, onChange, options, placeholder = 'Select', required, allowEmpty = true, className = 'w-full',
}: {
  value: string
  onChange: (value: string) => void
  options: Array<{ value: string; label: string }>
  placeholder?: string
  required?: boolean
  allowEmpty?: boolean
  className?: string
}) {
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [active, setActive] = useState(0)
  const [box, setBox] = useState({ top: 0, left: 0, width: 0, upward: false })
  const root = useRef<HTMLDivElement>(null)
  const menu = useRef<HTMLUListElement>(null)
  const selected = options.find((option) => option.value === value)
  const needle = query.trim().toLowerCase()
  const filtered = options.filter((option) => option.label.toLowerCase().includes(needle))

  useEffect(() => {
    const onDoc = (event: MouseEvent) => {
      const target = event.target as Node
      if (!root.current?.contains(target) && !menu.current?.contains(target)) setOpen(false)
    }
    document.addEventListener('mousedown', onDoc)
    return () => document.removeEventListener('mousedown', onDoc)
  }, [])

  useLayoutEffect(() => {
    if (!open || !root.current) return
    const place = () => {
      const rect = root.current!.getBoundingClientRect()
      const upward = window.innerHeight - rect.bottom < 240 && rect.top > 240
      setBox({ top: upward ? rect.top : rect.bottom, left: rect.left, width: rect.width, upward })
    }
    place()
    window.addEventListener('scroll', place, true)
    window.addEventListener('resize', place)
    return () => {
      window.removeEventListener('scroll', place, true)
      window.removeEventListener('resize', place)
    }
  }, [open])

  function choose(next: string) {
    onChange(next)
    setOpen(false)
    setQuery('')
  }

  const rows = allowEmpty && !needle ? [{ value: '', label: placeholder }, ...filtered] : filtered

  return (
    <div ref={root} className={`relative ${className}`}>
      {required && <input className="pointer-events-none absolute h-0 w-0 opacity-0" tabIndex={-1} value={value} required onChange={() => undefined} />}
      <input
        className={`${controlClass} pr-8`}
        role="combobox"
        aria-expanded={open}
        aria-autocomplete="list"
        autoComplete="off"
        placeholder={open ? 'Search' : placeholder}
        value={open ? query : (selected?.label || '')}
        onFocus={() => { setOpen(true); setQuery(''); setActive(0) }}
        onChange={(event) => { setQuery(event.target.value); setOpen(true); setActive(0) }}
        onKeyDown={(event) => {
          if (event.key === 'ArrowDown') { event.preventDefault(); setOpen(true); setActive((index) => Math.min(index + 1, rows.length - 1)) }
          else if (event.key === 'ArrowUp') { event.preventDefault(); setActive((index) => Math.max(index - 1, 0)) }
          else if (event.key === 'Enter' && open && rows[active]) { event.preventDefault(); choose(rows[active].value) }
          else if (event.key === 'Escape') { setOpen(false); setQuery('') }
        }}
      />
      <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#6e675e]">▾</span>
      {open && createPortal(
        <ul
          ref={menu}
          className="fixed z-[80] max-h-56 overflow-auto rounded-lg border border-[#e4ddd2] bg-white py-1 shadow-lg"
          style={box.upward
            ? { left: box.left, width: box.width, bottom: window.innerHeight - box.top + 4 }
            : { left: box.left, width: box.width, top: box.top + 4 }}
        >
          {rows.map((option, index) => (
            <li key={`${option.value}-${option.label}`}>
              <button
                type="button"
                className={`w-full px-3 py-1.5 text-left text-sm hover:bg-[#f6f1e8] ${index === active || option.value === value ? 'bg-[#f3efe6] text-[#1a3c34]' : 'text-[#24312c]'}`}
                onMouseEnter={() => setActive(index)}
                onMouseDown={(event) => { event.preventDefault(); choose(option.value) }}
              >
                {option.label}
              </button>
            </li>
          ))}
          {!filtered.length && <li className="px-3 py-2 text-sm text-[#6e675e]">No matches</li>}
        </ul>,
        document.body,
      )}
    </div>
  )
}

export function Modal({ title, onClose, children, wide }: { title: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => { if (event.key === 'Escape') onClose() }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-[#1a3c34]/45 p-3 sm:items-center" onMouseDown={onClose}>
      <div className={`max-h-[92vh] w-full overflow-auto rounded-2xl bg-[#fffcf8] shadow-2xl ${wide ? 'max-w-4xl' : 'max-w-xl'}`} onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-center justify-between border-b border-[#e6dfd4] px-5 py-4">
          <h2 className="text-lg font-semibold text-[#1a3c34]">{title}</h2>
          <button className="text-sm text-[#6e675e]" onClick={onClose}>Close</button>
        </div>
        <div className="px-5 py-4">{children}</div>
      </div>
    </div>
  )
}

export function Confirm({ title, message, onCancel, onConfirm }: { title: string; message: string; onCancel: () => void; onConfirm: () => void }) {
  return (
    <Modal title={title} onClose={onCancel}>
      <p className="text-sm text-[#4a524c]">{message}</p>
      <div className="mt-5 flex justify-end gap-2">
        <Button tone="ghost" onClick={onCancel}>Cancel</Button>
        <Button tone="danger" onClick={onConfirm}>Confirm</Button>
      </div>
    </Modal>
  )
}

export function Empty({ text }: { text: string }) {
  return <div className="rounded-xl border border-dashed border-[#e4ddd2] px-4 py-8 text-center text-sm text-[#6e675e]">{text}</div>
}

export function ErrorNote({ text }: { text?: string }) {
  if (!text) return null
  return <div className="rounded-lg border border-[#e7c7c7] bg-[#fdf6f6] px-3 py-2 text-sm text-[#9f2d2d]">{text}</div>
}

export function PageHeader({ title, text, children }: { title: string; text?: string; children?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-[#1a3c34]">{title}</h1>
        {text && <p className="mt-1 max-w-3xl text-sm text-[#6e675e]">{text}</p>}
      </div>
      <div className="flex flex-wrap gap-2">{children}</div>
    </div>
  )
}

export function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] p-4 shadow-sm ${className}`}>{children}</section>
}

export function Stat({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
  return (
    <div className="rounded-2xl border border-[#e6dfd4] bg-[#fffcf8] px-4 py-3 shadow-sm">
      <div className="text-xs font-semibold uppercase tracking-[0.14em] text-[#c4a574]">{label}</div>
      <div className="mt-1 text-2xl font-semibold text-[#1a3c34]">{value}</div>
      {hint && <div className="mt-1 text-xs text-[#6e675e]">{hint}</div>}
    </div>
  )
}

export function Tabs({ tabs, value, onChange }: { tabs: string[]; value: string; onChange: (value: string) => void }) {
  return (
    <div className="mb-4 flex gap-1 overflow-auto border-b border-[#e6dfd4]">
      {tabs.map((tab) => (
        <button
          key={tab}
          className={`whitespace-nowrap px-3 py-2 text-sm font-semibold ${value === tab ? 'border-b-2 border-[#c4622d] text-[#1a3c34]' : 'text-[#6e675e]'}`}
          onClick={() => onChange(tab)}
        >
          {tab}
        </button>
      ))}
    </div>
  )
}

export function Table({ columns, rows, onRow }: { columns: string[]; rows: ReactNode[][]; onRow?: (index: number) => void }) {
  if (!rows.length) return <Empty text="Nothing to show yet." />
  return (
    <div className="overflow-auto">
      <table className="w-full min-w-[640px] text-left text-sm">
        <thead className="text-xs uppercase tracking-[0.12em] text-[#8c6a32]">
          <tr>{columns.map((column) => <th key={column} className="border-b border-[#e6dfd4] px-3 py-2 font-semibold">{column}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            <tr key={index} className={onRow ? 'cursor-pointer hover:bg-[#f6f1e8]' : ''} onClick={() => onRow?.(index)}>
              {row.map((cell, cellIndex) => <td key={cellIndex} className="border-b border-[#efeae2] px-3 py-2.5 align-top">{cell}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function useForm<T extends Record<string, unknown>>(initial: T) {
  const [values, setValues] = useState(initial)
  function set<K extends keyof T>(key: K, value: T[K]) {
    setValues((current) => ({ ...current, [key]: value }))
  }
  return { values, set, setValues }
}
