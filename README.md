# Zentrix Corporation HRIS

Human resources system for Zentrix Corporation. HR runs the company record. Employees use the Employee Self Service portal (ESS) for their own profile, attendance, leave, and payslips.

## Run

```bash
npm install
copy server\.env.example server\.env
npm run dev
```

On macOS or Linux, use `cp server/.env.example server/.env`.

The API starts an embedded PostgreSQL database on port 54330, applies the schema, and listens on [http://localhost:4000](http://localhost:4000). The web app is at [http://localhost:5173](http://localhost:5173). Set a long `JWT_SECRET` in `server/.env` before anyone signs in.

To use your own PostgreSQL server, set `USE_EMBEDDED_POSTGRES=false` and point `DATABASE_URL` at that server.

The database is not filled with sample staff. The first sign-in has to be created in the database or through a user you already have. New employees get an ESS sign-in only when HR ticks **Create login**. The temporary password is shown once on that screen, and the employee must change it at the first sign-in.

## What each person sees

HR, managers, and payroll see the modules their role allows: employees, recruitment, attendance, leave, payroll, performance, training, relations, welfare, contracts, workforce planning, exits, reports, and settings.

An employee sees ESS only: home, profile, attendance, leave, payslips, notifications, and account settings.

## Attendance

Attendance comes from the fingerprint clock. The device posts to `POST /api/time/biometric/punch` with the employee number and the punch time. The first punch of the day is clock-in. A later punch updates clock-out. The device key must match `BIOMETRIC_DEVICE_KEY` in `server/.env`. The system administrator can read the clock address under **System settings → Working hours**.

Reporting time, closing time, and grace minutes are set there. A punch after the grace period is late.

## Payroll

Each month is generated on its own when that month has not already been run. Ghana PAYE uses the bands effective 1 September 2026. Employee SSNIT is 5.5% of basic salary. Employer SSNIT is 13% of basic salary, of which 5% is Tier 2. A bonus, allowance, commission, deduction, or loan is applied to that month and the payslip is recalculated. Employees can download an approved payslip from ESS.

## Documents

Appointment letters and payslips are PDFs. Uploaded files stay in `server/uploads` unless `S3_BUCKET` and its credentials are set. A super administrator can write a JSON snapshot from **System settings → Backup**. A full database copy is `pg_dump` of `DATABASE_URL`.
