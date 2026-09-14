# Database Schema

Complete MySQL schema definition. All tables use InnoDB, utf8mb4, utf8mb4_unicode_ci.

---

## 1. users

```sql
CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(191) NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    email VARCHAR(191) NULL DEFAULT NULL,
    username VARCHAR(50) NULL DEFAULT NULL,      -- staff login (optional)
    password_hash VARCHAR(255) NOT NULL,         -- argon2id
    role_id INT UNSIGNED NOT NULL,
    status ENUM('ACTIVE','INACTIVE','PENDING','LOCKED') NOT NULL DEFAULT 'ACTIVE',
    verification_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    verification_rejected_reason VARCHAR(500) NULL DEFAULT NULL,
    email_verified_at DATETIME NULL DEFAULT NULL,
    mobile_verified_at DATETIME NULL DEFAULT NULL,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,   -- 2FA OTP login
    two_factor_enabled_at DATETIME NULL DEFAULT NULL,
    password_set_at DATETIME NULL DEFAULT NULL,
    last_login_at DATETIME NULL DEFAULT NULL,
    remember_token VARCHAR(255) NULL DEFAULT NULL,
    profile_image_file_id INT UNSIGNED NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL DEFAULT NULL,   -- who created (for staff)
    is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_mobile (mobile),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role_id),
    KEY idx_users_status (status),
    KEY idx_users_verification (verification_status),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. roles

```sql
CREATE TABLE roles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,                   -- SUPER_ADMIN, DISTRICT_ADMIN, etc.
    display_name VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,     -- cannot delete system roles
    level INT UNSIGNED NOT NULL DEFAULT 0,       -- hierarchy: SA=100, DA=70, CM=50, CO=30, Farmer=10
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Seed data:**
| name | display_name | level |
|------|-------------|-------|
| SUPER_ADMIN | Super Admin | 100 |
| DISTRICT_ADMIN | District Admin | 70 |
| CENTRE_MANAGER | Centre Manager | 50 |
| CENTRE_OPERATOR | Centre Operator | 30 |
| FARMER | Farmer | 10 |

---

## 3. permissions

```sql
CREATE TABLE permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,                  -- manage_centres, view_queue, etc.
    display_name VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,                 -- CENTRES, QUEUE, etc.
    description VARCHAR(500) NULL DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_name (name),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Seed data (from master prompt §16):**
| name | module |
|------|--------|
| view_farmers | FARMERS |
| manage_farmers | FARMERS |
| view_centres | CENTRES |
| manage_centres | CENTRES |
| view_slots | SLOTS |
| manage_slots | SLOTS |
| view_queue | QUEUE |
| manage_queue | QUEUE |
| view_procurement | PROCUREMENT |
| manage_procurement | PROCUREMENT |
| view_payments | PAYMENTS |
| manage_payments | PAYMENTS |
| view_reports | REPORTS |
| manage_staff | STAFF |
| approve_corrections | CORRECTIONS |
| view_audit_logs | AUDIT |
| manage_languages | LANGUAGES |
| manage_files | FILES |
| manage_system_settings | SETTINGS |
| manage_sessions | SESSIONS |
| manage_secrets | SECRETS |
| manage_maintenance | MAINTENANCE |
| + farmer permissions: |
| manage_own_profile | PROFILE |
| book_slot | BOOKINGS |
| manage_own_bookings | BOOKINGS |
| view_own_queue | QUEUE |
| view_own_procurement | PROCUREMENT |
| view_own_payments | PAYMENTS |

---

## 4. role_permissions

```sql
CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. user_permissions

```sql
CREATE TABLE user_permissions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,        -- 1=allow, 0=deny (override
    created_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_perm (user_id, permission_id),
    CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 6. user_sessions

```sql
CREATE TABLE user_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    session_token_hash VARCHAR(64) NOT NULL,     -- SHA-256 hash of token
    device_id VARCHAR(100) NULL DEFAULT NULL,    -- stable device identifier
    device_name VARCHAR(190) NULL DEFAULT NULL,
    platform VARCHAR(30) NULL DEFAULT NULL,      -- android, ios, web
    app_version VARCHAR(20) NULL DEFAULT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,    -- IPv4/IPv6
    user_agent VARCHAR(500) NULL DEFAULT NULL,
    remember INT UNSIGNED NULL DEFAULT NULL,     -- ID of remember_tokens (if remember-me)
    is_remembered TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    revoked_by INT UNSIGNED NULL DEFAULT NULL,
    revoked_reason VARCHAR(190) NULL DEFAULT NULL,
    status ENUM('ACTIVE','EXPIRED','REVOKED','LOGGED_OUT') NOT NULL DEFAULT 'ACTIVE',

    PRIMARY KEY (id),
    UNIQUE KEY uq_session_token (session_token_hash),
    KEY idx_session_user (user_id),
    KEY idx_session_expires (expires_at),
    KEY idx_session_status (status),
    CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 7. remember_tokens

