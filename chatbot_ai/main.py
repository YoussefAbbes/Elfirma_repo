import pickle
import random
import json
import os
import subprocess
import sys
from pathlib import Path
import numpy as np
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from analytics import SupplierAnalyticsAI
from typing import Any, List

# ── Load trained model artifacts ──────────────────────────────────────────────
with open("model/rf_model.pkl", "rb") as f:
    model = pickle.load(f)

with open("model/vectorizer.pkl", "rb") as f:
    vectorizer = pickle.load(f)

with open("model/label_encoder.pkl", "rb") as f:
    label_encoder = pickle.load(f)

with open("model/responses.pkl", "rb") as f:
    responses_map = pickle.load(f)

# ── FastAPI app ───────────────────────────────────────────────────────────────
app = FastAPI(title="Supplier Chatbot AI", version="1.0.0")
RAG_CHAT_ENGINE_SCRIPT = Path(__file__).resolve().parent / "scripts" / "chat_engine.py"

# Allow Symfony (localhost) to call the API
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# ── Request / Response models ─────────────────────────────────────────────────
class ChatRequest(BaseModel):
    message: str

class ChatResponse(BaseModel):
    intent:     str
    response:   str
    confidence: float


class RagRequest(BaseModel):
    query: str
    top_k: int | None = None
    min_score: float | None = None
    route_override: str | None = None
    disable_routing: bool = False
    rerank_pool_size: int | None = None
    filters: dict[str, Any] | None = None
    debug: bool = False


FALLBACK_RESPONSES = {
    "create_supplier": [
        "Sure! Let's create a new supplier. I'll open the form for you."
    ],
    "edit_supplier": [
        "Sure! Tell me which supplier you want to edit."
    ],
    "delete_supplier": [
        "Sure. Tell me which supplier you want to delete."
    ],
}


def detect_priority_intent(message: str) -> str | None:
    text = message.lower().strip()

    has_supplier_word = any(word in text for word in [
        "supplier", "suppliers", "fournisseur", "fournisseurs", "vendor", "vendors"
    ])

    if has_supplier_word and any(word in text for word in ["delete", "remove", "supprimer", "erase"]):
        return "delete_supplier"

    if has_supplier_word and any(word in text for word in ["edit", "update", "modify", "change", "modifier", "mettre a jour"]):
        return "edit_supplier"

    if has_supplier_word and any(word in text for word in ["create", "add", "new", "ajouter", "creer", "register"]):
        return "create_supplier"

    return None

# ── Prediction endpoint ───────────────────────────────────────────────────────
@app.post("/chat", response_model=ChatResponse)
def chat(request: ChatRequest):
    message = request.message.lower().strip()

    # Prioritize explicit command-like intents so critical actions are not
    # misclassified as informational intents.
    forced_intent = detect_priority_intent(message)
    if forced_intent is not None:
        possible_responses = responses_map.get(
            forced_intent,
            FALLBACK_RESPONSES.get(forced_intent, responses_map["unknown"])
        )
        response = random.choice(possible_responses)
        return ChatResponse(
            intent=forced_intent,
            response=response,
            confidence=1.0
        )

    # Vectorize input using the same TF-IDF vectorizer used in training
    X = vectorizer.transform([message]).toarray()

    # Get predicted class index
    predicted_index = model.predict(X)[0]

    # Get probabilities for ALL classes from every tree in the forest
    # shape: (1, n_classes)
    probabilities = model.predict_proba(X)[0]

    # Confidence = probability of the predicted class
    confidence = float(np.max(probabilities))

    # Decode the label back to intent name
    intent = label_encoder.inverse_transform([predicted_index])[0]

    # If confidence is too low, fall back to unknown
    if confidence < 0.30:
        intent = "unknown"

    # Pick a random response for this intent
    possible_responses = responses_map.get(intent, responses_map["unknown"])
    response = random.choice(possible_responses)

    return ChatResponse(
        intent=intent,
        response=response,
        confidence=round(confidence, 4)
    )

# ── Health check ──────────────────────────────────────────────────────────────
@app.get("/health")
def health():
    return {
        "status": "ok",
        "model":  "Random Forest",
        "classes": list(label_encoder.classes_)
    }


