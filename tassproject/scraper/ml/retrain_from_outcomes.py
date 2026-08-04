"""
Retrains the lead-scoring model on REAL outcome data instead of the
synthetic bootstrap dataset. Run this periodically once you have enough
approved/rejected/won/lost contacts to learn from.

Run FROM THE scraper/ FOLDER:
    python -m ml.retrain_from_outcomes

LABEL SOURCE (this will get better as later phases go live):
  Right now (before Phases 5-6 exist), the only real signal available is
  what an admin decided at approval time and how the contact's pipeline
  stage evolved:
    label = 1  if contact.stage in ('won', 'interested')
    label = 0  if contact.stage in ('lost')
  Contacts still sitting at lead_found/approved/contacted are excluded
  (no outcome yet — including them as either label would be a guess).

  Once Phase 5-6 (outreach + replies) are live, swap the label query
  below to use actual reply/open data instead — e.g. "replied within 14
  days of first email" — which is a much stronger signal than pipeline
  stage alone. The training/eval code below doesn't need to change, only
  the SQL in `fetch_training_rows()`.
"""
import sys

import joblib
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.metrics import roc_auc_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

import db
from ml.features import FEATURE_COLUMNS_CATEGORICAL, FEATURE_COLUMNS_NUMERIC, extract_features
from ml.train_bootstrap_model import MODEL_PATH, RANDOM_SEED

MIN_ROWS_TO_RETRAIN = 50  # below this, real data is too sparse to trust


def fetch_training_rows() -> pd.DataFrame:
    """Joins contacts back to the leads_queue row (and target_profiles)
    that produced them, using leads_queue.profile_id, so we can
    reconstruct the same features used at scrape time.
    """
    with db.get_connection() as conn:
        cur = conn.cursor()
        cur.execute(
            """
            SELECT
                c.id AS contact_id,
                c.name AS business_name,
                c.domain AS domain,
                c.source_url AS source_url,
                c.stage AS stage,
                lq.profile_id AS profile_id
            FROM contacts c
            LEFT JOIN leads_queue lq
                ON lq.email = c.email OR lq.domain = c.domain
            WHERE c.stage IN ('won', 'interested', 'lost')
            """
        )
        rows = cur.fetchall()
        rows = [dict(r) if not isinstance(r, dict) else r for r in rows]

    profile_cache: dict = {}
    training_rows = []

    for row in rows:
        profile_id = row.get("profile_id")
        if not profile_id:
            continue  # can't reconstruct features without knowing the profile

        if profile_id not in profile_cache:
            profile_cache[profile_id] = db.get_profile(profile_id)
        profile = profile_cache[profile_id]
        if not profile:
            continue

        features = extract_features(
            profile,
            business_name=row["business_name"],
            snippet="",  # not stored on contacts; acceptable — keyword_ratio
                          # will be computed from name only for retraining rows
            domain=row["domain"] or "",
            source_url=row["source_url"] or "",
        )
        features["label"] = 1 if row["stage"] in ("won", "interested") else 0
        training_rows.append(features)

    return pd.DataFrame(training_rows)


def build_pipeline() -> Pipeline:
    preprocessor = ColumnTransformer(
        transformers=[
            ("num", "passthrough", FEATURE_COLUMNS_NUMERIC),
            ("cat", OneHotEncoder(handle_unknown="ignore"), FEATURE_COLUMNS_CATEGORICAL),
        ]
    )
    model = GradientBoostingClassifier(random_state=RANDOM_SEED)
    return Pipeline([("preprocess", preprocessor), ("model", model)])


def main():
    df = fetch_training_rows()

    if len(df) < MIN_ROWS_TO_RETRAIN:
        print(
            f"Only {len(df)} labeled contact(s) found (won/interested/lost with a "
            f"traceable profile). Need at least {MIN_ROWS_TO_RETRAIN} to retrain "
            f"reliably — keep using the bootstrap model for now."
        )
        sys.exit(0)

    X = df[FEATURE_COLUMNS_NUMERIC + FEATURE_COLUMNS_CATEGORICAL]
    y = df["label"]

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=RANDOM_SEED, stratify=y
    )

    pipeline = build_pipeline()
    pipeline.fit(X_train, y_train)

    preds = pipeline.predict_proba(X_test)[:, 1]
    auc = roc_auc_score(y_test, preds)
    print(f"Retrained on {len(df)} real contacts. Validation AUC: {auc:.3f}")

    joblib.dump(pipeline, MODEL_PATH)
    print(f"Saved retrained model to {MODEL_PATH}")


if __name__ == "__main__":
    main()
