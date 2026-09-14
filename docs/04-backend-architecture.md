# PHP Backend Architecture

## Overview

PHP 8.1+ modular MVC-inspired backend. No framework. Custom lightweight architecture designed for simplicity, maintainability, and shared hosting compatibility.

## Directory Structure

```
/home/user/
├── public/                          # Document root (web-accessible)
│   ├── index.php                    # Front controller (single entry)
│   ├── .htaccess                    # Apache rewrite rules
│   └── portal/                      # Staff/Admin portal static files
│       ├── index.html
│       ├── assets/
│       └── ...
│
├── app/                             # Application code (outside web root)
│   ├── Core/                        # Framework core (boot, config, DB, router)
│   │   ├── App.php                  # Application container
│   │   ├── Bootstrap.php            # Autoloader + initialization
│   │   ├── Router.php              # URL routing
│   │   ├── Request.php             # HTTP request wrapper
│   │   ├── Response.php            # HTTP response wrapper
│   │   ├── Database.php            # PDO wrapper
│   │   ├── Container.php           # Simple DI container
│   │   └── ErrorHandler.php        # Exception → response
│   │
│   ├── Controllers/                 # Request handlers
│   │   ├── AuthController.php
│   │   ├── FarmerController.php
│   │   ├── CentreController.php
│   │   ├── StaffController.php
│   │   ├── SlotController.php
│   │   ├── BookingController.php
│   │   ├── QueueController.php
│   │   ├── ProcurementController.php
│   │   ├── PaymentController.php
│   │   ├── NotificationController.php
│   │   ├── FileController.php
│   │   ├── SettingController.php
│   │   ├── SecretController.php
│   │   ├── LanguageController.php
│   │   ├── AuditController.php
│   │   ├── ReportController.php
│   │   └── HealthController.php
│   │
│   ├── Models/                      # Data models
│   │   ├── User.php
│   │   ├── Farmer.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── UserSession.php
│   │   ├── RememberToken.php
│   │   ├── UserDevice.php
│   │   ├── LoginHistory.php
│   │   ├── Centre.php
│   │   ├── CentreStaff.php
│   │   ├── Slot.php
│   │   ├── Booking.php
│   │   ├── BookingCrop.php
│   │   ├── QueueEntry.php
│   │   ├── Procurement.php
│   │   ├── Payment.php
│   │   ├── Notification.php
│   │   ├── NotificationLog.php
│   │   ├── Language.php
│   │   ├── Translation.php
│   │   ├── File.php
│   │   ├── FileFolder.php
│   │   ├── FileReference.php
│   │   ├── SystemSetting.php
│   │   ├── SystemSecret.php
│   │   ├── AuditLog.php
│   │   ├── ErrorLog.php
│   │   └── SupportRequest.php
│   │
│   ├── Services/                    # Business logic
│   │   ├── AuthService.php
│   │   ├── FarmerService.php
│   │   ├── CentreService.php
│   │   ├── StaffService.php
│   │   ├── SlotService.php
│   │   ├── BookingService.php
│   │   ├── TokenService.php
│   │   ├── QueueService.php
│   │   ├── ProcurementService.php
│   │   ├── PaymentService.php
│   │   ├── NotificationService.php
│   │   ├── OneSignalService.php       # Push via OneSignal (hardcoded config keys for now)
│   │   ├── OtpService.php             # 2FA/registration OTP via SMS gateway (hardcoded key)
│   │   ├── FileService.php
│   │   ├── SettingService.php
│   │   ├── SecretService.php
│   │   ├── LanguageService.php
│   │   ├── AuditService.php
│   │   ├── ReportService.php
│   │   └── MaintenanceService.php
│   │
│   ├── Middleware/                   # Request processing layers
│   │   ├── MiddlewareInterface.php
│   │   ├── AuthMiddleware.php       # Session/JWT validation
│   │   ├── RoleMiddleware.php       # Role checking
│   │   ├── PermissionMiddleware.php # Permission checking
│   │   ├── ScopeMiddleware.php      # Resource scope validation
│   │   ├── RateLimitMiddleware.php  # Rate limiting
│   │   ├── CsrfMiddleware.php       # CSRF token validation
│   │   ├── CorsMiddleware.php       # CORS headers
│   │   ├── MaintenanceMiddleware.php# Maintenance mode check
│   │   └── AuditMiddleware.php      # Request logging
│   │
│   ├── Validators/                  # Input validation
│   │   ├── ValidatorInterface.php
│   │   ├── Validator.php            # Base validator
│   │   ├── AuthValidator.php
│   │   ├── FarmerValidator.php
│   │   ├── CentreValidator.php
│   │   ├── SlotValidator.php
│   │   ├── BookingValidator.php
│   │   ├── ProcurementValidator.php
│   │   ├── PaymentValidator.php
│   │   ├── FileValidator.php
│   │   ├── SettingValidator.php
│   │   ├── StaffValidator.php
│   │   └── LanguageValidator.php
│   │
│   ├── Helpers/                     # Utility functions
│   │   ├── helpers.php              # Global helper functions
│   │   ├── Hasher.php               # Hash/verify tokens
│   │   ├── Encryptor.php            # AES-256-GCM encrypt/decrypt
│   │   ├── Uuid.php                 # UUID generation
│   │   ├── Token.php                # Random token generation
│   │   ├── DateTime.php             # Date/time helpers
│   │   ├── Response.php             # JSON response helpers
│   │   ├── Validator.php            # Input sanitization helpers
│   │   └── Pagination.php           # Pagination helpers
│   │
│   ├── Exceptions/                  # Custom exceptions
│   │   ├── AppException.php         # Base exception
│   │   ├── ValidationException.php
│   │   ├── AuthenticationException.php
│   │   ├── AuthorizationException.php
│   │   ├── NotFoundException.php
│   │   ├── ConflictException.php
│   │   ├── RateLimitException.php
│   │   ├── MaintenanceException.php
│   │   ├── ExternalServiceException.php
│   │   └── DatabaseException.php
│   │
│   ├── Console/                     # CLI scripts
│   │   ├── cron.php                 # Cron job runner
│   │   ├── migrate.php              # Database migration runner
│   │   ├── seed.php                 # Database seeder
│   │   └── commands/                # Individual cron commands
│   │       ├── ExpireSessions.php
│   │       ├── CleanRememberTokens.php
│   │       ├── RetryNotifications.php
│   │       ├── CleanupTempFiles.php
│   │       ├── RotateLogs.php
│   │       └── GenerateReport.php
│   │
│   └── bootstrap.php               # Autoloader + app initialization
│
├── config/                          # Configuration
│   ├── config.php                   # Main config (reads .env)
│   ├── database.php                 # Database config
│   ├── routes.php                   # Route definitions
│   ├── middleware.php               # Middleware pipeline
│   └── app.php                      # App constants
│
├── routes/                          # Route files (optional split)
│   ├── api.php                      # API routes
│   └── web.php                      # Web/portal routes
│
├── database/                        # Database
│   ├── migrations/                  # Migration files
│   ├── seeders/                     # Seed data
│   └── factories/                   # Test data generators (optional)
│
├── storage/                         # Writable storage
│   ├── logs/                        # Application logs
│   │   ├── application.log
│   │   ├── api.log
│   │   ├── security.log
│   │   └── notification.log
│   ├── app/                         # Uploaded files
│   │   ├── public/                  # Publicly accessible files
│   │   └── private/                 # Protected files
│   ├── cache/                       # File cache (optional)
│   └── temp/                        # Temporary files
│
├── views/                           # Server-rendered templates
│   ├── errors/                      # Error pages
│   │   ├── 404.php
│   │   ├── 500.php
│   │   └── maintenance.php
│   └── emails/                      # Email templates (future)
│       └── notification.php
│
├── .env                             # Environment variables
├── .env.example                     # Environment template
└── .htaccess                        # Root .htaccess (deny access)
```