@app.get("/rag/health")
def rag_health():
    return {
        "ok": True,
        "service": "rag",
        "wrapped_by": "chatbot_ai",
    }


@app.post("/rag/chat")
def rag_chat(request: RagRequest):
    if not RAG_CHAT_ENGINE_SCRIPT.exists():
        raise HTTPException(status_code=500, detail=f"Missing RAG engine script: {RAG_CHAT_ENGINE_SCRIPT}")

    command = [
        sys.executable,
        str(RAG_CHAT_ENGINE_SCRIPT),
        "--json",
        "--query",
        request.query,
    ]

    if request.top_k is not None:
        command.extend(["--top-k", str(request.top_k)])
    if request.min_score is not None:
        command.extend(["--min-score", str(request.min_score)])
    if request.route_override is not None:
        command.extend(["--route", request.route_override])
    if request.disable_routing:
        command.append("--disable-routing")
    if request.rerank_pool_size is not None:
        command.extend(["--rerank-pool-size", str(request.rerank_pool_size)])
    if request.debug:
        command.extend(["--include-context-items", "--include-prompt-payload"])

    filter_option_map = {
        "domain": "--domain",
        "document_type": "--document-type",
        "confidence": "--confidence",
        "language": "--language",
        "evidence_scope": "--evidence-scope",
    }

    for key, cli_flag in filter_option_map.items():
        values = (request.filters or {}).get(key)
        if values is None:
            continue
        if isinstance(values, str):
            values = [values]
        if not isinstance(values, list):
            continue
        for value in values:
            if isinstance(value, str) and value.strip():
                command.extend([cli_flag, value.strip()])

    timeout_seconds = float(os.environ.get("RAG_PROCESS_TIMEOUT", "60"))

    try:
        completed = subprocess.run(
            command,
            cwd=str(Path(__file__).resolve().parent),
            capture_output=True,
            text=True,
            timeout=timeout_seconds,
        )
    except subprocess.TimeoutExpired as exc:
        raise HTTPException(
            status_code=504,
            detail={
                "message": "RAG engine timed out",
                "timeout_seconds": timeout_seconds,
                "stderr": (exc.stderr or "").strip() if isinstance(exc.stderr, str) else "",
                "stdout": (exc.stdout or "").strip() if isinstance(exc.stdout, str) else "",
            },
        ) from exc

    if completed.returncode != 0:
        raise HTTPException(
            status_code=502,
            detail={
                "message": "RAG engine failed",
                "stderr": completed.stderr.strip(),
                "stdout": completed.stdout.strip(),
            },
        )

    try:
        return json.loads(completed.stdout)
    except json.JSONDecodeError as exc:
        raise HTTPException(
            status_code=502,
            detail={
                "message": "RAG engine returned invalid JSON",
                "stderr": completed.stderr.strip(),
                "stdout": completed.stdout.strip(),
                "error": str(exc),
            },
        ) from exc
analytics_ai = SupplierAnalyticsAI()

# ── Analytics request model ───────────────────────────────────────────────────
class RatingItem(BaseModel):
    stars:      int
    comment:    str = ""
    created_at: str = ""

class SupplierRatings(BaseModel):
    supplier_id:   int
    supplier_name: str
    ratings:       List[RatingItem]

class AnalyticsRequest(BaseModel):
    suppliers: List[SupplierRatings]

# ── Analytics endpoint ────────────────────────────────────────────────────────
@app.post("/analyze")
def analyze(request: AnalyticsRequest):
    results = []
    for supplier in request.suppliers:
        data = {
            'supplier_id':   supplier.supplier_id,
            'supplier_name': supplier.supplier_name,
            'ratings': [
                {
                    'stars':      r.stars,
                    'comment':    r.comment,
                    'created_at': r.created_at,
                }
                for r in supplier.ratings
            ]
        }
        result = analytics_ai.analyze_supplier(data)
        results.append(result)

    # Sort by avg_stars ascending (worst first) for priority review
    results.sort(key=lambda x: x['avg_stars'])

    return {
        'total_suppliers_analyzed': len(results),
        'results': results
    }
# ── Run directly ──────────────────────────────────────────────────────────────
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8002, reload=True)