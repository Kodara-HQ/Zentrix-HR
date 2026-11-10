# Meridian HRIS

Human resource information system for the full employee lifecycle: records, recruitment, attendance, leave, Ghana payroll, performance, training, relations, welfare, contracts, workforce planning, and exits.

## Run

```bash
npm install
npm run dev
```

The API starts an embedded PostgreSQL database on port 54329 the first time, creates the schema, and loads Meridian Logistics Ghana Limited. The web app is at [http://localhost:5173](http://localhost:5173). The API is at [http://localhost:4000](http://localhost:4000).

To use your own PostgreSQL server, set `USE_EMBEDDED_POSTGRES=false` and `DATABASE_URL` in `server/.env`, then run `npm run dev`.

## Sample sign-in

Password for every sample account: `Hris@2026`

| Person | Role | Email |
| --- | --- | --- |
| Efua Boateng | Super administrator | efua.boateng@meridian.gh |
| Akosua Darko | HR administrator | akosua.darko@meridian.gh |
| Kojo Mensah | HR manager | kojo.mensah@meridian.gh |
| Kofi Asante | Department manager | kofi.asante@meridian.gh |
| Ama Adjei | Supervisor | ama.adjei@meridian.gh |
| Yaw Owusu | Payroll officer | yaw.owusu@meridian.gh |
| Abena Sarpong | Recruitment officer | abena.sarpong@meridian.gh |
| Kwame Boateng | Employee | kwame.boateng@meridian.gh |

## Payroll

Monthly PAYE uses the Ghana Revenue Authority bands effective 1 September 2026. Chargeable income is gross taxable pay minus the employee SSNIT contribution of 5.5% of basic salary. The employer pays 13% of basic salary: 8% toward Tier 1 and 5% toward Tier 2. Rates are stored with effective dates and can be replaced from System Settings without rewriting old payslips.

## Biometric attendance

`POST /api/time/biometric/punch` with `{ "deviceKey": "device-demo-key", "employeeNo": "EMP-0010", "timestamp": "2026-11-09T08:05:00.000Z" }`.

## Documents and backup

Uploads stay on the server unless `S3_BUCKET` and credentials are set in `server/.env`. A super administrator can write a JSON snapshot from System Settings. A full database copy is `pg_dump` of `DATABASE_URL`.
