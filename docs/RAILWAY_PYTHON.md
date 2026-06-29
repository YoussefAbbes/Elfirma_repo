# Deploying the Python services to Railway

EL FIRMA's Python code runs as **separate Railway services** in the *same* Railway
project as the PHP app, reached over Railway's **private network**. This guide
covers the three HTTP services that are ready today:

| Railway service (suggested name) | Root directory       | Listens | PHP env var that points to it     |
|----------------------------------|----------------------|---------|-----------------------------------|
| `elfirma-equipment-ai`           | `ai_service/ai_service` | 8001 | `EQUIPMENT_AI_BASE_URL`           |
| `elfirma-chatbot`                | `chatbot_ai`         | 8002    | `AI_FASTAPI_BASE_URL`             |
| `elfirma-faceid`                 | `scripts/faceid`     | 8765    | `FACE_ID_HOST` + `FACE_ID_PORT`   |

> RAG (`rag/scripts`) is **not** covered here — it is still a CLI subprocess and
> needs a FastAPI wrapper + a PHP refactor before it can be deployed. Do it after
> these three are healthy.

---

## Key concepts (read once)

- **One repo, many services.** Each Python service is a *new Railway service*
  pointing at this same GitHub repo, with a different **Root Directory**. Railway's
  Nixpacks detects Python from the `requirements.txt` already committed in each
  folder and installs it automatically.
- **Private, not public.** These services should have **no public domain**. Other
  services reach them at `http://<service-name>.railway.internal:<PORT>`.
- **Bind to IPv6.** Railway's private network is IPv6-only. A service bound to
  `0.0.0.0` is *unreachable internally* — bind to `::`.
- **Fix the PORT.** Set a service variable `PORT=<the port>` so the value is stable
  and the PHP side can address it predictably.

---

## Per-service setup (Railway dashboard)

For each service: **New → GitHub Repo → (this repo)**, then open **Settings**.

### 1. elfirma-equipment-ai
- **Root Directory:** `ai_service/ai_service`
- **Variables:** `PORT=8001`
- **Custom Start Command:** `uvicorn main:app --host :: --port $PORT`
- **Networking:** no public domain. (Pure-Python scoring; tiny + fast.)

### 2. elfirma-chatbot
- **Root Directory:** `chatbot_ai`
- **Variables:** `PORT=8002`
- **Custom Start Command:** `uvicorn main:app --host :: --port $PORT`
- Loads `model/*.pkl` on startup via a relative path — works because Railway runs
  from the root directory. The `.pkl` artifacts are committed, so nothing to upload.

### 3. elfirma-faceid  ⚠️ has prerequisites — see below
- **Root Directory:** `scripts/faceid`
- **Variables:** `PORT=8765`
- **Custom Start Command:**
  `python face_id_api.py --host :: --port $PORT --storage-dir /data/encodings --models-dir /data/models --threshold 0.28`

---

## Wire the PHP app to the services

On the **PHP (EL FIRMA) service**, add these variables (Variables tab). Use the
internal hostnames — substitute your actual service names:

```
EQUIPMENT_AI_BASE_URL=http://elfirma-equipment-ai.railway.internal:8001
AI_FASTAPI_BASE_URL=http://elfirma-chatbot.railway.internal:8002
FACE_ID_HOST=elfirma-faceid.railway.internal
FACE_ID_PORT=8765
```

(`FACE_ID_HOST`/`FACE_ID_PORT` are split because `FaceIdClient` builds
`http://{host}:{port}/{endpoint}` itself — host has no scheme.)

Keep these in `.env.railway` (untracked) alongside the other Railway vars, per the
project's secrets convention.

---

## Face ID prerequisites (must do, or it crash-loops)

Face ID has two problems that the other two services don't:

1. **ONNX model files are not in git.** `face_id_api.py` needs
   `face_detection_yunet_2023mar.onnx` and `face_recognition_sface_2021dec.onnx` in
   its `--models-dir`. Without them `FaceEngine.__init__` raises and the service
   never starts.
2. **Enrollments are written to disk** as `user_*.json` in `--storage-dir`. Railway's
   container filesystem is **ephemeral** — every redeploy wipes it, losing all
   enrolled faces.

**Fix:** attach a **Railway Volume** to `elfirma-faceid` mounted at `/data`, then:
- put the two `.onnx` files under `/data/models` (upload once via a one-off shell or
  a small init step), and
- let `/data/encodings` hold the enrollments persistently.

The start command above already points `--models-dir` and `--storage-dir` at `/data`.
(Alternative for enrollments: store encodings in MySQL instead of files — a code
change, not required to get going.)

---

## Verify

After all three deploy green, from the **PHP service shell** (Railway → service →
Shell), or by exercising the app:

- Equipment AI: open an equipment that triggers AI analysis; or
  `curl http://elfirma-equipment-ai.railway.internal:8001/health`
- Chatbot: `curl http://elfirma-chatbot.railway.internal:8002/health`; exercise the
  chatbot widget and the `/elfirma/supplier-analytics` page.
- Face ID: attempt a face-ID login; the service log should show detect/recognize hits.

If a call fails with a connection error, the usual causes are: service bound to
`0.0.0.0` instead of `::`, wrong port in the PHP env var, or (faceid) missing ONNX
models.
