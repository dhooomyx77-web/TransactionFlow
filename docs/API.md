# API reference

Base URL: `/api.php`. All JSON responses use UTF-8. Authentication uses an HttpOnly SameSite session cookie. Mutation requests require `X-CSRF-Token` with the token from the session or login response. No cross-origin access is enabled.

| Method | Query action | Purpose | Access |
|---|---|---|---|
| GET | session | Current user (or null) and CSRF token | Everyone |
| POST | login | Authenticate username/password | Everyone + CSRF |
| POST | logout | End the session | Authenticated + CSRF |
| GET | transactions | List, pagination and global status counts | All roles |
| POST | transactions | Create | Admin / editor + CSRF |
| PUT | transactions | Update | Admin / editor + CSRF |
| DELETE | transactions | Delete | Admin + CSRF |
| GET | export | Download filtered CSV | All roles |
| GET | audit | Latest 200 mutation summaries | Admin |

## Payloads

Create requires `transaction_number` (1–30 chars), `applicant_name` and `transaction_type` (1–100 chars), and `status`. Optional `notes` is at most 5000 characters. Text fields are trimmed. Supported statuses: `new`, `under_review`, `incomplete`, `completed`.

Update uses all create fields plus integer `id` and current `version`. Delete uses `id` and `version`. Stale versions return 409; reload before editing again.

```json
{
  "transaction_number": "DEMO-0001",
  "applicant_name": "اسم تجريبي",
  "transaction_type": "طلب إداري",
  "status": "new",
  "notes": "بيانات وهمية فقط"
}
```

## List and export

Optional `q` searches transaction number or applicant name (literal substring, max 100 chars). Optional `status` is one of the four supported statuses. List also accepts `page` and returns 15 rows, newest internal ID first. The server clamps page to the available range.

List returns `items`, `total` (filtered count), `page`, `pages` and `stats` (global counts by status). Timestamps in JSON and CSV are UTC; the Arabic UI displays Riyadh time. Export follows current search/status filters across all pages.

## Response codes

200: success; 201: created; 400: malformed JSON; 401: unauthenticated/failed login; 403: forbidden/invalid CSRF; 404: missing record/action; 405: unsupported method; 409: duplicate number/stale version; 413: oversized body; 422: validation error; 500: unexpected server failure; 503: missing configuration.

JSON errors have an `error` string suitable for display. Database exception details are logged only on the server. See SECURITY.md for scope and limits.
