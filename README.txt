TASSPROJECT - COMPLETE CONSOLIDATED PROJECT (Phases 1-8 + branding)
=========================================================================

This is everything built across every phase, merged into one coherent
project - no more applying 15 separate zips one at a time. I traced the
final version of every single file (many were replaced 2-3 times across
phases) and assembled the correct end state.

WHAT I VERIFIED BEFORE PACKAGING THIS
------------------------------------------
- All 46 PHP files: valid <?php opening tag and balanced braces
  (checked programmatically, not just visually)
- All 10 job-service JS files: pass `node --check` (real syntax
  validation, not just eyeballing)
- All 6 scraper Python files + ml/ package: pass `python -m py_compile`
- Confirmed CampaignController.php has BOTH the Phase 6 LLM context
  params AND the Phase 7 message_id linking (the two most recently
  touched pieces of that file) - grepped for both to be sure
- Confirmed login.ts uses the inject() pattern (not the old buggy
  constructor version that threw TS2729)
- Confirmed zero old blue hex codes remain anywhere in the frontend
  (grepped after applying the teal rebrand to all 9 pages that needed it
  - 33 replacements across 9 files, verified none left over)
- Confirmed sidebar/login reference the CORRECT logo path
  (/logo/tplus-logo.jpg via the public/ folder, not the broken
  src/assets/ path from the first branding attempt)

WHAT'S INCLUDED
-----------------
tassproject/backend/   - app/ (Models, Controllers, Middleware, Services),
                          database/ (migrations, seeders), routes/api.php,
                          bootstrap/app.php, config/cors.php
tassproject/frontend/  - src/app/ (everything), src/environments/,
                          src/brand-tokens.css, src/styles.css,
                          src/index.html, public/logo/
tassproject/scraper/   - all .py files, ml/ package, requirements.txt,
                          .env.example
tassproject/job-service/ - src/, package.json, .env.example

WHAT'S NOT INCLUDED (on purpose - these are yours, not mine to touch)
--------------------------------------------------------------------------
- .env files (backend, scraper, job-service) - your passwords/secrets
- vendor/ (Composer), node_modules/ (npm), venv/ (Python) - dependencies
- composer.json/lock, package.json/lock (job-service and frontend),
  angular.json, tsconfig* - your project scaffolding
- Your MySQL/Redis Docker containers and their data

HOW TO APPLY
--------------
1. Extract this zip anywhere, e.g. C:\Users\DELL\Downloads\tassproject-full
2. Open PowerShell and run:

   cd C:\Users\DELL\Downloads\tassproject-full
   Unblock-File .\merge-project.ps1
   .\merge-project.ps1

   (If PowerShell blocks it: Set-ExecutionPolicy RemoteSigned -Scope
   CurrentUser, then retry.)

   If your project isn't at C:\Users\DELL\tassproject, pass the path:
   .\merge-project.ps1 -TargetPath "C:\path\to\tassproject"

3. If you're missing job-service dependencies (fresh install):
   cd C:\Users\DELL\tassproject\job-service
   npm install

4. Make sure your databases still have data. Since your backend is on
   MySQL (from Phase 8), your existing data should already be there -
   nothing here resets your database. If you want a clean demo dataset:
   cd C:\Users\DELL\tassproject\backend
   php artisan db:seed --class=DemoDataSeeder

RUN IT
-------
Same 5 windows as before:
1. cd backend && php artisan serve
2. cd frontend && npm start
3. cd scraper && .\venv\Scripts\Activate.ps1 && python worker.py
4. cd job-service && npm start
5. cd job-service && npm run worker

Plus Docker running: docker start tassproject-mysql tassproject-redis

TEST IT
--------
1. http://localhost:4200 - teal login page, block animation, your logo
2. Log in - teal sidebar with logo, all pages teal-themed
3. Everything from Phases 1-8 should work: Contacts, Lead Finder,
   Sequences, Campaigns, Replies, AI Actions Log, Analytics (+ Power BI
   tab if you finished that setup)

NEXT STEP
----------
Phase 9 (Docker Compose for one-command startup, automated tests,
GitHub README, security review) is still the one remaining item on your
cahier de charge. Say the word whenever you're ready.
