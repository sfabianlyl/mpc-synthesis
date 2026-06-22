# MPC Synthesis — Implementation Plan

## Overview

Building on the existing Laravel 13 + React (Inertia v3) + shadcn/ui starter kit, this plan implements the full Synthesis Web App as specified in `development_plan.md`.

## Architecture Summary

| Layer | Technology | Notes |
|---|---|---|
| Frontend | React 19 + Inertia v3 + Tailwind v4 + shadcn/ui | Already installed |
| Backend | Laravel 13 + Fortify | Already installed |
| Database | SQLite (dev) → PostgreSQL (prod) | Currently SQLite |
| LLM | Local Ollama (primary) → Gemini API (failover) | New service |
| PDF Export | dompdf (backend) | New dependency |

## Implementation Phases

### Phase 1: Database & Models
1. Add `role` column to existing `users` migration
2. Create `groups` migration & model
3. Create `responses` migration & model
4. Create `synthesis_reports` migration & model
5. Create `final_documents` migration & model
6. Create seeders for test data

### Phase 2: Auth & Role-Based Access
1. Add `role` field to User model
2. Create `RoleMiddleware` for route protection
3. Update Fortify registration to assign roles
4. Create registration page (currently missing)

### Phase 3: Responder Portal (Interface 1)
1. `ResponseController` — store consensus paragraphs
2. React page: single-field submission form
3. Group selection or auto-assignment

### Phase 4: Secretary Dashboard (Interface 2)
1. `SynthesisController` — CRUD synthesis reports
2. `LlmService` — failover service (Ollama → Gemini)
3. React page: view assigned responses, trigger LLM analysis, review/edit

### Phase 5: Master Synthesis & Export (Interface 3)
1. `MasterSynthesisController` — aggregate all reports
2. `ExportController` — PDF generation
3. React page: drill-down aggregated view, global synthesis, export

---

## Files to Create/Modify

### Backend (PHP)
- `database/migrations/xxxx_add_role_to_users.php`
- `database/migrations/xxxx_create_groups_table.php`
- `database/migrations/xxxx_create_responses_table.php`
- `database/migrations/xxxx_create_synthesis_reports_table.php`
- `database/migrations/xxxx_create_final_documents_table.php`
- `app/Models/Group.php`
- `app/Models/Response.php`
- `app/Models/SynthesisReport.php`
- `app/Models/FinalDocument.php`
- `app/Models/User.php` (modify)
- `app/Http/Middleware/RoleMiddleware.php`
- `app/Http/Controllers/ResponseController.php`
- `app/Http/Controllers/SynthesisController.php`
- `app/Http/Controllers/MasterSynthesisController.php`
- `app/Http/Controllers/ExportController.php`
- `app/Services/LlmService.php`
- `routes/web.php` (modify)
- `config/services.php` (modify for LLM config)
- `database/seeders/DatabaseSeeder.php` (modify)

### Frontend (React/TSX)
- `resources/js/types/models.ts`
- `resources/js/pages/responder/submit.tsx`
- `resources/js/pages/secretary/dashboard.tsx`
- `resources/js/pages/secretary/review.tsx`
- `resources/js/pages/master/dashboard.tsx`
- `resources/js/pages/master/export.tsx`
- `resources/js/components/app-sidebar.tsx` (modify)
- `resources/js/app.tsx` (modify)
- `resources/js/types/global.d.ts` (modify)