```sql
CREATE TABLE remember_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,             -- SHA-256 of long random token
    device_id VARCHAR(100) NULL DEFAULT NULL,
    device_name VARCHAR(190) NULL DEFAULT NULL,
    platform VARCHAR(30) NULL DEFAULT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    user_agent VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL DEFAULT NULL,
    revoked_by INT UNSIGNED NULL DEFAULT NULL,
    status ENUM('ACTIVE','EXPIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE',

    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_token (token_hash),
    KEY idx_remember_user (user_id),
    KEY idx_remember_expires (expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 8. user_devices

```sql
CREATE TABLE user_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    device_id VARCHAR(100) NOT NULL,             -- stable Flutter device ID
    device_name VARCHAR(190) NULL DEFAULT NULL,
    platform VARCHAR(30) NULL DEFAULT NULL,
    app_version VARCHAR(20) NULL DEFAULT NULL,
    onesignal_player_id VARCHAR(100) NULL DEFAULT NULL, -- OneSignal push target
    last_ip_address VARCHAR(45) NULL DEFAULT NULL,
    last_seen_at DATETIME NULL DEFAULT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_device (user_id, device_id),
    CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 9. login_history

```sql
CREATE TABLE login_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL DEFAULT NULL,      -- NULL if user not found
    mobile VARCHAR(15) NULL DEFAULT NULL,        -- attempted mobile
    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_at DATETIME NULL DEFAULT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    user_agent VARCHAR(500) NULL DEFAULT NULL,
    platform VARCHAR(30) NULL DEFAULT NULL,
    device_name VARCHAR(190) NULL DEFAULT NULL,
    status ENUM('SUCCESS','FAILURE') NOT NULL,
    failure_reason VARCHAR(190) NULL DEFAULT NULL,  -- INVALID_PASSWORD, NO_ACCOUNT, LOCKED, etc.
    session_id INT UNSIGNED NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_login_user (user_id),
    KEY idx_login_at (login_at),
    KEY idx_login_status (status),
    KEY idx_login_mobile (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 10. farmers

```sql
CREATE TABLE farmers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    alternative_mobile VARCHAR(15) NULL DEFAULT NULL,
    village VARCHAR(190) NOT NULL,
    district_id INT UNSIGNED NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(10) NULL DEFAULT NULL,
    land_area_acres DECIMAL(10,3) NULL DEFAULT NULL,
    primary_crops VARCHAR(1000) NULL DEFAULT NULL,   -- JSON array or comma list
    aadhaar_last4 VARCHAR(4) NULL DEFAULT NULL,       -- minimal identifier only
    verification_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    verification_rejected_reason VARCHAR(500) NULL DEFAULT NULL,
    verified_at DATETIME NULL DEFAULT NULL,
    verified_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_farmer_user (user_id),
    KEY idx_farmer_district (district_id),
    KEY idx_farmer_verification (verification_status),
    CONSTRAINT fk_farmer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_farmer_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 11. districts

