import { useEffect, useState } from 'react'
import { Camera } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../lib/api'
import { formatDate, fullName, money, titleCase, wholeDays } from '../lib/format'
import { useLookups } from '../lib/lookups'
import { can, useAuth } from '../auth'
import { Badge, Button, ErrorNote, Field, Modal, Panel, SearchSelect, Tabs, controlClass, statusTone } from '../components/ui'

type Profile = {
  id: string
  employeeNo: string
  firstName: string
  lastName: string
  gender: string
  status: string
  email: string
  phone: string
  address?: string
  city?: string
  dateOfBirth: string
  dateOfEmployment: string
  probationEndDate?: string
  basicSalary?: number
  ssnitNumber?: string
  tinNumber?: string
  photoFileId?: string
  emergencyName?: string
  emergencyPhone?: string
  nextOfKinName?: string
  nextOfKinPhone?: string
  department?: { name: string }
  jobTitle?: { name: string }
  employmentType?: { name: string }
  location?: { name: string; city: string }
  manager?: { firstName: string; lastName: string }
  education?: Array<{ id: string; institution: string; qualification: string; endYear?: number }>
  skills?: Array<{ id: string; name: string; level: string }>
  qualifications?: Array<{ id: string; name: string; institution?: string }>
  experience?: Array<{ id: string; employer: string; role: string }>
  bankAccounts?: Array<{ id: string; bankName: string; accountNumber: string }>
  events?: Array<{ id: string; title: string; effectiveDate: string; description?: string; type: string }>
  contracts?: Array<{ id: string; title: string; startDate: string; endDate?: string; status: string }>
  letters?: Array<{ id: string; subject: string; issuedOn: string; fileAssetId?: string }>
  leaveBalances?: Array<{ id: string; entitled: string; used: string; pending: string; leaveType: { name: string } }>
  payItems?: Array<{ id: string; amount: string; component: { name: string } }>
}

