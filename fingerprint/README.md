# ZKFinger HTTP bridge

`FingerprintBridgeServer.java` exposes the ZKTeco fingerprint reader over HTTP
(`/status`, `/enroll/start`, `/enroll/capture`, `/verify`, `/identify`) so the
Symfony app (`App\Service\FingerprintClient`) can use it.

## ZKTeco SDK (not included)

The SDK is proprietary, so it is not committed. Download the **ZKFinger Reader
SDK for Java** from ZKTeco (https://www.zkteco.com, *Support → Download Center*)
and place these files here:

```
fingerprint/
├── lib/ZKFingerReader.jar   # SDK jar
└── libzkfp.dll              # 32-bit native library
```

Both paths are gitignored.

## Run

Run `start_fingerprint_service.bat` (or `start_fingerprint_service.ps1`) on the
Windows machine the reader is plugged into. It compiles the bridge with a JDK and
runs it with a 32-bit JRE, which `libzkfp.dll` requires.

## Configuration (environment variables)

| Variable | Default | Purpose |
|---|---|---|
| `FINGERPRINT_BIND_HOST` | `127.0.0.1` | Interface to listen on. Only use `0.0.0.0` on a trusted network. |
| `FINGERPRINT_ALLOWED_ORIGIN` | *(empty)* | Browser origin allowed to call the bridge directly. Not needed when only Symfony calls it. |

The bridge always listens on port `8085`. Symfony finds it via `FINGERPRINT_HOST` / `FINGERPRINT_PORT`.
