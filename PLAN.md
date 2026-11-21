# CityCare Medical Centre: Clinic Appointment & Patient Management System

Implementation plan (Laravel 12 · PHP 8.2 · MariaDB/MySQL · Bootstrap 5 · Font Awesome 6)

---

## 1. Tech stack & packages

| Concern | Choice |
|---|---|
| Framework | Laravel 12 (`composer create-project laravel/laravel:^12.0 .`) |
| Auth | `laravel/breeze` (Blade stack), then Tailwind views rewritten in Bootstrap |
| UI | Bootstrap 5.3 + Font Awesome 6 (npm, bundled with Vite), Chart.js for graphs |
| Roles | `role` column on `users` + `role` middleware + Policies/Gates |
| PDF | `barryvdh/laravel-dompdf` |
| Excel / CSV | `maatwebsite/excel` |
| Images | Laravel `public` disk (`php artisan storage:link`) for profile photos |
| AJAX | `fetch()` against session-authenticated JSON routes (no extra token setup) |
| DB | MariaDB from XAMPP, database `citycare` |

## 2. Roles & access matrix

The brief names five roles. Admin/doctor/patient were in the request, and receptionist and cashier are required by the exam text.

| Feature | Admin | Receptionist | Doctor | Cashier | Patient |
|---|:-:|:-:|:-:|:-:|:-:|
| Dashboard (role-specific) | ✅ | ✅ | ✅ | ✅ | ✅ |
| Manage users / staff | CRUD | – | – | – | – |
| Departments | CRUD | view | view | – | – |
| Doctor profiles | CRUD | view | own | – | view list |
| Consultation schedules | CRUD | view | view own | – | – |
| Patients | CRUD | CRUD | view (own patients) | view | own profile |
| Appointments | CRUD | CRUD + cancel | view own, update status | view | view own |
| Consultation notes / treatment | view | – | CRUD (own) | – | view own history |
| Invoices & payments | CRUD | view | – | CRUD | view own status |
| Reports (PDF/Excel/CSV) | all | daily appts | own schedule | payments | – |
| Profile + photo | ✅ | ✅ | ✅ | ✅ | ✅ |

Enforcement happens in three layers:
1. `EnsureUserHasRole` middleware on route groups (`->middleware('role:admin,receptionist')`)
2. Policies for record ownership (a doctor sees only their appointments, a patient only their own data)
3. `@can` / `@role` Blade checks so the sidebar shows only what's relevant

## 3. Database design

```
users            id, name, email, password, role(enum), phone, photo_path, is_active, timestamps
departments      id, name(unique), description, timestamps
doctors          id, user_id→users, department_id→departments, specialization,
                 license_no(unique), consultation_fee, bio, timestamps
doctor_schedules id, doctor_id→doctors, day_of_week(0-6), start_time, end_time,
                 slot_minutes(default 30), is_active
patients         id, user_id→users, patient_no(unique, e.g. CC-00042), date_of_birth, gender,
                 national_id(unique, nullable), address, blood_group, allergies,
                 emergency_contact_name, emergency_contact_phone, timestamps, softDeletes
appointments     id, patient_id, doctor_id, booked_by→users, appointment_date, start_time, end_time,
                 status(enum: scheduled, checked_in, completed, cancelled, no_show),
                 reason, cancellation_reason, timestamps
                 INDEX(doctor_id, appointment_date)
consultations    id, appointment_id(unique), patient_id, doctor_id, symptoms, diagnosis,
                 treatment, prescription, notes, follow_up_date, timestamps
invoices         id, invoice_no(unique), patient_id, appointment_id(nullable), amount,
                 status(enum: unpaid, partial, paid), due_date, timestamps
payments         id, invoice_id, received_by→users, amount, method(enum: cash, mobile_money,
                 card, insurance), reference, paid_at, notes, timestamps
```

Relationships: User 1–1 Doctor/Patient, Department 1–N Doctors, Doctor 1–N Schedules/Appointments, Patient 1–N Appointments/Invoices, Appointment 1–1 Consultation, Invoice 1–N Payments.

### Business rules that fix the clinic's problems
- **No double-booking.** `AppointmentService::book()` runs in a DB transaction. It locks the doctor's appointments for that date (`lockForUpdate`) and rejects any booking where `start < existing.end AND end > existing.start` among non-cancelled appointments. The slot must also fall inside the doctor's schedule for that weekday. The same check runs on update/reschedule.
- **No duplicate patients.** Unique `national_id` and `email`, plus a phone and name+DOB duplicate warning at registration.
- **Payment tracking.** Completing an appointment auto-creates an invoice for the doctor's fee. Invoice status is recalculated from its payments.
- **Fast history.** The patient detail page shows a timeline of all consultations, and the doctor reaches it in one click from their schedule.

## 4. Features by module (all with CRUD, search, filters, pagination)