export function EmployeeProfilePage() {
  const { id = '' } = useParams()
  const { user } = useAuth()
  const lookups = useLookups()
  const [profile, setProfile] = useState<Profile | null>(null)
  const [tab, setTab] = useState('Overview')
  const [error, setError] = useState('')
  const [action, setAction] = useState<string | null>(null)
  const [note, setNote] = useState('')
  const [jobTitleId, setJob] = useState('')
  const [salary, setSalary] = useState('')
  const [departmentId, setDepartment] = useState('')
  const [locationId, setLocation] = useState('')
  const [skill, setSkill] = useState({ name: '', level: 'Proficient' })
  const [education, setEducation] = useState({ institution: '', qualification: '' })
  const [photoError, setPhotoError] = useState('')
  const [uploadingPhoto, setUploadingPhoto] = useState(false)

  async function load() {
    setProfile(await api<Profile>(`/employees/${id}`))
  }
  useEffect(() => { load().catch((err) => setError(err.message)) }, [id])

  const writer = can(user?.role, ['HR_ADMIN', 'HR_MANAGER'])
  const canPhoto = writer || user?.employeeId === profile?.id

  async function uploadPhoto(file: File) {
    if (!profile) return
    setPhotoError('')
    if (!file.type.startsWith('image/')) {
      setPhotoError('Choose a JPEG, PNG, or WebP image.')
      return
    }
    if (file.size > 5 * 1024 * 1024) {
      setPhotoError('The picture must be 5 MB or smaller.')
      return
    }
    setUploadingPhoto(true)
    try {
      const form = new FormData()
      form.set('file', file)
      form.set('ownerType', 'EMPLOYEE')
      form.set('ownerId', profile.id)
      await api('/files?photo=1', { method: 'POST', form })
      await load()
    } catch (err) {
      setPhotoError(err instanceof Error ? err.message : 'The picture could not be saved.')
    } finally {
      setUploadingPhoto(false)
    }
  }

  if (!profile) return <p className="text-sm text-[#6e675e]">{error || 'Loading employee…'}</p>

  return (
    <div>
      <Link to={user?.role === 'EMPLOYEE' ? '/' : '/employees'} className="text-sm text-[#c4622d]">{user?.role === 'EMPLOYEE' ? 'Back to home' : 'Back to employees'}</Link>
      <div className="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-4">
          {canPhoto ? (
            <label className="group relative h-20 w-20 shrink-0 cursor-pointer" title={profile.photoFileId ? 'Click to update photo' : 'Click to upload photo'}>
              <input className="sr-only" type="file" accept="image/jpeg,image/png,image/webp" disabled={uploadingPhoto} onChange={(event) => { const file = event.target.files?.[0]; event.target.value = ''; if (file) uploadPhoto(file) }} />
              {profile.photoFileId ? (
                <img src={`/api/files/${profile.photoFileId}`} alt="" className="h-20 w-20 rounded-full border border-[#e6dfd4] object-cover" />
              ) : (
                <div className="grid h-20 w-20 place-items-center rounded-full bg-[#1a3c34] text-xl font-semibold text-white">{profile.firstName[0]}{profile.lastName[0]}</div>
              )}
              <span className="absolute inset-0 grid place-items-center rounded-full bg-[#1a3c34]/75 px-2 text-center text-[11px] font-semibold leading-tight text-white opacity-0 transition group-hover:opacity-100">
                {uploadingPhoto ? 'Saving…' : profile.photoFileId ? 'Update photo' : 'Upload photo'}
              </span>
              <span className="absolute -bottom-1 -right-1 grid h-7 w-7 place-items-center rounded-full border-2 border-[#f6f1e8] bg-[#c4622d] text-white">
                <Camera size={14} />
              </span>
            </label>
          ) : profile.photoFileId ? (
            <img src={`/api/files/${profile.photoFileId}`} alt="" className="h-20 w-20 rounded-full border border-[#e6dfd4] object-cover" />
          ) : (
            <div className="grid h-20 w-20 place-items-center rounded-full bg-[#1a3c34] text-xl font-semibold text-white">{profile.firstName[0]}{profile.lastName[0]}</div>
          )}
          <div>
            <h1 className="text-2xl font-semibold">{fullName(profile)}</h1>
            <p className="text-sm text-[#6e675e]">{profile.employeeNo} · {profile.jobTitle?.name} · {profile.department?.name}</p>
            <Badge tone={statusTone(profile.status)}>{titleCase(profile.status)}</Badge>
            {photoError && <p className="mt-1 text-xs text-[#9f2d2d]">{photoError}</p>}
          </div>
        </div>
        {writer && (
          <div className="flex flex-wrap gap-2">
            {['PROMOTE', 'TRANSFER', 'SUSPEND', 'REINSTATE', 'DEACTIVATE'].map((item) => (
              <Button key={item} tone={item === 'DEACTIVATE' ? 'danger' : 'ghost'} small onClick={() => setAction(item)}>{titleCase(item)}</Button>
            ))}
          </div>
        )}
      </div>
      <div className="mt-4">
        <Tabs tabs={['Overview', 'Qualifications', 'Compensation', 'Documents', 'History']} value={tab} onChange={setTab} />
      </div>
      {tab === 'Overview' && (
        <div className="grid gap-4 md:grid-cols-2">
          <Panel>
            <h2 className="mb-2 font-semibold">Personal</h2>
            <dl className="grid grid-cols-2 gap-2 text-sm">
              <div>Gender<div className="font-semibold">{titleCase(profile.gender)}</div></div>
              <div>Date of birth<div className="font-semibold">{formatDate(profile.dateOfBirth)}</div></div>
              <div>Phone<div className="font-semibold">{profile.phone}</div></div>
              <div>Email<div className="font-semibold">{profile.email}</div></div>
              <div className="col-span-2">Address<div className="font-semibold">{profile.address || '—'}, {profile.city}</div></div>
              <div>Emergency<div className="font-semibold">{profile.emergencyName || '—'} {profile.emergencyPhone}</div></div>
              <div>Next of kin<div className="font-semibold">{profile.nextOfKinName || '—'}</div></div>
            </dl>
          </Panel>
          <Panel>
            <h2 className="mb-2 font-semibold">Job</h2>
            <dl className="grid grid-cols-2 gap-2 text-sm">
              <div>Title<div className="font-semibold">{profile.jobTitle?.name}</div></div>
              <div>Type<div className="font-semibold">{profile.employmentType?.name}</div></div>
              <div>Location<div className="font-semibold">{profile.location?.name}, {profile.location?.city}</div></div>
              <div>Manager<div className="font-semibold">{profile.manager ? fullName(profile.manager) : '—'}</div></div>
              <div>Hired<div className="font-semibold">{formatDate(profile.dateOfEmployment)}</div></div>
              <div>Probation<div className="font-semibold">{formatDate(profile.probationEndDate)}</div></div>
            </dl>
          </Panel>
          <Panel className="md:col-span-2">
            <h2 className="mb-2 font-semibold">Leave balances this year</h2>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
              {(profile.leaveBalances || []).map((balance) => (
                <div key={balance.id} className="rounded-xl bg-[#f3efe6] px-3 py-2 text-sm">
                  <div className="font-semibold">{balance.leaveType.name}</div>
                  <div className="text-[#6e675e]">{wholeDays(Number(balance.entitled) - Number(balance.used) - Number(balance.pending))} days left · {wholeDays(balance.used)} used</div>
                </div>
              ))}
            </div>
          </Panel>
        </div>
      )}
      {tab === 'Qualifications' && (
        <div className="grid gap-4 lg:grid-cols-2">
          <Panel>
            <h2 className="mb-2 font-semibold">Education</h2>
            {(profile.education || []).map((item) => <p key={item.id} className="text-sm">{item.qualification}, {item.institution}</p>)}
            {writer && (
              <form className="mt-3 grid gap-2" onSubmit={async (event) => {
                event.preventDefault()
                await api(`/employees/${id}/education`, { method: 'POST', body: education })
                setEducation({ institution: '', qualification: '' })
                await load()
              }}>
                <input className={controlClass} placeholder="Institution" value={education.institution} onChange={(event) => setEducation({ ...education, institution: event.target.value })} required />
                <input className={controlClass} placeholder="Qualification" value={education.qualification} onChange={(event) => setEducation({ ...education, qualification: event.target.value })} required />
                <Button type="submit" small>Add education</Button>
              </form>
            )}
          </Panel>
          <Panel>
            <h2 className="mb-2 font-semibold">Skills</h2>
            {(profile.skills || []).map((item) => <p key={item.id} className="text-sm">{item.name} · {item.level}</p>)}
            <form className="mt-3 grid gap-2" onSubmit={async (event) => {
              event.preventDefault()
              await api(`/employees/${id}/skills`, { method: 'POST', body: skill })
              setSkill({ name: '', level: 'Proficient' })
              await load()
            }}>
              <input className={controlClass} placeholder="Skill" value={skill.name} onChange={(event) => setSkill({ ...skill, name: event.target.value })} required />
              <Button type="submit" small>Add skill</Button>
            </form>
            <h2 className="mb-2 mt-4 font-semibold">Experience</h2>
            {(profile.experience || []).map((item) => <p key={item.id} className="text-sm">{item.role}, {item.employer}</p>)}
            <h2 className="mb-2 mt-4 font-semibold">Qualifications</h2>
            {(profile.qualifications || []).map((item) => <p key={item.id} className="text-sm">{item.name}</p>)}
          </Panel>
        </div>
      )}
      {tab === 'Compensation' && (
        <Panel>
          {profile.basicSalary == null ? <p className="text-sm">Compensation is restricted for your role.</p> : (
            <>
              <p className="text-sm">Basic salary <strong>{money(profile.basicSalary)}</strong></p>
              <p className="mt-1 text-sm">SSNIT {profile.ssnitNumber || '—'} · TIN {profile.tinNumber || '—'}</p>
              <ul className="mt-3 text-sm">{(profile.payItems || []).map((item) => <li key={item.id}>{item.component.name}: {money(item.amount)}</li>)}</ul>
              <ul className="mt-3 text-sm">{(profile.bankAccounts || []).map((item) => <li key={item.id}>{item.bankName} · {item.accountNumber}</li>)}</ul>
            </>
          )}
        </Panel>
      )}
      {tab === 'Documents' && (
        <Panel>
          <ul className="space-y-2 text-sm">
            {(profile.contracts || []).map((item) => <li key={item.id}>{item.title} · {formatDate(item.startDate)} to {formatDate(item.endDate)} · {item.status}</li>)}
            {(profile.letters || []).map((item) => (
              <li key={item.id}><a className="text-[#c4622d]" href={item.fileAssetId ? `/api/files/${item.fileAssetId}` : '#'}>{item.subject}</a> · {formatDate(item.issuedOn)}</li>
            ))}
          </ul>
          <DocumentList ownerId={profile.id} photoFileId={profile.photoFileId} />
        </Panel>
      )}
      {tab === 'History' && (
        <Panel>
          <ul className="space-y-3 text-sm">
            {(profile.events || []).map((item) => (
              <li key={item.id}><span className="font-semibold">{item.title}</span> · {formatDate(item.effectiveDate)}<div className="text-[#6e675e]">{item.description}</div></li>
            ))}
          </ul>
        </Panel>
      )}
      {action && (
        <Modal title={titleCase(action)} wide onClose={() => setAction(null)}>
          <form onSubmit={async (event) => {
            event.preventDefault()
            try {
              await api(`/employees/${id}/actions`, {
                method: 'POST',
                body: {
                  type: action,
                  jobTitleId: jobTitleId || undefined,
                  basicSalary: salary ? Number(salary) : undefined,
                  departmentId: departmentId || undefined,
                  locationId: locationId || undefined,
                  effectiveDate: new Date().toISOString().slice(0, 10),
                  notes: note,
                },
              })
              setAction(null)
              await load()
            } catch (err) { setError(err instanceof Error ? err.message : 'Could not update the record') }
          }}>
            <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2">
              {action === 'PROMOTE' && (
                <>
                  <Field label="New job title"><SearchSelect required value={jobTitleId} onChange={setJob} options={lookups?.jobTitles.map((item) => ({ value: item.id, label: item.name })) || []} /></Field>
                  <Field label="New basic salary"><input className={controlClass} type="number" value={salary} onChange={(event) => setSalary(event.target.value)} required /></Field>
                </>
              )}
              {action === 'TRANSFER' && (
                <>
                  <Field label="Department"><SearchSelect value={departmentId} onChange={setDepartment} options={lookups?.departments.map((item) => ({ value: item.id, label: item.name })) || []} /></Field>
                  <Field label="Location"><SearchSelect value={locationId} onChange={setLocation} options={lookups?.locations.map((item) => ({ value: item.id, label: item.name })) || []} /></Field>
                </>
              )}
              <div className="sm:col-span-2"><Field label="Notes"><textarea className={controlClass} rows={3} value={note} onChange={(event) => setNote(event.target.value)} /></Field></div>
            </div>
            <div className="mt-3"><ErrorNote text={error} /></div>
            <div className="mt-4"><Button type="submit">Confirm</Button></div>
          </form>
        </Modal>
      )}
    </div>
  )
}

