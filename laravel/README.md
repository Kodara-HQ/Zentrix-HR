# Zentrix HR (Laravel)

The PHP + MySQL version of Zentrix HR. It replaces the React client and Node server in `../client` and `../server`, module by module.

## Run it locally (XAMPP)

1. Start MySQL in the XAMPP control panel.
2. In this folder:

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

Open http://localhost:8000. `.env` expects a MySQL database called `zentrix_hr` with user `root` and no password, which is the XAMPP default. Create the database first if it does not exist.

The database starts with no people. Either load the sample company below or create the system administrator:

```bash
php artisan hr:admin you@example.com
```

That prints a temporary password, which must be changed at the first sign-in.

### Several companies

One installation serves several companies. Each company sees only its own staff, settings and records. The **system administrator** (created by `hr:admin`, or an existing account turned into one with `php artisan hr:system-admin <username>`) belongs to no company: they sign in to **Companies**, set up each company together with its first super administrator, and can **Open** any company to work inside it. Only the system administrator can set up or suspend a company.

On Vercel, after a deploy that changes the database, call `https://<your domain>/cron/migrate` with the header `Authorization: Bearer <CRON_SECRET>`. If it stops part-way, call it again; it carries on.

## Test sign-ins

```bash
php artisan db:seed --class=DemoSeeder
```

adds a sample company with 18 staff and a test administrator. **Every test account uses the password `Hris@2026`.** Never load the demo data on a live system.

| Sign in as | Role | Sees |
| --- | --- | --- |
| `test` | Super administrator | Everything, not linked to a staff record |
| `efua.boateng@zentrix.gh` | Super administrator | Everything, with her own ESS record |
| `kojo.mensah@zentrix.gh` | HR manager | All staff and HR modules |
| `akosua.darko@zentrix.gh` | HR administrator | All staff and HR modules |
| `yaw.owusu@zentrix.gh` | Payroll officer | Staff and pay, no recruitment |
| `abena.sarpong@zentrix.gh` | Recruitment officer | Recruitment |
| `kofi.asante@zentrix.gh` | Department manager | The Operations department |
| `ama.adjei@zentrix.gh` | Supervisor | Her direct reports |
| `kwame.boateng@zentrix.gh` | Employee | ESS only: own profile, leave, attendance, payslips |

`yaw.darko@zentrix.gh` has resigned, so that account is switched off.

## Monthly payroll

Each day at 06:00 the scheduler calculates the current month's payroll if nobody has yet. The run waits for an HR administrator or HR manager to approve it; approving makes the payslips final, releases them to employees, and takes loan repayments off balances. Bonuses, deductions and loans for an approved month are refused, so add them to the next month.

Keep the scheduler running alongside the app:

```bash
php artisan schedule:work
```

On a server, add a cron entry for `php artisan schedule:run` every minute instead.

## Hosting online (Railway)

The `Dockerfile` builds a production image with PHP 8.2 and Apache. On every start the container updates the database, loads the reference settings and runs the scheduler, so nothing needs to be done by hand after a deploy.

1. Sign in at railway.com with GitHub and create a project from the `Kodara-HQ/Zentrix-HR` repository.
2. On the new service: set **Root directory** to `laravel` and **Branch** to `laravel`. Railway reads `railway.json` and builds the `Dockerfile`.
3. In the project, add a **MySQL** database.
4. On the app service, add a **Volume** mounted at `/var/www/html/storage`, so uploaded documents, letters and payslips survive redeploys.
5. Set these variables on the app service:

   | Variable | Value |
   | --- | --- |
   | `APP_KEY` | the output of `php artisan key:generate --show`, run once on your computer |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | the https address Railway gives the service |
   | `DB_CONNECTION` | `mysql` |
   | `DB_URL` | `${{MySQL.MYSQL_URL}}` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `LOG_CHANNEL` | `stderr` |
   | `BIOMETRIC_DEVICE_KEY` | the key the fingerprint clock will send (optional) |

6. Under **Networking**, generate a domain (or add your own). Open it; the first start takes a minute while the database is set up.
7. Create the system administrator from the service's shell: `php artisan hr:admin you@example.com`. Do not load `DemoSeeder` on the live system.

For email notifications, also set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS`. The fingerprint clock must reach `https://<your domain>/api/time/biometric/punch` over the internet.

The same image runs on Render or Fly.io; point `DB_URL` at any MySQL 8 or MariaDB 10.4+ database and give `storage/` a persistent disk.

## Tests

```bash
php artisan test
```

The tests use a separate MySQL database, `zentrix_hr_test`, so create that too.

## Sign-in photo

The sign-in page shows `public/images/login.jpg`. Replace that file to change the picture.