## Bootstrap Process

```
Request arrives at public/index.php
        │
        ▼
Load .env → config.php
        │
        ▼
Initialize Autoloader (PSR-4 for app/, custom for vendor/)
        │
        ▼
Initialize Database (PDO connection)
        │
        ▼
Initialize Session (for web) or skip (for Flutter JWT)
        │
        ▼
Parse route from REQUEST_URI
        │
        ▼
Match route → Controller@method + middleware chain
        │
        ▼
Execute middleware pipeline (in order):
  1. MaintenanceMiddleware      → Block if maintenance + not admin
  2. CorsMiddleware             → Add CORS headers
  3. AuthMiddleware             → Validate session/JWT
  4. RateLimitMiddleware        → Check rate limits
  5. RoleMiddleware             → Check role (if route requires)
  6. PermissionMiddleware       → Check permission (if route requires)
  7. ScopeMiddleware            → Validate resource scope
  8. AuditMiddleware            → Log request for audit trail
        │
        ▼
Controller receives validated Request
        │
        ▼
Controller → Service → Model → Database
        │
        ▼
Service returns result
        │
        ▼
Controller formats Response (JSON)
        │
        ▼
Response sent to client
```

## Core Components

### Router

```php
// config/routes.php or routes/api.php
// Pattern: METHOD /path → Controller@method

// Public routes (no auth required)
'POST /api/v1/auth/register'       => 'AuthController@register',
 'POST /api/v1/auth/verify-otp'     => 'AuthController@verifyOtp',
 'POST /api/v1/auth/resend-otp'     => 'AuthController@resendOtp',
 'POST /api/v1/auth/login'          => 'AuthController@login',
 'POST /api/v1/auth/verify-2fa'     => 'AuthController@verify2fa',
 'POST /api/v1/auth/resend-2fa'     => 'AuthController@resend2fa',
 'POST /api/v1/auth/2fa/enable'     => 'AuthController@enable2fa',
 'POST /api/v1/auth/2fa/enable/confirm' => 'AuthController@enable2faConfirm',
 'POST /api/v1/auth/2fa/disable'    => 'AuthController@disable2fa',
 'POST /api/v1/auth/2fa/challenge'  => 'AuthController@challenge2fa',
 'POST /api/v1/auth/2fa/confirm'    => 'AuthController@confirm2fa',
 'POST /api/v1/auth/forgot-password' => 'AuthController@forgotPassword',
 'POST /api/v1/auth/reset-password' => 'AuthController@resetPassword',
 'POST /api/v1/auth/mobile-change'       => 'AuthController@mobileChange',
 'POST /api/v1/auth/mobile-change/confirm' => 'AuthController@mobileChangeConfirm',
 'POST /api/v1/auth/devices'        => 'AuthController@registerDevice', // OneSignal player_id
 'GET  /api/v1/health'              => 'HealthController@index',
'GET  /api/v1/health/maintenance'  => 'HealthController@maintenance',
'GET  /api/v1/languages'           => 'LanguageController@index',
'GET  /api/v1/centres'             => 'CentreController@index', // public list
'GET  /api/v1/centres/{id}'        => 'CentreController@show',

// Authenticated routes (session or JWT required)
'POST /api/v1/auth/logout'         => ['AuthController@logout', ['auth']],
'POST /api/v1/auth/refresh'        => ['AuthController@refresh', ['auth']],
'GET  /api/v1/auth/me'             => ['AuthController@me', ['auth']],

// Staff routes (auth + role required)
'GET    /api/v1/dashboards'        => ['ReportController@dashboard', ['auth', 'staff']],
'POST   /api/v1/centres'           => ['CentreController@store', ['auth', 'staff', 'permission:manage_centres']],
// ... etc

// Farmer routes (auth + farmer role required)
'POST /api/v1/farmer/bookings'     => ['BookingController@store', ['auth', 'farmer']],
// ... etc

// Parameter patterns
'GET /api/v1/centres/{id}'         => ['CentreController@show', ['auth']],
'GET /api/v1/bookings/{id}'        => ['BookingController@show', ['auth', 'scope:booking']],
```

