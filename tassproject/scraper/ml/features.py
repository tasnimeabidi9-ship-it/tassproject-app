"""
Feature extraction shared by training and inference, so the model always
sees features engineered exactly the same way in both places.
"""
from typing import List
from urllib.parse import urlparse

SOURCE_DIRECTORIES = ["facebook.com", "yellow.tn", "pagesjaunes.tn", "other"]


def _terms(profile: dict) -> List[str]:
    terms = []
    for key in ("industry", "location", "job_title"):
        val = profile.get(key)
        if val:
            terms.append(str(val).lower())

    keywords = profile.get("keywords") or []
    if isinstance(keywords, str):
        keywords = [k.strip() for k in keywords.split(",") if k.strip()]
    terms.extend(k.lower() for k in keywords)
    return terms


def _source_directory(source_url: str) -> str:
    if not source_url:
        return "other"
    netloc = urlparse(source_url).netloc.lower().replace("www.", "")
    for d in SOURCE_DIRECTORIES[:-1]:
        if d in netloc:
            return d
    return "other"


def extract_features(profile: dict, business_name: str, snippet: str, domain: str, source_url: str) -> dict:
    """Returns a flat dict of numeric/categorical features for one lead
    candidate, given the target profile it was matched against.
    """
    haystack = f"{business_name or ''} {snippet or ''}".lower()
    terms = _terms(profile)

    industry = (profile.get("industry") or "").lower()
    location = (profile.get("location") or "").lower()
    job_title = (profile.get("job_title") or "").lower()

    keyword_matches = sum(1 for t in terms if t and t in haystack)
    keyword_ratio = keyword_matches / len(terms) if terms else 0.0

    return {
        "keyword_ratio": keyword_ratio,
        "industry_match": 1 if industry and industry in haystack else 0,
        "location_match": 1 if location and location in haystack else 0,
        "job_title_match": 1 if job_title and job_title in haystack else 0,
        "name_length": len(business_name or ""),
        "is_tn_domain": 1 if domain and domain.endswith(".tn") else 0,
        "source_directory": _source_directory(source_url),
        "num_profile_terms": len(terms),
    }


FEATURE_COLUMNS_NUMERIC = [
    "keyword_ratio",
    "industry_match",
    "location_match",
    "job_title_match",
    "name_length",
    "is_tn_domain",
    "num_profile_terms",
]
FEATURE_COLUMNS_CATEGORICAL = ["source_directory"]
