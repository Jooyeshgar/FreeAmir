# Postman collection (FreeAmir API)

Secrets are not stored in this collection. Set these variables in your Postman environment:

| Variable | Example |
|---|---|
| `base_url` | `http://127.0.0.1:8000` |
| `email` | your test user email |
| `password` | your test user password |
| `token` | filled automatically after running **login** (or paste a token from `api-tokens`) |

Steps: run `POST {{base_url}}/api/login`, copy `data.token` from the response into the `token` variable, then run other requests.
