"""
Cliente firmado HMAC-SHA256 para el nodo de visión artificial (Python) de ProwlTracker.

Cómo usarlo en tu script principal de detección (YOLO / OpenCV):
1. Configura las variables de entorno en tu máquina local:
   - PROWL_API_URL="https://tu-app.onrender.com"
   - NODE_API_SECRET="la_misma_clave_hex_configurada_en_render"
2. Importa `signed_get` y `signed_post` (o reemplaza tus llamadas a `requests.get`/`requests.post`).
"""

import hashlib
import hmac
import json
import os
import secrets
import time
from urllib.parse import urlencode, urlsplit
import requests

PROWL_API_URL = os.getenv("PROWL_API_URL", "http://127.0.0.1:8000").rstrip("/")
NODE_API_SECRET = os.getenv("NODE_API_SECRET", "").strip()


def _canonical_query(query_string: str) -> str:
    if not query_string:
        return ""
    pairs = [p.split("=", 1) for p in query_string.split("&") if p]
    normalized = [(k, v if len( pair := [k, *v] ) > 1 else "") for k, *v in pairs]
    normalized.sort(key=lambda item: (item[0], item[1]))
    return "&".join(f"{k}={v}" for k, v in normalized)


def build_signed_headers(method: str, full_url: str, body_bytes: bytes = b"") -> dict:
    """
    Construye las cabeceras X-PT-Timestamp, X-PT-Nonce y X-PT-Signature (HMAC-SHA256)
    compatibles con el middleware VerifyNodeSignature de Laravel.
    """
    headers = {"Accept": "application/json"}
    if not NODE_API_SECRET:
        return headers

    parsed = urlsplit(full_url)
    path = parsed.path or "/"
    query = _canonical_query(parsed.query)
    timestamp = str(int(time.time()))
    nonce = secrets.token_hex(16)
    body_hash = hashlib.sha256(body_bytes).hexdigest()

    canonical_payload = "\n".join([
        method.upper(),
        path,
        query,
        timestamp,
        nonce,
        body_hash,
    ])

    signature = hmac.new(
        NODE_API_SECRET.encode("utf-8"),
        canonical_payload.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()

    headers.update({
        "X-PT-Timestamp": timestamp,
        "X-PT-Nonce": nonce,
        "X-PT-Signature": signature,
    })
    return headers


def signed_get(endpoint: str, params: dict | None = None, timeout: int = 15) -> requests.Response:
    url = f"{PROWL_API_URL}/{endpoint.lstrip('/')}"
    if params:
        url = f"{url}?{urlencode(params)}"
    headers = build_signed_headers("GET", url, b"")
    return requests.get(url, headers=headers, timeout=timeout)


def signed_post(endpoint: str, payload: dict, timeout: int = 20) -> requests.Response:
    url = f"{PROWL_API_URL}/{endpoint.lstrip('/')}"
    body_bytes = json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    headers = build_signed_headers("POST", url, body_bytes)
    headers["Content-Type"] = "application/json"
    return requests.post(url, data=body_bytes, headers=headers, timeout=timeout)


if __name__ == "__main__":
    print(f"Probando conexión firmada hacia: {PROWL_API_URL}/api/schedule?zone=lab")
    try:
        res = signed_get("/api/schedule", params={"zone": "lab"})
        print(f"Código HTTP: {res.status_code}")
        print("Respuesta del servidor:", res.text)
        if res.status_code == 200:
            print("\n✅ ¡Comunicación exitosa! El panel en Render ya debe marcar 'Nodo sincronizado' en verde.")
        elif res.status_code == 401:
            print("\n❌ Error 401: Revisa que NODE_API_SECRET sea idéntico al configurado en Render.")
        elif res.status_code == 503:
            print("\n⚠️ Error 503: Falta definir NODE_API_SECRET en las variables de entorno de Render.")
    except Exception as exc:
        print(f"\n❌ No se pudo conectar: {exc}")
