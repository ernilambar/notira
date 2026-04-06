# REST API

Base path: `/wp-json/notira/v1`

All routes require a user who can **manage options** (typically an administrator). Authenticate the same way as other WordPress REST requests from the dashboard (e.g. cookie session and `X-WP-Nonce` with value from `wp_create_nonce( 'wp_rest' )`).

---

## `POST /generate`

Runs AI text generation (same logic as the Notira admin screen).

**URL:** `{site}/wp-json/notira/v1/generate`

### Request body (JSON)

| Field   | Type   | Required | Description |
|--------|--------|----------|-------------|
| `input` | string | Yes      | Draft text. Length **20–2000** characters (byte length as in PHP `strlen`). |
| `mode`  | string | Yes      | `email` or `proofread`. |
| `tone`  | string | No       | Tone slug; defaults to `professional`. Must be one of the allowed values below. |

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

`data.meta` may include provider details when available, for example `response_id`, `token_usage`, `model`, `provider`. Cached responses add `"from_cache": true` inside `meta`.

Identical `input` + `mode` + `tone` may be served from a transient cache for **300 seconds**.

### Error responses

Errors use WordPress REST format (`code`, `message`, `data.status`).

| HTTP | Code (examples) | When |
|------|-----------------|------|
| 400 | `notira_input_too_short`, `notira_input_too_long`, `notira_invalid_mode`, `notira_empty_input`, `rest_*` | Invalid or missing parameters. |
| 502 | `notira_ai_error` | AI call failed or returned unusable output. |
| 503 | `notira_ai_unsupported`, `notira_no_api_key`, `notira_missing_prompts`, `notira_no_models`, `notira_ai_unauthorized`, `notira_ai_forbidden` | AI unavailable, missing key, bad key, or provider/configuration issues. |

`403` is returned by core if the user is not allowed to call the route.
