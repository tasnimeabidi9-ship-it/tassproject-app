"""
Loads the trained model (if present) and turns predict_proba into the
0-100 match_score the rest of the app expects. Falls back to the simple
heuristic if no model has been trained yet, so the worker never breaks.
"""
import os

from ml.features import FEATURE_COLUMNS_CATEGORICAL, FEATURE_COLUMNS_NUMERIC, extract_features

_MODEL_PATH = os.path.join(os.path.dirname(__file__), "model.pkl")
_model = None
_model_load_attempted = False


def _load_model():
    global _model, _model_load_attempted
    if _model_load_attempted:
        return _model
    _model_load_attempted = True

    if not os.path.exists(_MODEL_PATH):
        return None

    try:
        import joblib

        _model = joblib.load(_MODEL_PATH)
    except Exception as e:
        print(f"[ml] Failed to load model.pkl, falling back to heuristic scoring: {e}")
        _model = None

    return _model


def predict_score(profile: dict, business_name: str, snippet: str, domain: str, source_url: str) -> int:
    """Returns a 0-100 match score. Uses the trained ML model if available,
    otherwise falls back to a simple keyword-overlap heuristic.
    """
    features = extract_features(profile, business_name, snippet, domain, source_url)

    model = _load_model()
    if model is not None:
        import pandas as pd

        row = pd.DataFrame([features])[FEATURE_COLUMNS_NUMERIC + FEATURE_COLUMNS_CATEGORICAL]
        proba = model.predict_proba(row)[0, 1]
        return round(proba * 100)

    # Heuristic fallback (same idea as the old scoring.py, using the
    # shared feature extraction so both paths stay consistent).
    score = (
        40
        + features["keyword_ratio"] * 30
        + features["industry_match"] * 10
        + features["location_match"] * 10
        + features["job_title_match"] * 10
    )
    return round(min(100, max(0, score)))
