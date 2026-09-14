# System Architecture

## High-Level Architecture

```
┌─────────────────────┐     HTTPS REST API      ┌─────────────────────┐
│  Flutter Farmer App │◀────────────────────────▶│   PHP Backend API   │
└─────────────────────┘                          └──────────┬──────────┘
                                                              │
                    ┌─────────────────────────────────────────┼─────────────────────────────────────────┐
                    │                                         │                                         │
                    ▼                                         ▼                                         ▼
            ┌───────────────┐                         ┌───────────────┐                         ┌──────────────────┐
            │    MySQL      │                         │  File Storage │                         │ OneSignal / OTP  │
            │  (Database)   │                         │  (Shared FS)  │                         │ SMS Gateway      │
            └───────────────┘                         └───────────────┘                         │ (Push + OTP SMS) │
                                                                                                └──────────────────┘
                    │                                                                             
                    │                                         ┌─────────────────────┐             
                    └────────────────────────────────────────▶│  Staff/Admin Portal │             
                                                              │  (HTML/CSS/JS)      │             
                                                              └─────────────────────┘             
```

## Component Responsibilities

### Flutter Farmer App
- **Platform**: Android (primary), iOS (future)
- **Communication**: HTTPS REST API only
- **State Management**: Simple (Provider/ChangeNotifier or Riverpod)
- **Offline**: Basic cached viewing, queue polling when online
- **Storage**: Secure local storage for tokens (flutter_secure_storage)
- **Dependencies**: Minimal (http, shared_preferences, flutter_secure_storage, qr_flutter, intl)

### Staff/Admin Portal
- **Technology**: HTML5, CSS3, Vanilla ES6+ JavaScript
- **Communication**: Same PHP REST API (fetch/AJAX)
- **Charts**: Chart.js (CDN)
- **No Build Step**: Direct file serving
- **Authentication**: Session cookies (HttpOnly, Secure)
- **UI**: Minimal, professional, light green theme

### PHP Backend API
- **Architecture**: Modular MVC-inspired
- **PHP Version**: 8.1+
- **Structure**:
  ```
  app/
  ├── Controllers/     # Request handling, response formatting
  ├── Models/          # Data models, database interaction
  ├── Services/        # Business logic, external integrations
  ├── Middleware/      # Auth, RBAC, rate limiting, validation
  ├── Validators/      # Request validation rules
  ├── Helpers/         # Common utilities
  ├── Repositories/    # Data access abstraction (if needed)
  └── Exceptions/      # Custom exceptions
  config/              # Configuration files
  routes/              # Route definitions
  database/            # Migrations, seeders
  public/              # Entry point (index.php), static assets
  storage/             # Logs, uploads, cache
  views/               # Email templates, error pages
  ```
- **Routing**: Front controller (public/index.php) with regex-based router
- **Database**: PDO with prepared statements
- **Response Format**: JSON (success: {data, meta}, error: {error, code, message})
- **Error Handling**: Centralized exception handler

### MySQL Database
- **Version**: 8.0+
- **Engine**: InnoDB
- **Charset**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Connection**: PDO with persistent connections disabled (shared hosting)
- **Migrations**: Custom PHP migration runner

### File Storage
- **Location**: Shared hosting filesystem (storage/app/public, storage/app/private)
- **Database**: File references with metadata
- **Abstraction**: StorageService for future migration to S3/compatible
- **Public Files**: Served via PHP (authorization check) or direct (public assets)
- **Private Files**: Served via PHP with auth/scope check

### OneSignal Push + OTP Gateway Services
- **OneSignal (push)**: REST API direct HTTP (no SDK dependency); keys hardcoded in `config/onesignal.php` for now
- **OTP Gateway (SMS)**: Separate service for OTP messages only; API key hardcoded in `config/otp.php` for now
- **Abstraction**: NotificationService with channel interface (PUSH, IN_APP, SMS-OTP)
- **Future**: Additional channels (e.g., email) register without redesign
- **Queue**: notification_logs table with retry logic

## Data Flow Examples

### Booking Flow
```
Farmer App                          PHP API                              MySQL
   │                                    │                                    │
   ├─ POST /api/bookings ──────────────▶│                                    │
   │         {centre_id, slot_id}       │                                    │
   │                                    ├─ BEGIN TRANSACTION                │
   │                                    ├─ CHECK slot capacity              │
   │                                    ├─ CHECK duplicate booking          │
   │                                    ├─ CREATE booking                  │
   │                                    ├─ CREATE queue_entry              │
   │                                    ├─ GENERATE token                  │
   │                                    ├─ COMMIT                          │
   │                                    │                                    │
   │◀──── {booking, token, queue} ───────┤                                    │
   │                                    │                                    │
   │                                    ├─ QUEUE notification (async)       │
   │                                    │                                    │
   │                                    ▼                                    ▼
```

### Queue "Call Next" Flow
```
Staff Portal                        PHP API                              MySQL
   │                                    │                                    │
   ├─ POST /api/queue/call-next ───────▶│                                    │
   │         {centre_id, date}          │                                    │
   │                                    ├─ BEGIN TRANSACTION                │
   │                                    ├─ SELECT ... FOR UPDATE            │
   │                                    │   (oldest WAITING queue_entry)   │
   │                                    ├─ UPDATE status → CALLED          │
   │                                    ├─ UPDATE called_at, called_by     │
   │                                    ├─ COMMIT                          │
   │                                    │                                    │
   │◀──── {queue_entry, token} ─────────┤                                    │
   │                                    │                                    │
   │                                    ├─ QUEUE notification (async)       │
   │                                    │                                    │
   ▼                                    ▼                                    ▼
```

