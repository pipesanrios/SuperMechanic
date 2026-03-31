# API.md

## Purpose

This document defines the API structure of Super Mechanic.

It provides a clear reference for:

- available endpoints
- authentication methods
- request/response formats
- integration patterns

---

## API Overview

Super Mechanic exposes two API layers:

### Internal Admin API

Base URL:

/wp-json/super-mechanic/v1/

Used for:

- admin operations
- internal modules
- dashboard logic

---

### Public API

Base URL:

/wp-json/super-mechanic-public/v1/

Used for:

- external integrations
- controlled access

---

## Authentication

### Internal API

- WordPress session authentication
- nonce validation
- capability checks

---

### Public API

Planned support:

- API Key authentication

### Authorization Header

```bash
Authorization: Bearer YOUR_API_KEY
```

---

## Endpoint Structure

Standard format:

/wp-json/{namespace}/{resource}

Example:

/wp-json/super-mechanic/v1/processes

---

## Common Resources

### Clients

GET /wp-json/super-mechanic/v1/clients  
POST /wp-json/super-mechanic/v1/clients  
GET /wp-json/super-mechanic/v1/clients/{id}

---

### Vehicles

GET /wp-json/super-mechanic/v1/vehicles  
POST /wp-json/super-mechanic/v1/vehicles  
GET /wp-json/super-mechanic/v1/vehicles/{id}

---

### Processes

GET /wp-json/super-mechanic/v1/processes  
POST /wp-json/super-mechanic/v1/processes  
GET /wp-json/super-mechanic/v1/processes/{id}

---

### CRM Tasks

GET /wp-json/super-mechanic/v1/crm/tasks  
POST /wp-json/super-mechanic/v1/crm/tasks

---

### CRM Alerts

GET /wp-json/super-mechanic/v1/crm/alerts

Important:

- alerts are persisted
- must not be recalculated manually

---

### Appointments

GET /wp-json/super-mechanic/v1/appointments  
POST /wp-json/super-mechanic/v1/appointments

---

## Request Format

All requests use JSON.

Example:

```json
{
  "vehicle_id": 10,
  "process_type": "maintenance"
}
```

---

## Response Format

All responses follow this structure:

```json
{
  "success": true,
  "data": {},
  "message": ""
}
```

---

## Error Format

```json
{
  "success": false,
  "message": "Invalid request",
  "code": "SM_INVALID"
}
```

---

## Pagination

For list endpoints:

Query parameters:

- page
- per_page

Example:

GET /wp-json/super-mechanic/v1/clients?page=1&per_page=20

---

## Filtering (Optional)

Example:

GET /wp-json/super-mechanic/v1/processes?status=active

---

## Webhooks

### Tables

- sm_webhooks
- sm_webhook_deliveries

---

### Example Events

- process.created
- process.updated
- task.created
- alert.triggered

---

## Security Model

- capability validation
- nonce checks (internal)
- API key validation (public)
- ownership enforcement
- business_id filtering

---

## Multi-Tenant Rules

All API calls must:

- respect business_id
- prevent cross-tenant access

---

## Common Mistakes

- exposing internal data
- bypassing services
- ignoring tenant filtering
- recalculating alerts

---

## Debugging

- check API response
- validate authentication
- inspect logs
- verify payload

---

## Versioning

Current version:

v1

Future:

- v2 for breaking changes
- backward compatibility when possible

---

## Final Note

The API is a controlled interface.

Always:

- respect architecture
- reuse services
- enforce security
