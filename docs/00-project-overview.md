# Project Overview

## Problem Statement (SIH 26032)

Farmers face significant challenges at procurement centres:
- Long waiting times with no visibility
- No procurement schedule information
- No slot availability information
- No queue visibility
- Congestion at centres
- Uncertainty about procurement status
- Uncertainty about payment status
- Repeated unnecessary visits

## Solution Overview

A digital platform providing:
1. **Farmer App (Flutter/Android)** - Registration, centre selection, slot booking, digital tokens, live queue, status tracking, notifications
2. **Staff/Admin Portal (Web)** - Centre management, queue operations, procurement processing, payment updates, monitoring, audit
3. **Backend (PHP/MySQL)** - REST API, authentication, RBAC, notifications, audit, configuration
4. **Notifications (OneSignal Push + OTP SMS)** - Booking confirmations, queue alerts, status updates

## Target Users

| User | Application | Key Actions |
|------|-------------|-------------|
| Farmer | Flutter App | Register, book slots, view token, track queue, check procurement/payment status |
| Centre Operator | Web Portal | Call next, mark arrived, start/complete procurement |
| Centre Manager | Web Portal | Manage centre, slots, staff, view reports |
| District Admin | Web Portal | Manage centres in district, monitor operations |
| Super Admin | Web Portal | System-wide management, settings, secrets, audit |

## Core Value Proposition

- **Transparency**: Real-time queue visibility
- **Efficiency**: Slot booking reduces congestion
- **Accountability**: Audit trail for all actions
- **Accessibility**: Push notifications + in-app notifications; OTP-based verification via SMS
- **Scalability**: Modular design for future growth

## Success Metrics

- Reduced average wait time at centres
- Increased slot booking adoption
- Reduced farmer complaints
- Improved procurement throughput
- Timely payment processing

## Scope Boundaries

### In Scope
- Farmer self-registration with verification
- Centre/slot management by admins
- Booking with digital token generation
- Live queue with call/arrive/start/complete
- Multi-crop procurement per booking
- Payment status tracking
- Push notifications (OneSignal)
- Role-based access with data scoping
- Audit logging
- Multi-language (EN/HI)
- File management
- System settings & secrets
- Maintenance mode

### Out of Scope (v1)
- WebSockets (AJAX polling only)
- Mobile app for staff
- Payment gateway integration (status only)
- Advanced analytics/BI
- Offline sync for farmer app (basic offline viewing only)
- Multi-tenant SaaS
- Kubernetes/Docker
- CI/CD
- GraphQL
- Microservices