```php
// app/Core/Router.php
class Router {
    private array $routes = [];
    private array $middlewareMap = [];

    public function add(string $method, string $path, array $handler): void { ... }
    public function match(string $method, string $uri): ?array { ... }
    public function getMiddleware(string $routeKey): array { ... }
}
```

### Request

```php
// app/Core/Request.php
class Request {
    private array $body;        // Parsed JSON body
    private array $query;       // Query parameters
    private array $routeParams; // Route parameters {id, etc}
    private array $headers;     // Request headers
    private string $method;     // HTTP method
    private string $uri;        // Request URI

    // JWT token (Flutter)
    private ?string $bearerToken = null;

    // Session user (Web)
    private ?array $sessionUser = null;

    // Auth
    public function getBearerToken(): ?string { ... }
    public function setSessionUser(array $user): void { ... }
    public function getUser(): ?array { ... }
    public function getUserId(): ?int { ... }
    public function getRoleId(): ?int { ... }

    // Input
    public function input(string $key, $default = null) { ... }
    public function all(): array { ... }
    public function only(array $keys): array { ... }
    public function has(string $key): bool { ... }

    // Meta
    public function ip(): string { ... }
    public function userAgent(): string { ... }
    public function requestId(): string { ... }

    // Validation
    public function validate(array $rules): array { ... } // throws ValidationException
}
```

