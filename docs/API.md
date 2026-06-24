# REST API

Base path: `/wp-json/notira/v1`

All routes require a user who can **manage options** (typically an administrator).

**Authentication options:**

- **Cookie + nonce** (browser / dashboard context): include the `X-WP-Nonce` header with a nonce from `wp_create_nonce( 'wp_rest' )` alongside the logged-in session cookie.
- **Application password** (external clients / scripts): use HTTP Basic Auth — username and the application password generated under *Users → Profile → Application Passwords*. WordPress handles the rest.

Unauthenticated requests receive `401`; authenticated but insufficient capability receives `403`.

---

## `POST /generate`

Runs AI text generation (same logic as the Notira admin screen).

**URL:** `{site}/wp-json/notira/v1/generate`

### Request body (JSON)

| Field   | Type   | Required | Description |
|---------|--------|----------|-------------|
| `input` | string | Yes      | Draft text. Length **20–2000** characters (byte length as in PHP `strlen`). |
| `mode`  | string | Yes      | `email` or `proofread`. |
| `tone`  | string | No       | Tone slug; defaults to `match_original`. Must be one of the allowed values below. |

**Allowed `tone` values:** `professional`, `match_original`, `friendly`, `formal`, `concise`, `empathetic`, `authoritative`, `commanding`, `assertive`, `neutral`.

### Success response

`200 OK` — JSON body:

```json
{
  "success": true,
  "data": {
    "output": "<!-- HTML string: email includes greeting/signoff from settings; proofread is body only -->",
    "meta": {}
  }
}
```

`data.meta` may include provider details when available: `response_id`, `token_usage`, `model`, `provider`.

### Error responses

Errors use WordPress REST format (`code`, `message`, `data.status`).

| HTTP | Code (examples) | When |
|------|-----------------|------|
| 400 | `notira_input_too_short`, `notira_input_too_long`, `notira_invalid_mode`, `notira_empty_input`, `rest_*` | Invalid or missing parameters. |
| 401 | `rest_not_logged_in` | Request is not authenticated. |
| 403 | `rest_forbidden` | Authenticated but insufficient capability. |
| 502 | `notira_ai_error` | AI call failed or returned unusable output. |
| 503 | `notira_ai_unsupported`, `notira_no_api_key`, `notira_missing_prompts`, `notira_no_models`, `notira_ai_unauthorized`, `notira_ai_forbidden` | AI unavailable, missing key, bad key, or provider/configuration issues. |

---

## cURL examples

Replace `https://example.com` with your site URL and the credentials with your own.

### Application password (recommended for scripts)

```bash
curl -s -X POST "https://example.com/wp-json/notira/v1/generate" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"input": "hey just checking if the meeting is still on for tmrw let me know", "mode": "email", "tone": "formal"}'
```

### Proofread mode

```bash
curl -s -X POST "https://example.com/wp-json/notira/v1/generate" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"input": "their going to the store and they dont know when there coming back", "mode": "proofread"}'
```

### With an explicit tone

```bash
curl -s -X POST "https://example.com/wp-json/notira/v1/generate" \
  -u "admin:xxxx xxxx xxxx xxxx xxxx xxxx" \
  -H "Content-Type: application/json" \
  -d '{"input": "we need the report done by end of day or the client will not be happy about the delay", "mode": "email", "tone": "concise"}'
```

### Cookie + nonce (browser/dashboard context)

```bash
curl -s -X POST "https://example.com/wp-json/notira/v1/generate" \
  -H "X-WP-Nonce: <nonce_from_wp_create_nonce>" \
  -H "Content-Type: application/json" \
  --cookie "wordpress_logged_in_<hash>=<value>" \
  -d '{"input": "just wanted to follow up on the proposal we sent last week", "mode": "email", "tone": "friendly"}'
```

### Error response example

```json
{
  "code": "notira_input_too_short",
  "message": "Input must be at least 20 characters.",
  "data": { "status": 400 }
}
```
