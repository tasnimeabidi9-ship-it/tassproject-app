import random
from dataclasses import dataclass
from typing import List

import requests

from config import config

HUNTER_DOMAIN_SEARCH_URL = "https://api.hunter.io/v2/domain-search"


@dataclass
class EnrichedEmail:
    email: str
    first_name: str = ""
    last_name: str = ""
    position: str = ""
    confidence: int = 0


def _mock_enrich(domain: str) -> List[EnrichedEmail]:
    random.seed(hash(domain) % (2**32))
    first_names = ["Amine", "Sara", "Yassine", "Rania", "Karim", "Nour"]
    last_names = ["Ben Ali", "Trabelsi", "Gharbi", "Jendoubi", "Chaabane"]
    positions = ["Owner", "Manager", "Founder", "Sales Director"]

    count = random.randint(0, 2)  # sometimes Hunter finds nothing — realistic
    results = []
    for _ in range(count):
        fn = random.choice(first_names)
        ln = random.choice(last_names)
        local = f"{fn.lower()}.{ln.lower().replace(' ', '')}"
        results.append(
            EnrichedEmail(
                email=f"{local}@{domain}",
                first_name=fn,
                last_name=ln,
                position=random.choice(positions),
                confidence=random.randint(55, 97),
            )
        )
    return results


def enrich_domain(domain: str) -> List[EnrichedEmail]:
    """Returns emails found for a domain, already filtered to
    confidence >= config.min_confidence_score. Falls back to mock data
    automatically if no real Hunter.io key is configured.
    """
    if not domain:
        return []

    if config.hunter_mock:
        found = _mock_enrich(domain)
    else:
        try:
            resp = requests.get(
                HUNTER_DOMAIN_SEARCH_URL,
                params={"domain": domain, "api_key": config.hunter_api_key},
                timeout=10,
            )
            resp.raise_for_status()
            data = resp.json().get("data", {})
            found = [
                EnrichedEmail(
                    email=e.get("value", ""),
                    first_name=e.get("first_name") or "",
                    last_name=e.get("last_name") or "",
                    position=e.get("position") or "",
                    confidence=e.get("confidence", 0),
                )
                for e in data.get("emails", [])
                if e.get("value")
            ]
        except requests.RequestException:
            # Network/API error — treat as "nothing found" rather than
            # crashing the whole worker over one domain.
            return []

    return [e for e in found if e.confidence >= config.min_confidence_score]