function DocumentList({ ownerId, photoFileId }: { ownerId: string; photoFileId?: string }) {
  const [files, setFiles] = useState<Array<{ id: string; fileName: string }>>([])
  const lookups = useLookups()
  async function load() {
    const rows = await api<Array<{ id: string; fileName: string }>>(`/files?ownerType=EMPLOYEE&ownerId=${ownerId}`)
    setFiles(rows.filter((file) => file.id !== photoFileId))
  }
  useEffect(() => { load().catch(() => undefined) }, [ownerId, photoFileId])
  return (
    <div className="mt-4">
      <h3 className="font-semibold">Uploaded documents</h3>
      <ul className="mt-2 text-sm">{files.map((file) => <li key={file.id}><a className="text-[#c4622d]" href={`/api/files/${file.id}`}>{file.fileName}</a></li>)}</ul>
      <input className="mt-3 text-sm" type="file" onChange={async (event) => {
        const file = event.target.files?.[0]
        if (!file) return
        const form = new FormData()
        form.set('file', file)
        form.set('ownerType', 'EMPLOYEE')
        form.set('ownerId', ownerId)
        if (lookups?.categories[0]) form.set('categoryId', lookups.categories[0].id)
        await api('/files', { method: 'POST', form })
        await load()
      }} />
    </div>
  )
}