### Procurement Flow (Multi-Crop)
```
Staff Portal                        PHP API                              MySQL
   │                                    │                                    │
   ├─ POST /api/procurements ──────────▶│                                    │
   │         {booking_id, crops:[...]}  │                                    │
   │                                    ├─ BEGIN TRANSACTION                │
   │                                    ├─ VERIFY booking status            │
   │                                    ├─ FOR EACH crop:                   │
   │                                    │   CREATE procurement record       │
   │                                    │   Status: PENDING                │
   │                                    ├─ UPDATE booking → COMPLETED      │
   │                                    ├─ UPDATE queue_entry → COMPLETED  │
   │                                    ├─ COMMIT                          │
   │                                    │                                    │
   │◀──── {procurements} ───────────────┤                                    │
   │                                    │                                    │
   │                                    ├─ QUEUE notifications (async)      │
   │                                    │                                    │
   ▼                                    ▼                                    ▼
```

## Security Architecture

### Network
- HTTPS everywhere (SSL/TLS)
- API and portal on same domain (subdomain or path)
- CORS configured for Flutter app domain only

### Authentication
- **Web Portal**: Session cookies (PHP session + custom session table)
- **Flutter App**: JWT (access 15min, refresh 7 days) + device binding
- **Remember-Me**: Separate long-lived tokens (30 days, hashed in DB)

### Authorization
- RBAC with permissions + resource scoping
- Middleware on every protected route
- Permission checks in Controllers AND Services

### Data Protection
- Passwords: argon2id (PHP password_hash)
- Secrets: AES-256-GCM encryption (key from env/bootstrap)
- Tokens: SHA-256 hash stored, never plain text
- PII: Minimal collection, no unnecessary logging

### Input/Output
- Validation: Server-side (Validators) + client-side
- Sanitization: Context-aware (HTML, SQL, JSON)
- Prepared statements: Always (PDO)

## Deployment Architecture

### Shared Hosting Environment
```
/home/user/
├── public_html/           # Document root
│   ├── index.php          # API front controller
│   ├── portal/            # Staff/Admin portal (static files)
│   │   ├── index.html
│   │   ├── assets/
│   │   └── app.js
│   └── farmer/            # Flutter web build (optional future)
├── app/                   # PHP application (outside web root)
├── config/                # Configuration
├── storage/               # Logs, uploads, cache (writable)
├── database/              # Migrations
└── .env                   # Environment variables (not in repo)
```

### Cron Jobs (cPanel/Plesk)
- `php /home/user/app/console cron:run` (every minute)
- Individual job scheduling via database scheduler table

### SSL/HTTPS
- Let's Encrypt or hosting provider SSL
- HSTS header
- Secure cookie flags

## Scalability Considerations

### Current (Shared Hosting)
- Stateless API (session in DB)
- Connection pooling via PDO
- Database indexes on all query paths
- Pagination mandatory
- File storage on local FS

### Next Level (VPS/Cloud)
- Redis for sessions/cache/rate limiting
- Object storage (S3/MinIO) for files
- Load balancer + multiple PHP workers
- Queue worker for notifications
- Read replicas for reporting

### Future (Microservices - Not Now)
- Separate services: Auth, Booking, Queue, Procurement, Notification
- API Gateway
- Event-driven (Kafka/RabbitMQ)
- Container orchestration

**Decision**: Current architecture supports migration path without rewrite.

## Technology Decisions Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Backend Language | PHP 8.1+ | Team skill, shared hosting native, mature ecosystem |
| Framework | Custom MVC-inspired | No framework overhead, full control, learning value |
| Database | MySQL 8.0 | Shared hosting standard, ACID, JSON support |
| API Style | REST/JSON | Simple, universal, Flutter-friendly |
| Farmer Auth | JWT | Stateless, mobile-friendly, token refresh |
| Web Auth | Sessions | Traditional, CSRF-protected, simple |
| Real-time | AJAX Polling | Shared hosting compatible, no WebSocket server |
| Notifications | OneSignal (push) + OTP SMS gateway | Push to app, OTP via SMS, keys hardcoded for now |
| File Storage | Local FS + DB refs | Shared hosting constraint, abstraction for future |
| Secrets | Encrypted in DB | Shared hosting (no Vault), key from env |
| Language | Gettext-style keys | Simple, no runtime compilation |
| State (Flutter) | ChangeNotifier/Provider | Simple, built-in, no code gen |

## Monitoring & Observability

### Health Checks
- `GET /api/health` - Database, storage, OneSignal/OTP gateway connectivity
- `GET /api/health/maintenance` - Maintenance mode status

### Logs
- `storage/logs/application.log` - General
- `storage/logs/api.log` - API requests/responses
- `storage/logs/security.log` - Auth, permissions, rate limits
- `storage/logs/notification.log` - Push/SMS send/retry/fail

### Metrics (Future)
- Request latency (p50, p95, p99)
- Error rates by endpoint
- Queue depth
- Push delivery rate (OneSignal) / OTP SMS delivery rate
- Active sessions

## Disaster Recovery

### Backup Strategy
- Daily MySQL dump (cron, compressed, encrypted)
- Daily file storage sync (rsync or hosting backup)
- Retention: 30 days daily, 12 months monthly
- Off-site: Download to local/secondary storage

### Recovery Procedure
1. Restore MySQL from dump
2. Restore files from backup
3. Verify .env configuration
4. Run migrations (if schema changed)
5. Test health endpoints
6. Disable maintenance mode

### RPO/RTO Targets (Shared Hosting)
- RPO: 24 hours (daily backup)
- RTO: 2-4 hours (manual restore)

---

**Next**: [03-application-architecture.md](03-application-architecture.md) for detailed application structure.