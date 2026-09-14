# SIH 26032 - Farmer Procurement & Queue Management System

## Project Overview

This is the documentation repository for the Smart India Hackathon 2026 Problem Statement 26032 - A Farmer Procurement & Queue Management System.

## Documentation Structure

```
docs/
├── README.md                    # This file
├── PROJECT-STATE.md             # Current project tracking
├── 00-project-overview.md       # High-level project summary
├── 01-requirements.md           # Detailed requirements
├── 02-system-architecture.md    # System architecture
├── 03-application-architecture.md # Application architecture
├── 04-backend-architecture.md   # PHP backend architecture
├── 05-flutter-architecture.md   # Flutter app architecture
├── 06-api-architecture.md       # API design principles
├── 07-api-reference.md          # Complete API reference
├── 08-database-architecture.md  # Database design principles
├── 09-database-schema.md        # Complete schema definitions
├── 10-erd.md                    # Entity Relationship Diagram
├── 11-authentication.md         # Authentication architecture
├── 12-session-management.md     # Session/token management
├── 13-roles-and-permissions.md  # RBAC system
├── 14-permission-matrix.md      # Detailed permission matrix
├── 15-business-rules.md         # Core business rules
├── 16-booking-workflow.md       # Booking system workflow
├── 17-queue-workflow.md         # Queue management workflow
├── 18-procurement-workflow.md   # Procurement workflow
├── 19-payment-workflow.md       # Payment workflow
├── 20-approval-workflow.md      # Approval/rejection workflows
├── 21-undo-reversal.md          # Undo/reversal policies
├── 22-notification-system.md    # Notification system design
├── 23-language-system.md        # Language/translation system
├── 24-file-manager.md           # File management system
├── 25-system-settings.md        # System configuration
├── 26-secret-management.md      # Secrets management
├── 27-maintenance-mode.md       # Maintenance mode
├── 28-security.md               # Security implementation
├── 29-rate-limiting.md          # Rate limiting strategy
├── 30-error-handling.md         # Error handling & logging
├── 31-logging-audit.md          # Audit logging
├── 32-login-history.md          # Login history tracking
├── 33-monitoring-alerts.md      # Monitoring & alerting
├── 34-backup-recovery.md        # Backup & recovery
├── 35-cron-jobs.md              # Scheduled jobs
├── 36-shared-hosting.md         # Shared hosting deployment
├── 37-scaling-strategy.md       # Future scaling path
├── 38-testing-strategy.md       # Testing approach
├── 39-deployment.md             # Deployment procedures
│
└── phases/
    ├── phase-00.md              # Project definition & business rules
    ├── phase-01.md              # System architecture & foundation
    ├── phase-02.md              # Database schema & migrations
    ├── phase-03.md              # PHP backend foundation
    ├── phase-04.md              # Authentication + sessions + tokens
    ├── phase-05.md              # RBAC + permission manager
    ├── phase-06.md              # System settings + secrets + maintenance
    ├── phase-07.md              # Language system + File Manager
    ├── phase-08.md              # Centre + staff management
    ├── phase-09.md              # Slots + capacity management
    ├── phase-10.md              # Bookings + tokens
    ├── phase-11.md              # Queue management
    ├── phase-12.md              # Procurement + approval/reversal
    ├── phase-13.md              # Payment status
    ├── phase-14.md              # Notification System (OneSignal Push + OTP Gateway)
    ├── phase-15.md              # Flutter Farmer App
    ├── phase-16.md              # Staff/Admin Portal
    ├── phase-17.md              # Integration + testing + security
    └── phase-18.md              # Deployment + production readiness
```

## Technology Stack

| Layer | Technology |
|-------|------------|
| Farmer App | Flutter (Dart), Android-first |
| Staff/Admin Portal | HTML5, CSS3, Vanilla JS, Chart.js |
| Backend | PHP 8.x, REST API, Modular MVC |
| Database | MySQL |
| Notifications | OneSignal Push + In-App; OTP via SMS Gateway |
| Hosting | Linux Shared Hosting, Apache, PHP, MySQL, HTTPS/SSL, Cron Jobs |

## Roles

1. **Super Admin** - System-wide access
2. **District Admin** - District-scoped access
3. **Centre Manager** - Centre-scoped management
4. **Centre Operator** - Centre-scoped operations
5. **Farmer** - Own data only (Flutter app)

## Key Features

- Farmer registration & verification
- Centre & slot management
- Slot booking with digital tokens
- Live queue with AJAX polling
- Procurement tracking (multi-crop per booking)
- Payment status tracking
- Push notifications via OneSignal + in-app inbox
- OTP SMS via gateway (registration, 2FA, password reset, mobile change)
- Role-based access control
- Audit logging
- Multi-language support (English, Hindi)
- File management
- System settings & secrets management
- Maintenance mode
- Session/device management

## Getting Started

See [PROJECT-STATE.md](PROJECT-STATE.md) for current project status and [phase-00.md](phases/phase-00.md) for the first implementation phase.

## Documentation Standards

- All phase files follow the standard format (21 sections)
- Documents are internally consistent
- Business rules are documented in [15-business-rules.md](15-business-rules.md)
- API contracts in [07-api-reference.md](07-api-reference.md)
- Database schema in [09-database-schema.md](09-database-schema.md)

---

**Note**: This is a documentation-only repository. Implementation begins when the developer explicitly says "Start Phase X".