### Response

```php
// app/Core/Response.php
class Response {
    public static function json($data, int $status = 200, array $meta = []): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $status >= 200 && $status < 400,
            'data' => $data,
            'meta' => array_merge(['timestamp' => gmdate('c')], $meta)
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function success($data, array $meta = []): void { ... }
    public static function created($data): void { ... }
    public static function error(string $code, string $message, int $status = 400, array $details = []): void { ... }
    public static function notFound(string $message = 'Resource not found'): void { ... }
    public static function forbidden(string $message = 'Access denied'): void { ... }
    public static function unauthorized(string $message = 'Authentication required'): void { ... }
    public static function validationError(array $errors): void { ... }
    public static function conflict(string $message): void { ... }
    public static function maintenance(string $message, string $estimated = ''): void { ... }
}
```

### Database

```php
// app/Core/Database.php
class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/../../config/database.php';
            self::$pdo = new PDO(
                "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]
            );
        }
        return self::$pdo;
    }

    // Transaction helpers
    public static function beginTransaction(): void { ... }
    public static function commit(): void { ... }
    public static function rollback(): void { ... }

    // Query helpers (wrapper around PDO)
    public static function query(string $sql, array $params = []): PDOStatement { ... }
    public static function select(string $sql, array $params = []): array { ... }
    public static function selectOne(string $sql, array $params = []): ?array { ... }
    public static function insert(string $table, array $data): int { ... }
    public static function update(string $table, array $data, string $where, array $whereParams = []): int { ... }
    public static function delete(string $table, string $where, array $whereParams = []): int { ... }
    public static function lastInsertId(): string { ... }
}
```

### Container (Simple DI)

```php
// app/Core/Container.php
class Container {
    private array $bindings = [];
    private array $singletons = [];

    public function bind(string $abstract, callable $concrete): void { ... }
    public function singleton(string $abstract, callable $concrete): void { ... }
    public function make(string $abstract) { ... }
    public function has(string $abstract): bool { ... }
}
```

## Middleware Execution Flow

### Request Pipeline

```
Incoming Request
      │
      ▼
public/index.php (Bootstrap → Route Match → Extract Middleware)
      │
      ▼
┌─ MaintenanceMiddleware ──── Is maintenance mode ON?
│  │                         Is user Super Admin bypassing?
│  │                         Yes → Continue    No → Return 503
│  ▼
├─ CorsMiddleware ──────── Set CORS headers
│  │                       Continue
│  ▼
├─ AuthMiddleware ──────── Web: Check PHP session → JWT: Validate access token
│  │                       Fail → 401    Success → Set user on Request
│  ▼
├─ RateLimitMiddleware ─── Check rate limit for endpoint + user/IP
│  │                       Fail → 429    Success → Continue
│  ▼
├─ RoleMiddleware ──────── Check user role matches required role
│  │                       Fail → 403    Success → Continue
│  ▼
├─ PermissionMiddleware ── Check user has required permission
│  │                       Fail → 403    Success → Continue
│  ▼
├─ ScopeMiddleware ─────── Check resource access (district/centre/data)
│  │                       Fail → 403    Success → Continue
│  ▼
└─ AuditMiddleware ─────── Log request metadata (optional)
       │                   Continue
       ▼
   Controller@method executes
       │
       ▼
   Service business logic
       │
       ▼
   Response sent
```

### Middleware Implementation

```php
// app/Middleware/AuthMiddleware.php
class AuthMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next) {
        // Try JWT first (Flutter)
        $token = $request->getBearerToken();
        if ($token) {
            $user = $this->authService->validateJwtToken($token);
            if ($user) {
                $request->setSessionUser($user);
                return $next($request);
            }
            return Response::unauthorized('Invalid or expired token');
        }

        // Try session (Web)
        $sessionUserId = $_SESSION['user_id'] ?? null;
        if ($sessionUserId) {
            $user = $this->authService->getValidSessionUser($sessionUserId);
            if ($user) {
                $request->setSessionUser($user);
                return $next($request);
            }
            // Session invalid/expired
            session_destroy();
            return Response::unauthorized('Session expired');
        }

        return Response::unauthorized('Authentication required');
    }
}

// app/Middleware/PermissionMiddleware.php
class PermissionMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next) {
        $requiredPermission = $request->getRouteAttribute('permission');
        if (!$requiredPermission) return $next($request);

        $userId = $request->getUserId();
        $hasPermission = $this->permissionService->userHasPermission($userId, $requiredPermission);

        if (!$hasPermission) {
            // Audit: unauthorized attempt
            $this->auditService->logUnauthorizedAccess($userId, $requiredPermission, $request);
            return Response::forbidden('You do not have permission: ' . $requiredPermission);
        }

        return $next($request);
    }
}
```

