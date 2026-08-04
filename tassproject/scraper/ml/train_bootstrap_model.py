"""
Trains the initial lead-scoring model on SYNTHETIC data, since no real
outreach/reply data exists yet (that comes in Phases 5-6).

The synthetic data is not random noise — it encodes the same domain
assumptions the old heuristic used (keyword/location/title match predicts
lead quality), but expressed as *probabilistic* outcomes with realistic
noise, so the model learns a genuine probability estimate (via
predict_proba) rather than just replaying a fixed formula. This gives you
a real, working ML model today.

Run this once, FROM THE scraper/ FOLDER, to produce ml/model.pkl:
    python -m ml.train_bootstrap_model
(Must use -m so the ml.features import resolves correctly as a package.)

Once real reply/conversion data exists (see retrain_from_outcomes.py),
retrain on that instead — same script structure, real labels.
"""
import random

import joblib
import numpy as np
import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.metrics import roc_auc_score
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder

from ml.features import FEATURE_COLUMNS_CATEGORICAL, FEATURE_COLUMNS_NUMERIC, SOURCE_DIRECTORIES

MODEL_PATH = "ml/model.pkl"
N_SAMPLES = 4000
RANDOM_SEED = 42


def generate_synthetic_dataset(n=N_SAMPLES, seed=RANDOM_SEED) -> pd.DataFrame:
    rng = np.random.default_rng(seed)
    random.seed(seed)

    rows = []
    for _ in range(n):
        keyword_ratio = rng.beta(2, 3)  # skewed toward lower ratios, realistic
        industry_match = rng.binomial(1, 0.5)
        location_match = rng.binomial(1, 0.6)
        job_title_match = rng.binomial(1, 0.4)
        name_length = int(np.clip(rng.normal(22, 8), 5, 60))
        is_tn_domain = rng.binomial(1, 0.55)
        source_directory = random.choice(SOURCE_DIRECTORIES)
        num_profile_terms = rng.integers(1, 6)

        # True latent "quality" combines the signals with realistic weights
        # plus noise — this is the domain assumption being encoded.
        latent = (
            0.35 * keyword_ratio
            + 0.20 * industry_match
            + 0.20 * location_match
            + 0.10 * job_title_match
            + 0.10 * is_tn_domain
            + 0.05 * (source_directory == "yellow.tn")
            + rng.normal(0, 0.15)
        )
        prob_positive = 1 / (1 + np.exp(-6 * (latent - 0.45)))  # squashed to [0,1]
        label = rng.binomial(1, prob_positive)

        rows.append(
            {
                "keyword_ratio": keyword_ratio,
                "industry_match": industry_match,
                "location_match": location_match,
                "job_title_match": job_title_match,
                "name_length": name_length,
                "is_tn_domain": is_tn_domain,
                "source_directory": source_directory,
                "num_profile_terms": num_profile_terms,
                "label": label,
            }
        )

    return pd.DataFrame(rows)


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
    df = generate_synthetic_dataset()
    X = df[FEATURE_COLUMNS_NUMERIC + FEATURE_COLUMNS_CATEGORICAL]
    y = df["label"]

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=RANDOM_SEED, stratify=y
    )

    pipeline = build_pipeline()
    pipeline.fit(X_train, y_train)

    preds = pipeline.predict_proba(X_test)[:, 1]
    auc = roc_auc_score(y_test, preds)
    print(f"Validation AUC on held-out synthetic data: {auc:.3f}")
    print("(This confirms the model learned the intended signal structure.")
    print(" It does NOT mean the model reflects real-world lead quality yet —")
    print(" that only happens once you retrain on real outcomes. See")
    print(" retrain_from_outcomes.py for that step, once Phases 5-6 are live.)")

    joblib.dump(pipeline, MODEL_PATH)
    print(f"Saved model to {MODEL_PATH}")


if __name__ == "__main__":
    main()
