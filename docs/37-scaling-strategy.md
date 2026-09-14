# Scaling Strategy

## Overview

Current architecture is shared-hosting-friendly. This document outlines the future growth path without redesign, per the master prompt's guidance.

## Scaling Principles

- Keep it simple now; design hooks for later
- Abstractions (Storage, Notification, Settings, Permission) enable swap without rewrite
- No premature infrastructure

## Current Capacity Reality

- Single shared-hosting PHP site
- MySQL DB (single)
- Local filesystem
- Cron-driven background jobs
- AJAX polling

Fine for SIH-scale (hundreds-to-thousands of farmers).

## Growth Path

```
Shared Hosting
    ↓ (traffic/feature growth)
Better PHP Hosting / VPS (LAMP)
    ↓
External/object File Storage (S3/MinIO)
    ↓
Optional Cache (Redis for sessions, rate limits, settings)
    ↓ (large scale)
Optional dedicated queue worker / read replicas
```

## 1. Shared Hosting → VPS

When:
- Sustained higher concurrency
- Need more CPU/DB
- Need process control (long cron, workers)

Changes (minimal):
- Move app to VPS (Apache/nginx + PHP-FPM + MySQL)
- Same codebase
- More aggressive caching (opcache, file cache)

## 2. File Storage Abstraction

Already designed (StorageService/StorageAdapter). Swap:
```
LocalStorageAdapter  →  S3Adapter / MinIOAdapter
```
- Entities reference file_id; adapter resolves physical location
- No code change beyond config + adapter

## 3. Cache (Redis optional)

Current: file/DB cache + in-memory per-request.
Future: swap implementations:
- Session store → Redis
- Rate limiter → Redis (atomic incr)
- Settings cache → Redis
- Translation cache → Redis

Interface-first design makes swap straightforward.

## 4. Notifications/Queue (optional)

Current: cron + notification_logs polling.
Future (if needed):
- Email channel added to channel registry
- Optional background queue (Redis-based) to decouple push/SMS sends
- Still same NotificationService interface

## 5. Database Growth

- Index strategy already in place ([08-database-architecture.md](08-database-architecture.md))
- Pagination mandatory ([15 business rules])
- Archive old audit/log/rate-limit data ([35])
- Future: read replicas for reporting (SQL unchanged)
- Future: partitioning by date for logs if huge

## 6. Stateless API

- Auth via JWT (stateless) + DB session check
- Server-side sessions DB-backed (portable to Redis)
- No per-server file state dependency (uploads via storage abstraction)

## What NOT to Do Now

- No microservices
- No Kubernetes
- No Docker (unless hosting requires)
- No Kafka/Redis for the initial build
- No serverless
- No GraphQL
- No WebSockets infra
- No CI/CD required

## Monitoring Guardrails

Watch metrics ([33]):
- API error rate
- DB connection/resource
- Queue backlog (notification retries)
- Storage usage
- Cron run status
- Response latency (p95)

Upgrade when metrics consistently exceed thresholds.

## Documentation

Scale decisions revisited in [33-monitoring-alerts.md] and this doc. Keep PROJECT-STATE updated on capacity changes.

---

**Next**: [38-testing-strategy.md](38-testing-strategy.md) for testing.