## Controller Pattern

```php
// app/Controllers/CentreController.php
class CentreController {
    private CentreService $centreService;

    public function __construct() {
        $this->centreService = new CentreService();
    }

    // GET /api/v1/centres
    public function index(Request $request): void {
        $filters = $request->only(['district_id', 'status', 'search']);
        $pagination = Pagination::fromRequest($request);

        $result = $this->centreService->list($filters, $pagination, $request->getUser());

        Response::success($result['data'], [
            'pagination' => $result['pagination']
        ]);
    }

    // GET /api/v1/centres/{id}
    public function show(Request $request): void {
        $id = (int) $request->getParam('id');
        $centre = $this->centreService->findById($id, $request->getUser());

        if (!$centre) {
            return Response::notFound('Centre not found');
        }

        Response::success($centre);
    }

    // POST /api/v1/centres
    public function store(Request $request): void {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:centres,code',
            'address' => 'required|string|max:500',
            'district_id' => 'required|integer|exists:districts,id',
            'contact_phone' => 'required|string|max:15',
            'contact_email' => 'nullable|email',
            'working_hours_start' => 'required|date_format:H:i',
            'working_hours_end' => 'required|date_format:H:i|after:working_hours_start',
            'daily_capacity' => 'required|integer|min:1|max:5000',
        ]);

        $centre = $this->centreService->create($data, $request->getUser());

        Response::created($centre);
    }

    // PUT /api/v1/centres/{id}
    public function update(Request $request): void { ... }

    // PATCH /api/v1/centres/{id}/status
    public function updateStatus(Request $request): void { ... }

    // DELETE /api/v1/centres/{id}
    public function destroy(Request $request): void { ... } // soft delete / deactivate
}
```

## Service Pattern

```php
// app/Services/CentreService.php
class CentreService {
    private Centre $centreModel;
    private AuditService $auditService;

    public function __construct() {
        $this->centreModel = new Centre();
        $this->auditService = new AuditService();
    }

    public function list(array $filters, array $pagination, ?array $user): array {
        // Apply scope based on user role
        $scope = $this->getScopeFilter($user);

        // Merge filters
        $allFilters = array_merge($filters, $scope);

        // Query
        $total = $this->centreModel->count($allFilters);
        $data = $this->centreModel->paginate($allFilters, $pagination, [
            'district' => true,  // eager load district
            'manager' => true,   // eager load manager user
        ]);

        return [
            'data' => $data,
            'pagination' => Pagination::make($total, $pagination)
        ];
    }

    public function create(array $data, ?array $user): array {
        // Business logic
        $centre = $this->centreModel->create($data);

        // Audit
        $this->auditService->log([
            'user_id' => $user['id'],
            'action' => 'CREATE',
            'module' => 'CENTRES',
            'entity_type' => 'centre',
            'entity_id' => $centre['id'],
            'new_value' => $data,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        return $centre;
    }

    private function getScopeFilter(?array $user): array {
        if (!$user) return [];
        return match ($user['role_name']) {
            'SUPER_ADMIN' => [],
            'DISTRICT_ADMIN' => ['district_id' => $user['district_id']],
            'CENTRE_MANAGER', 'CENTRE_OPERATOR' => ['id' => $user['centre_id']],
            default => ['id' => 0] // no access
        };
    }
}
```

## Model Pattern

```php
// app/Models/Centre.php
class Centre {
    private const TABLE = 'centres';

    // Read operations
    public function findById(int $id, array $eager = []): ?array { ... }
    public function findByCode(string $code): ?array { ... }
    public function count(array $filters = []): int { ... }
    public function paginate(array $filters, array $pagination, array $eager = []): array { ... }

    // Write operations
    public function create(array $data): array { ... }
    public function update(int $id, array $data): array { ... }
    public function updateStatus(int $id, string $status): bool { ... }

    // Soft delete
    public function softDelete(int $id): bool { ... }

    // Eager loading
    private function eagerLoad(array $data, array $eager): array { ... }

    // Query builder helpers
    private function buildWhereClause(array $filters): array { ... }
    private function buildOrderClause(array $pagination): string { ... }
}
```

## Error Handling

