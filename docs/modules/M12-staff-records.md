# M12 — Staff records and employment

| | |
|---|---|
| **Client module** | 12 — Staff records and employment |
| **Features** | 12.1–12.17 |
| **Depends on** | M13 (staff are access-controlled users), M09 (the `notes` table and `NotesPanel`) |
| **Tier** | **Pro** |
| **Engine** | no |
| **Critical review** | no (but PII: the DoB, address and documents are sensitive) |

## Goal

A complete employee file per member of staff: their account, personal and contact details,
emergency contact, contract, statutory numbers, shift, pay definition, recurring pay items and
deductions, documents, and notes, all with full change history. Staff are WordPress users (role
`rtbp_staff`), and the employee file is a row linked to the user.

**D1 note:** fields 12.12–12.15 (pay schedule, pay definition, additional pay, deductions) feed
payroll. If D1 = *payroll out of scope*, build them anyway, hidden behind the `payroll.enabled`
setting (they are small). Or defer them: decide when starting the module.

## Scope

| Feature | Summary | Task |
|---|---|---|
| 12.1 | Employee list: name, job title, shift, active state, access role | T2 |
| 12.2 | Add an employee: creates the WP user (role `rtbp_staff`), the employee row, an access role, and an optional PIN | T1, T3 |
| 12.3 | Edit any part of the record (tabbed detail screen) | T3 |
| 12.4 | Active / inactive: deactivating blocks sign-in (`authenticate` filter) and ends sessions; history stays. **Separate from "banned"**, which the legacy system conflated | T1 |
| 12.5 | Account: username, password (set / reset link), PIN (M13 API) | T3 |
| 12.6, 12.7 | Personal: first and last name, date of birth, address, marital status, number of children. Contact: phone (required), e-mail | T1, T3 |
| 12.8 | Emergency contact: full name, address, phone, relationship | T1, T3 |
| 12.9 | Contract: status (active / terminated), job type, job title, department, hire date, end date; a contract history (one row per contract) | T4 |
| 12.10 | Statutory identifiers: CNPS number, tax number (masked in lists) | T1 |
| 12.11 | Work shift assignment (shifts are managed in M15; until then, a plain list) | T4 |
| 12.12, 12.13 | Pay schedule + pay definition (hourly / salaried, amount, period) | T4 |
| 12.14 | Additional pay types: overtime, paid time off, unpaid time off, sick pay, vacation pay, bonus, commission, transport allowance, gratification | T4 |
| 12.15 | Recurring deductions and garnishments | T4 |
| 12.16 | Documents: upload, list, download, delete (protected storage, ADR-009) | T5 |
| 12.17 | Notes (shared `notes` table, types general/caution/warning) + the change history (`ActivityTimeline`) | T5 |

## Data

| Table | Key columns |
|---|---|
| `employees` | `id`, `user_id` (unique), `employee_no` (Sequence), `first_name`, `last_name`, `dob`, `address`, `marital_status`, `num_children`, `phone`, `phone_e164`, `email`, `emergency` (JSON: full_name, address, phone, relationship), `cnps_no`, `tax_no`, `department`, `job_title`, `shift_id`, `pay_schedule_id`, `pay_type` (hourly/salary), `pay_amount` DECIMAL(12,2), `pay_period` (week/month/year), `is_active`, `deactivated_at`, `avatar_id`, timestamps |
| `employee_contracts` | `id`, `employee_id`, `status` (active/terminated), `job_type`, `job_title`, `department`, `start_date`, `end_date`, `file_token`, `notes`, timestamps |
| `employee_pay_items` | `id`, `employee_id`, `kind` (earning/deduction/garnishment), `code`, `label`, `calc` (amount/percent/hours), `value`, `frequency` (per_payslip/per_month), `max_percent`, `active`, timestamps |
| `employee_documents` | `id`, `employee_id`, `title`, `category`, `file_token`, `mime`, `size`, `uploaded_by`, `created_at` |

Enums (from the legacy system): marital `single|married|divorced|separated|widowed`;
relationship `father|mother|spouse|brother|sister|friend|relative|guardian|other`; job type
`full_time|part_time|contract|internship|other`.

## API

| Method | Route | Access key |
|---|---|---|
| GET | `employees` | `page.staff` |
| POST | `employees` | `staff.create` |
| GET/PUT | `employees/{id}` | `page.staff` / `staff.edit` |
| POST | `employees/{id}/deactivate` · `/activate` | `staff.deactivate` |
| CRUD | `employees/{id}/contracts` | `staff.contracts` |
| CRUD | `employees/{id}/pay-items` | `staff.edit` |
| CRUD | `employees/{id}/documents` (+ download) | `staff.edit` |
| notes | `notes?type=employee&id=` | `staff.note_*` |

## Activity log actions

`staff.create`, `staff.edit` (before/after; DoB, address and IDs masked), `staff.deactivate`,
`staff.activate`, `staff.contract_*`, `staff.pay_item_*`, `staff.document_upload|delete`,
`staff.note_*`.

## Tasks

- [ ] T1 [Pro] Tables + `EmployeeService` (create user + employee in one transaction, update with diff logging, activate/deactivate with a sign-in block and session kill); register the access keys
- [ ] T2 [Pro] Employee list screen
- [ ] T3 [Pro] Employee detail: Account, Personal, Contact, Emergency tabs + the create wizard
- [ ] T4 [Pro] Employment tab: contracts, shift, pay schedule, pay definition, pay items (hidden when `payroll.enabled` is off)
- [ ] T5 [Pro] Documents tab + Notes and History tab

## Acceptance

1. Create an employee with a role and a PIN. They can sign in and see only what their role allows.
2. Deactivate them. Their session ends, they cannot sign in, and their bookings still show them as author.
3. Upload a contract PDF. Its URL is not publicly reachable, and the download works for a manager.
4. Edit the address. The history shows before and after, masked.

## Legacy reference

`legacy-reference.md` → Module 12.

## Migration notes

Legacy `staff` users → role `rtbp_staff` + `employees`. The `staff_*` user meta, the
`staff_em_contact` JSON, the `hbfwc_payroll_contracts` table, the `staff_deductions` and
`staff_additional_pays` meta, and the notes from `hbfwc_payroll_notes` (where `user_role` = staff)
all come across. The per-year balance meta (`staff_pto_{Y}` …) goes to M15.

## Progress notes
