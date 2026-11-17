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

The database starts with no people. Either load the sample company below or create the first real administrator:

```bash
php artisan hr:admin you@example.com
```

That prints a temporary password, which must be changed at the first sign-in.

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

## Tests

```bash
php artisan test
```

The tests use a separate MySQL database, `zentrix_hr_test`, so create that too.

## Sign-in photo

The sign-in page shows `public/images/login.jpg`. Replace that file to change the picture.
