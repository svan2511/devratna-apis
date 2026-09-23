# Dev Ratna — Auth API (OTP)

Base URL (dev): `http://127.0.0.1:8000/api/v1`
Base URL (Android emulator): `http://10.0.2.2:8000/api/v1`

## Standards followed
- RESTful versioning (`/api/v1/...`)
- Service-Repository pattern (`OtpService` + `UserRepository` / `OtpRepository`)
- Form Requests (`RequestOtpRequest`, `VerifyOtpRequest`)
- API Resources (`UserResource`)
- DB transactions in `OtpService`
- Strict types + PSR-12 (verified with `vendor/bin/pint`)
- Eloquent only — no raw/engine-specific SQL, so switching between SQLite, MySQL and Postgres needs no code changes
- Sanctum Bearer tokens, throttle on public OTP routes

## Endpoints

### 1. POST /auth/request-otp
Body: `{ "phone": "9897012345" }` (+91 / spaces allowed)
Success 200:
```json
{ "success": true, "message": "OTP sent successfully.",
  "data": { "expires_in_seconds": 300, "resend_available_in": 30, "dev_otp": "591080" } }
```
Note: `dev_otp` is only returned when `OTP_DUMMY=true` or `APP_DEBUG=true`. Remove it once a real SMS provider is connected.

### 2. POST /auth/verify-otp
Body: `{ "phone": "9897012345", "otp": "4821", "name": "Rahul (first login)" }`
Success 200:
```json
{ "success": true, "message": "Welcome to Dev Ratna!",
  "data": { "user": {...}, "token": "1|...", "token_type": "Bearer", "is_new": true } }
```
In the local environment, `OTP_FIXED_CODE=1234` is always accepted for emulator testing.

### 3. GET /auth/me
Header: `Authorization: Bearer <token>`

### 4. POST /auth/logout
Header: `Authorization: Bearer <token>` — revokes the current token.

## Mobile (.env) vars
```
OTP_DUMMY=true
OTP_FIXED_CODE=1234   # keep empty in production
OTP_EXPIRY_MINUTES=5
OTP_MAX_ATTEMPTS=5
OTP_RESEND_COOLDOWN=30
OTP_MAX_PER_HOUR=10
```

## Run
```
cd backend
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```
