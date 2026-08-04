# TassProject — LeadCRM AI

An AI-powered lead generation and CRM platform built as a full-stack
Bachelor's project. It discovers leads with a Google + Hunter.io scraper,
scores and approves them, runs automated multi-channel outreach sequences
(email + SMS) with AI-personalized copy, tracks replies with sentiment
analysis, and turns everything into actionable analytics dashboards.

![Stack](https://img.shields.io/badge/Backend-Laravel%2011%20PHP%208.2-informational)
![Stack](https://img.shields.io/badge/Frontend-Angular%2021%20TypeScript-informational)
![Stack](https://img.shields.io/badge/Jobs-BullMQ%20Node%20Redis-informational)
![Stack](https://img.shields.io/badge/Scraper-Python-informational)

---

## Table of contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Architecture](#architecture)
- [Quick start (Docker)](#quick-start-docker)
- [Local development (5 services)](#local-development-5-services)
- [Configuration](#configuration)
- [Demo accounts](#demo-accounts)
- [Running the tests](#running-the-tests)
- [Project structure](#project-structure)
- [Deployment](#deployment)
- [Academic context](#academic-context)

---

## Features

| Area | What it does |
| --- | --- |
| **Lead Finder** | Target profiles → Google search queries → lead discovery. Hunter.io email enrichment with confidence scoring, dedup, and an ML match score per lead. |
| **Lead Queue** | Manual + automatic approval of scraped leads. Score-driven auto-approval keeps the queue clean while everything above threshold skips straight to outreach. |
| **Contacts & Pipeline** | Single view of every lead through the 6-stage pipeline (`lead_found → approved → contacted → replied → interested → won/lost`). Drag-and-drop pipeline board. |
| **Sequences** | Multi-step outreach templates with placeholders (`{{name}}`, `{{company}}`), step delays, and email/SMS channels. |
| **Campaigns** | Enroll contacts, schedule sends via Redis/BullMQ, per-contact progress, failure handling. |
| **AI Outreach** | LLM personalization of each message just before send (OpenAI), with an offline mock (template merge + varied opening lines) when no API key is set. |
| **Replies & Sentiment** | Inbox for replies, automatic sentiment classification (Positive / Neutral / Negative), auto task creation for positive & interested replies. |
| **Tasks** | Per-contact follow-up tasks. Created automatically from positive/interested replies or manually by admins/reps. |
| **Settings** | Integration status cards (SendGrid, Twilio, Hunter, LLM), automation thresholds, user & role management (admin / rep / readonly). |
| **Analytics** | KPI cards, 14-day timeline, channel & status donuts, pipeline funnel, campaign comparison, top companies, sentiment bars, best-send-time card. Zero-dependency SVG charts. |
| **Demo mode** | Every external integration (email, SMS, LLM, Hunter, Google) falls back to a safe mock when its API key is missing, so the whole platform runs end-to-end with no credentials. |

## Tech stack

- **Backend:** Laravel 11 (PHP 8.2), Sanctum token auth, role middleware, MySQL / Postgres / SQLite
- **Frontend:** Angular 21, standalone components, signals, zero-dependency SVG charts
- **Jobs:** Node.js + BullMQ + Redis (job service API + workers)
- **Scraper:** Python 3, Google search, Hunter.io enrichment, scikit-learn match scoring
- **Infra:** Docker Compose, Render (docker + node services)

## Architecture

```
┌─────────────┐      ┌──────────────────┐      ┌──────────────┐
│   Angular   │ HTTP │      Laravel     │      │  Python      │
│   (SPA)     │ ───▶ │  API + Sanctum   │      │  Scraper     │
└─────────────┘      └────────┬─────────┘      └──────┬───────┘
                              │                       │
                              │ HTTP (internal secret)│
                              ▼                       ▼
                    ┌────────────────────┐    ┌───────────────┐
                    │  job-service (Node)│    │ MySQL / Redis │
                    │  BullMQ workers    │◀───┘               │
                    └────────────────────┘
```

- Laravel owns the data and exposes the JSON API.
- The scraper reads pending jobs straight from the DB, discovers/enriches leads, and inserts them into the lead queue.
- Laravel calls the job-service to schedule sends; BullMQ workers pick them up, personalize with the LLM (or mock), and deliver via SendGrid / Twilio (or mock).

## Quick start (Docker)

> Docker Desktop must be running.

```bash
docker compose up --build
```

Then open **http://localhost:8000** — the backend container builds the
Angular SPA and serves it, along with the API, on one port. MySQL is
exposed on `3307` and Redis on `6379`.

Services started:

| Service | Container | URL / port |
| --- | --- | --- |
| Laravel API + SPA | `tassproject-backend` | http://localhost:8000 |
| MySQL 8 | `tassproject-mysql` | 127.0.0.1:3307 |
| Redis 7 | `tassproject-redis` | 127.0.0.1:6379 |
| Python scraper | `tassproject-scraper` | — |
| Job service API | `tassproject-job-service` | http://localhost:4000 |
| BullMQ workers | `tassproject-job-workers` | — |

Seed demo users and demo data (optional, on first run):

```bash
docker compose exec backend php artisan db:seed --class=UserSeeder --force
docker compose exec backend php artisan db:seed --class=DemoDataSeeder --force
```

## Local development (5 services)

Prerequisites: PHP 8.2 + Composer, Node 20, Python 3.12, MySQL & Redis
running locally (or `docker start tassproject-mysql tassproject-redis`).

```bash
# 1. Backend API
cd backend
composer install
copy .env.example .env        # then set DB_* to your local MySQL
php artisan key:generate
php artisan migrate
php artisan db:seed --class=UserSeeder
php artisan serve

# 2. Frontend (dev server, proxies /api to :8000)
cd frontend
npm install
npm start                     # http://localhost:4200

# 3. Python scraper
cd scraper
python -m venv venv
venv\Scripts\Activate.ps1
pip install -r requirements.txt
copy .env.example .env
python worker.py

# 4. Job service API
cd job-service
npm install
copy .env.example .env
npm start                     # http://localhost:4000

# 5. Job service workers
cd job-service
npm run worker
```

## Configuration

All integration keys are optional — the platform runs in **demo mode**
with mock delivery when a key is absent.

| Variable | Where | Purpose |
| --- | --- | --- |
| `SENDGRID_API_KEY` | job-service `.env` | Real email sending (mock if empty) |
| `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN` / `TWILIO_FROM_NUMBER` | job-service `.env` | Real SMS sending (mock if empty) |
| `OPENAI_API_KEY` | job-service `.env` | LLM message personalization (mock if empty) |
| `HUNTER_API_KEY` | scraper `.env` | Email enrichment (mock if empty) |
| `MOCK_MODE` | scraper `.env` | Simulate Google searches instead of live scraping |
| `INTERNAL_SERVICE_SECRET` | backend `.env` | Shared secret between Laravel and job-service; must match `INTERNAL_SECRET` |
| `DB_CONNECTION` | backend / scraper `.env` | `sqlite`, `mysql`, or `pgsql` |

## Demo accounts

Created by `UserSeeder` (password is `password` for all):

| Email | Role | Can do |
| --- | --- | --- |
| `admin@tassproject.test` | admin | Everything, incl. Settings & user management |
| `rep@tassproject.test` | rep | CRM work, tasks, campaigns |
| `readonly@tassproject.test` | readonly | View dashboards, no writes |

## Running the tests

Backend (Laravel feature tests, in-memory SQLite — no database needed):

```bash
cd backend
php artisan test
```

Frontend (Angular unit tests):

```bash
cd frontend
npm test
```

## Project structure

```
tassproject/
├─ backend/              Laravel API
│  ├─ app/Http/Controllers/   REST controllers (auth, contacts, campaigns,
│  │                         sequences, replies, leads, scraper, settings,
│  │                         tasks, analytics, ai-actions, power-bi)
│  ├─ app/Http/Middleware/    role + internal-secret middleware
│  ├─ app/Models/             Eloquent models
│  ├─ app/Services/           LlmClient, JobServiceClient
│  ├─ database/migrations/    schema (2026_*)
│  ├─ database/seeders/       UserSeeder, DemoDataSeeder
│  └─ routes/api.php
├─ frontend/             Angular 21 SPA
│  └─ src/app/
│     ├─ core/               models, services, guards, interceptors, layout
│     └─ features/           login, dashboard, contacts, lead-finder,
│                            lead-queue, sequences, campaigns, replies,
│                            actions-log, analytics, settings
├─ scraper/              Python lead discovery + enrichment
│  ├─ google_scraper.py      query builder + search
│  ├─ hunter_client.py       email enrichment
│  ├─ ml/                    match-score model (train, predict, retrain)
│  └─ worker.py              job loop
├─ job-service/          Node.js scheduling + sending
│  ├─ src/servers/job API    /health, /schedule
│  ├─ src/queues/            BullMQ queues
│  ├─ src/senders/           SendGrid + Twilio (mock-able)
│  └─ src/workers/           email + SMS workers
├─ Dockerfile            backend (PHP + Node build) image
├─ docker-compose.yml    one-command full stack
├─ render.yaml           Render blueprint (api, job-service, workers)
└─ render-build.sh       container build/start script
```

## Deployment

The `render.yaml` blueprint deploys three Render services: the Laravel API
(docker), the job-service API (node), and the worker (node). Provision a
managed Postgres and a managed Redis instance, then fill the `FILL_AFTER_CREATE`
values in Render's dashboard with the generated connection strings. The
docker build runs `render-build.sh`, which compiles the Angular SPA into
Laravel's `public/` folder so the API and UI share one origin.

## Academic context

Built as the Bachelor's project at **EPI Digital School — Sousse** (Tunisia)
across 9 phases / 23 weeks: analysis, design, scraper, CRM, AI outreach,
sequence automation, campaign engine, reporting, and deployment.