```php
// app/Core/ErrorHandler.php
class ErrorHandler {
    public static function handle(Throwable $e): void {
        // Log the error
        self::logError($e);

        // Map exception to HTTP response
        $response = match (true) {
            $e instanceof ValidationException => Response::validationError($e->getErrors()),
            $e instanceof AuthenticationException => Response::unauthorized($e->getMessage()),
            $e instanceof AuthorizationException => Response::forbidden($e->getMessage()),
            $e instanceof NotFoundException => Response::notFound($e->getMessage()),
            $e instanceof ConflictException => Response::conflict($e->getMessage()),
            $e instanceof RateLimitException => Response::error('RATE_LIMITED', $e->getMessage(), 429),
            $e instanceof MaintenanceException => Response::maintenance($e->getMessage()),
            $e instanceof DatabaseException => self::handleDatabaseError($e),
            default => self::handleUnknownError($e),
        };

        // Never expose internals in production
        if (getenv('APP_ENV') === 'production' && !($e instanceof ValidationException)) {
            Response::error('SERVER_ERROR', 'An unexpected error occurred', 500);
        }
    }

    private static function handleDatabaseError(DatabaseException $e): void {
        // Check if deadlock/lock wait
        // Return appropriate message without exposing SQL
        Response::error('DATABASE_ERROR', 'A database error occurred. Please try again.', 500);
    }

    private static function handleUnknownError(Throwable $e): void {
        Response::error('SERVER_ERROR', 'An unexpected error occurred', 500);
    }

    private static function logError(Throwable $e): void {
        $logEntry = [
            'timestamp' => gmdate('c'),
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid('req_'),
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ];
        error_log(json_encode($logEntry) . PHP_EOL, 3, __DIR__ . '/../../storage/logs/application.log');
    }
}
```

## Autoloading

```php
// app/bootstrap.php
spl_autoload_register(function (string $class) {
    // PSR-4: App\Core → app/Core/
    // PSR-4: App\Controllers → app/Controllers/
    // etc.
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Custom helpers autoload
require_once __DIR__ . '/Helpers/helpers.php';
```

## Configuration

```php
// .env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_SECRET=your-random-secret-key

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=farmer_procurement
DB_USERNAME=db_user
DB_PASSWORD=db_pass
DB_CHARSET=utf8mb4

JWT_SECRET=your-jwt-secret-key
JWT_ACCESS_EXPIRY=900          # 15 minutes
JWT_REFRESH_EXPIRY=604800      # 7 days

SESSION_LIFETIME=1800          # 30 minutes
REMEMBER_ME_EXPIRY=2592000     # 30 days

ENCRYPTION_KEY=your-256-bit-encryption-key

RATE_LIMIT_LOGIN=5             # per minute
RATE_LIMIT_OTP=3               # per 5 minutes
RATE_LIMIT_BOOKING=10          # per minute
RATE_LIMIT_SMS=10              # per minute

# Hardcoded for now (moved to encrypted secrets in a later phase)
ONESIGNAL_APP_ID=your-onesignal-app-id
ONESIGNAL_REST_API_KEY=your-onesignal-rest-api-key
OTP_API_KEY=your-otp-gateway-api-key
OTP_SENDER_ID=your-sender-id
OTP_TEMPLATE_ID=your-template-id

FILE_UPLOAD_MAX_SIZE=5242880   # 5MB

NOTIFICATION_MAX_RETRIES=3
NOTIFICATION_RETRY_INTERVAL=300  # 5 minutes

APP_TIMEZONE=Asia/Kolkata
```

## PHP Configuration Requirements

```ini
; php.ini requirements
display_errors = Off
log_errors = On
error_log = /home/user/storage/logs/php_error.log

memory_limit = 128M
max_execution_time = 30
max_input_time = 60

; PDO
pdo_mysql.default_socket = /var/run/mysqld/mysqld.sock

; Session (for web portal)
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Lax
session.use_strict_mode = 1

; File uploads
upload_max_filesize = 10M
post_max_size = 12M
```

## Apache Configuration

```apache
# public/.htaccess
RewriteEngine On

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API routes → index.php
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^api/(.*)$ index.php [QSA,L]

# Deny direct access to non-public directories
<DirectoryMatch "^/home/user/(app|config|storage|database|views)/">
    Require all denied
</DirectoryMatch>

# Allow public directory
<Directory /home/user/public>
    AllowOverride None
    Require all granted
</Directory>
```

---

**Next**: [05-flutter-architecture.md](05-flutter-architecture.md) for Flutter app details.