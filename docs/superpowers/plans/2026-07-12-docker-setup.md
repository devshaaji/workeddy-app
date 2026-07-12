# Docker Setup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a valid and tested docker-compose.yml and .env.example.docker for the WorkEddy v2 project.

**Architecture:** Use Approach B, where all Docker network configurations and service overrides are defined in .env.example.docker, which copies to .env. Direct Symfony Console commands are integrated for DB migrations, database seeding, and video retention.

**Tech Stack:** Docker, Docker Compose, PHP/Symfony CLI

## Global Constraints

- Services must match existing container structures (e.g. mysql:8.4, redis:7-alpine, nginx:1.27-alpine).
- Database migrations and seeders must use v2 Symfony CLI commands: `php bin/console doctrine:migrations:migrate` and `php bin/console db:seed`.
- Video worker container must depend on Nginx.
- Command validation must run via `docker compose config` before completion.

---

### Task 1: Create `.env.example.docker` File

**Files:**
- Create: `.env.example.docker`

**Interfaces:**
- Consumes: None
- Produces: `.env.example.docker` file on disk template

- [ ] **Step 1: Write `.env.example.docker`**

Create the file `.env.example.docker` with all Docker environment variables configured for container networks.

- [ ] **Step 2: Verify file existence**

Verify the `.env.example.docker` exists on disk.
Run: `ls -la .env.example.docker` (or PowerShell equivalent)
Expected: File size and details are listed.

---

### Task 2: Create `docker-compose.yml` File

**Files:**
- Create: `docker-compose.yml`

**Interfaces:**
- Consumes: `.env.example.docker`
- Produces: `docker-compose.yml` file on disk

- [ ] **Step 1: Write `docker-compose.yml`**

Create the file `docker-compose.yml` at the project root defining all backend, database, worker, and proxy services.

- [ ] **Step 2: Create temporary `.env` for validation**

Copy `.env.example.docker` to `.env` to validate the compose file.
Run: `cp .env.example.docker .env` (or PowerShell `Copy-Item .env.example.docker .env`)

- [ ] **Step 3: Run `docker compose config` validation**

Verify compose syntax passes lint/verification.
Run: `docker compose config --quiet`
Expected: Passes with exit code 0.

- [ ] **Step 4: Restore/Clean up `.env` if necessary**

Clean up or leave `.env` as appropriate.