| Module | Search / filters |
|---|---|
| Users (admin) | name/email, role, active |
| Departments | name |
| Doctors | name, department, specialization |
| Schedules | doctor, weekday |
| Patients | name, patient_no, phone (instant AJAX search) |
| Appointments | patient, doctor, date range, status, department |
| Consultations | patient, doctor, date |
| Invoices / Payments | patient, status, method, date range |

All list pages use `->paginate(15)->withQueryString()` with reusable query scopes (`scopeSearch`, `scopeFilter`).

## 5. AJAX / API features (three, for safety on the "at least one" requirement)
1. **Dynamic slot loading.** `GET /ajax/doctors/{doctor}/slots?date=YYYY-MM-DD` returns free slots from the schedule minus booked appointments. The booking form refreshes the slot picker when the doctor or date changes.
2. **Instant patient search.** `GET /ajax/patients/search?q=` returns a live-results dropdown on the booking form and the patients page.
3. **Dashboard chart data.** `GET /ajax/dashboard/charts` returns JSON consumed by Chart.js.

## 6. Dashboards & graphs
- **Admin:** cards for patients, doctors, today's appointments, and revenue this month. Charts for appointments over the last 30 days (line), appointments by department (doughnut), monthly revenue for the last 6 months (bar), doctor workload this month (horizontal bar), and appointment status breakdown (pie).
- **Receptionist:** today's appointment list, waiting/checked-in counts, quick-book button.
- **Doctor:** today's schedule, upcoming count, patients seen per week (line).
- **Cashier:** today's collections, unpaid/partial invoices, payment methods (doughnut).
- **Patient:** next appointment, visit history, outstanding balance.

## 7. Reports (PDF / Excel / CSV)
`ReportController` has a filter form, and each report exports through one Export class plus one Blade PDF view:
1. Daily appointment list (date, doctor, department)
2. Doctor schedule (doctor, date range)
3. Payment report (date range, method, cashier), with totals
4. Patient visit report (patient, date range), covering consultations and treatments

## 8. Seed data (realistic enough for the charts)
- 1 admin, 2 receptionists, 2 cashiers
- 6 departments (General Medicine, Paediatrics, Cardiology, Gynaecology, Dermatology, Dental)
- 12 doctors, each with weekday schedules
- 80 patients (Faker; patient users can log in)
- About 600 appointments spread over the past 6 months and the next 2 weeks. They are generated from real free slots, so the seed itself never overlaps. Statuses are weighted: past appointments are mostly completed with some cancelled/no-show, and future ones are scheduled.
- Consultations for completed appointments, invoices for those, and payments (about 75% paid, 15% partial, 10% unpaid)
- Fixed demo logins (password `password`): `admin@citycare.test`, `reception@citycare.test`, `doctor@citycare.test`, `cashier@citycare.test`, `patient@citycare.test`

## 9. UI layout
- One Bootstrap layout (`layouts/app.blade.php`) with a role-aware sidebar that collapses to an offcanvas on mobile, a top navbar with the avatar/profile dropdown, and flash toasts.
- Breeze auth pages (login, register, forgot/reset password, verify email) restyled with Bootstrap cards.
- Reusable Blade components: `x-stat-card`, `x-status-badge`, `x-search-filter`, `x-delete-button` (confirm modal).
- Font Awesome icons throughout (`fa-user-doctor`, `fa-calendar-check`, `fa-file-invoice-dollar`, …).

## 10. Build order (phases)

| # | Phase | Output |
|---|---|---|
| 1 | Setup | Laravel 12, `.env` → MariaDB `citycare`, Breeze (Blade), remove Tailwind, add Bootstrap/FA/Chart.js via Vite |
| 2 | Layout & auth views | Bootstrap layout, sidebar, Breeze views converted |
| 3 | Roles | `role` column, enum, middleware, policies, role-based redirect after login |
| 4 | Profile | Breeze profile extended with phone and photo upload (validate image ≤2MB, replace/delete old file) |
| 5 | Migrations, models, factories | All tables above, relationships, scopes |
| 6 | Admin CRUD | Users, departments, doctors, schedules, patients |
| 7 | Appointments | `AppointmentService` overlap logic, slot AJAX, patient search AJAX, cancel/reschedule |
| 8 | Doctor area | My schedule, patient history, consultation notes CRUD |
| 9 | Billing | Invoices, payments, cashier workflow |
| 10 | Patient portal | Profile, upcoming appointments, history, payment status |
| 11 | Dashboards | Role dashboards + Chart.js |
| 12 | Reports | PDF/Excel/CSV exports |
| 13 | Seeders | `DatabaseSeeder` producing the data above |
| 14 | Tests & polish | Feature tests (overlap rejection, role access 403s, slot API), responsive check, README |

## 11. Key tests
- Booking an overlapping slot for the same doctor fails, and a different doctor at the same time succeeds.
- Booking outside the doctor's schedule fails.
- A patient cannot view another patient's appointment (403).
- A cashier cannot reach admin routes, and so on for each role.
- The slots endpoint excludes booked times.
- A payment updates the invoice status (partial → paid).

## 12. Run commands (after build)
```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve
```