```sql
CREATE TABLE districts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    state VARCHAR(100) NOT NULL,
    code VARCHAR(10) NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_district_name_state (name, state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 12. procurement_centres

```sql
CREATE TABLE procurement_centres (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    code VARCHAR(20) NOT NULL,
    district_id INT UNSIGNED NOT NULL,
    address VARCHAR(500) NOT NULL,
    contact_phone VARCHAR(15) NULL DEFAULT NULL,
    contact_email VARCHAR(191) NULL DEFAULT NULL,
    working_hours_start TIME NOT NULL,
    working_hours_end TIME NOT NULL,
    working_days VARCHAR(50) NOT NULL DEFAULT 'MON,TUE,WED,THU,FRI',  -- CSV
    daily_capacity INT UNSIGNED NOT NULL DEFAULT 100,
    slot_duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    manager_user_id INT UNSIGNED NULL DEFAULT NULL,
    latitude DECIMAL(10,7) NULL DEFAULT NULL,
    longitude DECIMAL(10,7) NULL DEFAULT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_centre_code (code),
    KEY idx_centre_district (district_id),
    KEY idx_centre_status (status),
    CONSTRAINT fk_centre_district FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 13. centre_staff

```sql
CREATE TABLE centre_staff (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    centre_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('CENTRE_MANAGER','CENTRE_OPERATOR') NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,     -- primary manager
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_centre_staff_centre (centre_id),
    UNIQUE KEY uq_centre_staff_user_role (user_id, role),
    CONSTRAINT fk_cs_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 14. slots

```sql
CREATE TABLE slots (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    centre_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 10,
    booked_count INT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('ACTIVE','INACTIVE','FULL') NOT NULL DEFAULT 'ACTIVE',
    created_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_slot_centre (centre_id),
    KEY idx_slot_date (date),
    UNIQUE KEY uq_slot_centre_date_time (centre_id, date, start_time, end_time),
    CONSTRAINT fk_slot_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 15. bookings

```sql
CREATE TABLE bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_number VARCHAR(30) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    centre_id INT UNSIGNED NOT NULL,
    slot_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    status ENUM('PENDING','CONFIRMED','CANCELLED','COMPLETED','EXPIRED') NOT NULL DEFAULT 'CONFIRMED',
    crop_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_quantity_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
    cancelled_at DATETIME NULL DEFAULT NULL,
    cancelled_by VARCHAR(20) NULL DEFAULT NULL,    -- farmer/operator/manager/admin
    cancelled_by_user_id INT UNSIGNED NULL DEFAULT NULL,
    cancellation_reason VARCHAR(500) NULL DEFAULT NULL,
    completed_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_booking_number (booking_number),
    UNIQUE KEY uq_booking_slot_user (slot_id, user_id, status),  -- active one-per-user
    KEY idx_booking_user (user_id),
    KEY idx_booking_slot (slot_id),
    KEY idx_booking_centre_date (centre_id, date),
    KEY idx_booking_status (status),
    CONSTRAINT fk_booking_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_slot FOREIGN KEY (slot_id) REFERENCES slots(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **Note**: The `uq_booking_slot_user (slot_id, user_id, status)` composite works only if we manage active-status carefully. Alternative: enforce "no duplicate active booking" via application transaction + query check, since ENUM comparisons in unique index are unreliable. We document the query-based approach as primary, index as optimization.

---

## 16. booking_crops

```sql
CREATE TABLE booking_crops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    crop_name VARCHAR(100) NOT NULL,
    variety VARCHAR(100) NULL DEFAULT NULL,
    quantity_kg DECIMAL(12,3) NOT NULL,
    expected_quality VARCHAR(50) NULL DEFAULT NULL,
    notes VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_bc_booking (booking_id),
    CONSTRAINT fk_bc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Key rule**: One booking → one queue token, but multiple crops → multiple procurement records (see #19).

---

## 17. tokens

```sql
CREATE TABLE tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    token_number VARCHAR(40) NOT NULL,           -- e.g. APMCPNQ-20260910-0042
    qr_data VARCHAR(200) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_by VARCHAR(20) NULL DEFAULT NULL,     -- system/operator
    status ENUM('ACTIVE','USED','EXPIRED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
    revoked_at DATETIME NULL DEFAULT NULL,
    revoked_reason VARCHAR(190) NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_token_booking (booking_id),
    UNIQUE KEY uq_token_number (token_number),
    CONSTRAINT fk_token_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 18. queue_entries

```sql
CREATE TABLE queue_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    centre_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    status ENUM('WAITING','CALLED','IN_PROGRESS','COMPLETED','SKIPPED','CANCELLED') NOT NULL DEFAULT 'WAITING',
    position INT UNSIGNED NOT NULL,               -- current logical position (recomputed)
    arrived_at DATETIME NULL DEFAULT NULL,
    called_at DATETIME NULL DEFAULT NULL,
    called_by INT UNSIGNED NULL DEFAULT NULL,
    started_at DATETIME NULL DEFAULT NULL,
    started_by INT UNSIGNED NULL DEFAULT NULL,
    completed_at DATETIME NULL DEFAULT NULL,
    completed_by INT UNSIGNED NULL DEFAULT NULL,
    skipped_at DATETIME NULL DEFAULT NULL,
    skipped_by INT UNSIGNED NULL DEFAULT NULL,
    skip_reason VARCHAR(500) NULL DEFAULT NULL,
    cancelled_at DATETIME NULL DEFAULT NULL,
    notes VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_queue_booking (booking_id),
    KEY idx_queue_centre_date (centre_id, date),
    KEY idx_queue_status (status),
    KEY idx_queue_created (created_at),
    CONSTRAINT fk_queue_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_queue_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 19. procurements

```sql
CREATE TABLE procurements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    booking_crop_id INT UNSIGNED NULL DEFAULT NULL,
    centre_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,               -- farmer
    crop_name VARCHAR(100) NOT NULL,
    variety VARCHAR(100) NULL DEFAULT NULL,
    quantity_kg DECIMAL(12,3) NOT NULL,
    quality_grade VARCHAR(10) NULL DEFAULT NULL,  -- A, B, C
    moisture_percent DECIMAL(5,2) NULL DEFAULT NULL,
    status ENUM('PENDING','VERIFIED','IN_PROGRESS','COMPLETED','REJECTED') NOT NULL DEFAULT 'PENDING',
    rate_per_kg DECIMAL(12,2) NULL DEFAULT NULL,
    amount DECIMAL(12,2) NULL DEFAULT NULL,
    notes VARCHAR(500) NULL DEFAULT NULL,
    rejection_reason VARCHAR(500) NULL DEFAULT NULL,
    verified_at DATETIME NULL DEFAULT NULL,
    verified_by INT UNSIGNED NULL DEFAULT NULL,
    started_at DATETIME NULL DEFAULT NULL,
    started_by INT UNSIGNED NULL DEFAULT NULL,
    completed_at DATETIME NULL DEFAULT NULL,
    completed_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_proc_booking (booking_id),
    KEY idx_proc_centre_date (centre_id, created_at),
    KEY idx_proc_status (status),
    KEY idx_proc_user (user_id),
    CONSTRAINT fk_proc_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_proc_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT,
    CONSTRAINT fk_proc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 20. payments

```sql
CREATE TABLE payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    procurement_id INT UNSIGNED NOT NULL,
    centre_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,               -- farmer receiving payment
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('PENDING','PROCESSING','PAID','FAILED') NOT NULL DEFAULT 'PENDING',
    payment_method VARCHAR(30) NULL DEFAULT NULL,  -- NEFT, RTGS, cheque, cash, UPI
    reference VARCHAR(100) NULL DEFAULT NULL,      -- UTR/cheque no/txn id
    processed_at DATETIME NULL DEFAULT NULL,
    processed_by INT UNSIGNED NULL DEFAULT NULL,
    failure_reason VARCHAR(500) NULL DEFAULT NULL,
    notes VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_procurement (procurement_id),
    KEY idx_payment_centre_date (centre_id, created_at),
    KEY idx_payment_status (status),
    KEY idx_payment_user (user_id),
    CONSTRAINT fk_pay_procurement FOREIGN KEY (procurement_id) REFERENCES procurements(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_centre FOREIGN KEY (centre_id) REFERENCES procurement_centres(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 21. notifications

```sql
CREATE TABLE notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(30) NOT NULL,                    -- BOOKING_CONFIRMED, etc.
    title VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL DEFAULT NULL,                  -- related entity IDs, links
    channel ENUM('PUSH','IN_APP','BOTH') NOT NULL DEFAULT 'IN_APP',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_notif_user (user_id),
    KEY idx_notif_user_read (user_id, is_read),
    KEY idx_notif_type (type),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 22. notification_logs

```sql
CREATE TABLE notification_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id INT UNSIGNED NULL DEFAULT NULL,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    recipient VARCHAR(190) NULL DEFAULT NULL,     -- player_id (PUSH) / mobile (SMS)
    channel ENUM('PUSH','IN_APP','SMS') NOT NULL DEFAULT 'PUSH',
    event_type VARCHAR(30) NOT NULL,
    status ENUM('PENDING','SENT','FAILED','RETRY') NOT NULL DEFAULT 'PENDING',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    next_retry_at DATETIME NULL DEFAULT NULL,
    sent_at DATETIME NULL DEFAULT NULL,
    error_message VARCHAR(500) NULL DEFAULT NULL,
    provider_response TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_nlog_status_retry (status, next_retry_at),
    KEY idx_nlog_notification (notification_id),
    KEY idx_nlog_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 23. languages

```sql
CREATE TABLE languages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL,                    -- en, hi, mr
    name VARCHAR(50) NOT NULL,
    native_name VARCHAR(50) NULL DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_lang_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 24. translations

```sql
CREATE TABLE translations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    language_id INT UNSIGNED NOT NULL,
    translation_key VARCHAR(190) NOT NULL,        -- e.g. auth.login_title
    translated_value TEXT NOT NULL,
    updated_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_translation (language_id, translation_key),
    CONSTRAINT fk_trans_lang FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 25. files

```sql
CREATE TABLE files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_name VARCHAR(190) NOT NULL,
    source_type ENUM('LOCAL','URL','EXTERNAL') NOT NULL DEFAULT 'LOCAL',
    path_or_url VARCHAR(500) NOT NULL,
    folder_id INT UNSIGNED NULL DEFAULT NULL,
    mime_type VARCHAR(100) NULL DEFAULT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    extension VARCHAR(20) NULL DEFAULT NULL,
    checksum VARCHAR(64) NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL DEFAULT NULL,
    status ENUM('ACTIVE','INACTIVE','DELETED') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_file_folder (folder_id),
    KEY idx_file_type (source_type),
    CONSTRAINT fk_file_folder FOREIGN KEY (folder_id) REFERENCES file_folders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 26. file_folders

```sql
CREATE TABLE file_folders (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    parent_id INT UNSIGNED NULL DEFAULT NULL,
    created_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_folder_parent (parent_id),
    CONSTRAINT fk_folder_parent FOREIGN KEY (parent_id) REFERENCES file_folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 27. file_references

```sql
CREATE TABLE file_references (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    file_id INT UNSIGNED NOT NULL,
    source_type VARCHAR(50) NOT NULL,             -- centre_logo, user_avatar, etc.
    source_id INT UNSIGNED NULL DEFAULT NULL,     -- related entity ID
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_file_ref (file_id, source_type, source_id),
    KEY idx_file_ref_file (file_id),
    CONSTRAINT fk_fref_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 28. system_settings

```sql
CREATE TABLE system_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name VARCHAR(100) NOT NULL,               -- system_name, sms_enabled, etc.
    key_value TEXT NULL DEFAULT NULL,
    value_type ENUM('STRING','INT','BOOL','JSON','FLOAT') NOT NULL DEFAULT 'STRING',
    is_public TINYINT(1) NOT NULL DEFAULT 0,      -- returned to non-admin endpoints
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,   -- masked in API
    group_name VARCHAR(50) NULL DEFAULT NULL,     -- GENERAL, NOTIFICATION, etc.
    description VARCHAR(500) NULL DEFAULT NULL,
    updated_by INT UNSIGNED NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Default seed settings:**
```
system_name                 = Farmer Procurement System (STRING, public)
default_language            = en (STRING, public)
maintenance_mode            = 0 (BOOL, public)
maintenance_message         = (STRING, public)
maintenance_expected_available_at = (STRING, public)
sms_enabled                 = 1 (BOOL)
push_enabled                = 1 (BOOL)   -- OneSignal push channel on/off
two_factor_enabled_default  = 0 (BOOL)   -- default for new users
notification_retry_count    = 3 (INT)
notification_retry_interval_seconds = 300 (INT)
booking_cancellation_window_minutes = 120 (INT)
queue_notification_threshold = 3 (INT)
file_upload_max_size_mb     = 5 (INT)
allowed_file_types          = ["jpg","png","pdf"] (JSON)
support_contact_phone       = (STRING, public)
support_contact_email       = (STRING, public)
timezone                    = Asia/Kolkata (STRING)
session_timeout_minutes     = 30 (INT)
remember_me_expiry_days     = 30 (INT)
rate_limit_login_per_min    = 5 (INT)
rate_limit_otp_per_5min     = 3 (INT)
rate_limit_booking_per_min  = 10 (INT)
rate_limit_sms_per_min      = 10 (INT)
notification_language_default = en (STRING)
```

---

## 29. system_secrets

```sql
CREATE TABLE system_secrets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_name VARCHAR(100) NOT NULL,               -- onesignal_app_id, otp_api_key, etc.
    encrypted_value TEXT NOT NULL,                -- AES-256-GCM ciphertext
    iv VARCHAR(64) NOT NULL,
    is_set TINYINT(1) NOT NULL DEFAULT 0,
    last_rotated_at DATETIME NULL DEFAULT NULL,
    last_updated_by INT UNSIGNED NULL DEFAULT NULL,
    description VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_secret_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Secret keys:**
```
onesignal_app_id        -- hardcoded in config/onesignal.php for now
onesignal_rest_api_key  -- hardcoded in config/onesignal.php for now
otp_api_key             -- hardcoded in config/otp.php for now
otp_sender_id
otp_template_id
jwt_secret
encryption_key_bootstrap   (managed via env, see secret-management doc)
```
> Note: OneSignal + OTP keys are **hardcoded in config for now** (development/initial release). Later they move into system_secrets (encrypted).

---

## 30. audit_logs

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    user_name VARCHAR(190) NULL DEFAULT NULL,     -- denormalized for history
    user_role VARCHAR(50) NULL DEFAULT NULL,
    action VARCHAR(50) NOT NULL,                  -- CREATE, UPDATE, DELETE, LOGIN, etc.
    module VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL DEFAULT NULL,
    entity_id INT UNSIGNED NULL DEFAULT NULL,
    old_value JSON NULL DEFAULT NULL,
    new_value JSON NULL DEFAULT NULL,
    reason VARCHAR(500) NULL DEFAULT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    user_agent VARCHAR(500) NULL DEFAULT NULL,
    request_id VARCHAR(64) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_audit_time (created_at),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_module (module),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 31. error_logs

```sql
CREATE TABLE error_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(64) NULL DEFAULT NULL,
    endpoint VARCHAR(500) NULL DEFAULT NULL,
    http_method VARCHAR(10) NULL DEFAULT NULL,
    status_code INT NULL DEFAULT NULL,
    error_type VARCHAR(100) NULL DEFAULT NULL,    -- ValidationException, etc.
    error_code VARCHAR(50) NULL DEFAULT NULL,
    severity ENUM('INFO','WARNING','ERROR','CRITICAL') NOT NULL DEFAULT 'ERROR',
    message TEXT NULL DEFAULT NULL,
    file_path VARCHAR(500) NULL DEFAULT NULL,
    line_number INT NULL DEFAULT NULL,
    stack_trace TEXT NULL DEFAULT NULL,
    user_id INT UNSIGNED NULL DEFAULT NULL,
    ip_address VARCHAR(45) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_err_time (created_at),
    KEY idx_err_endpoint (endpoint),
    KEY idx_err_type (error_type),
    KEY idx_err_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 32. support_requests

```sql
CREATE TABLE support_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    subject VARCHAR(190) NOT NULL,
    category VARCHAR(50) NULL DEFAULT NULL,       -- booking, payment, account, etc.
    message TEXT NOT NULL,
    related_entity_type VARCHAR(50) NULL DEFAULT NULL,
    related_entity_id INT UNSIGNED NULL DEFAULT NULL,
    status ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN',
    assigned_to INT UNSIGNED NULL DEFAULT NULL,   -- staff user
    resolved_at DATETIME NULL DEFAULT NULL,
    resolved_by INT UNSIGNED NULL DEFAULT NULL,
    resolution_notes TEXT NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_support_user (user_id),
    KEY idx_support_status (status),
    CONSTRAINT fk_support_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 33. otp_verifications

```sql
CREATE TABLE otp_verifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    mobile VARCHAR(15) NOT NULL,
    purpose ENUM('REGISTER','LOGIN','LOGIN_2FA','PASSWORD_RESET','MOBILE_CHANGE','2FA_ENABLE','2FA_STEP_UP') NOT NULL,
    verification_id VARCHAR(40) NOT NULL,         -- ver_... / rst_... / 2fa_... / step_...
    otp_hash VARCHAR(64) NOT NULL,               -- SHA-256 of OTP (never store plain)
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    expires_at DATETIME NOT NULL,
    resend_at DATETIME NULL DEFAULT NULL,
    verified_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_otp_verification_id (verification_id),
    KEY idx_otp_mobile (mobile),
    KEY idx_otp_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 34. migrations (runner table)

```sql
CREATE TABLE migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(190) NOT NULL,              -- filename
    batch INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_migration (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Schema Versioning

- Versioned via migration files
- `migrations` table tracks applied migrations
- Phase 02 implements all tables
- Later phases alter/add tables via new migrations

## Seed Summary

Phase 02 seeds:
- Default roles (5)
- All permissions
- role_permissions defaults
- Languages (en, hi)
- Default districts (Maharashtra sample)
- System settings defaults
- System secrets placeholders (unset) — OneSignal/OTP keys hardcoded in config for now

Demo seeder (Phase 17):
- Sample centres, slots, farmers, bookings, queue, procurements, payments

---

**Next**: [10-erd.md](10-erd.md) for entity relationship diagram.