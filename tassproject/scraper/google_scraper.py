import random
import re
import time
from dataclasses import dataclass
from typing import List
from urllib.parse import urlparse, quote_plus

from config import config


@dataclass
class SearchResult:
    business_name: str
    website_url: str
    snippet: str = ""


def build_queries(profile: dict) -> List[str]:
    """Turns a target profile into a handful of Google search queries,
    following the pattern from the spec:
    site:facebook.com OR site:yellow.tn "atelier réparation" Tunisia contact
    """
    industry = (profile.get("industry") or "").strip()
    location = (profile.get("location") or "").strip()
    job_title = (profile.get("job_title") or "").strip()
    keywords = profile.get("keywords") or []
    if isinstance(keywords, str):
        keywords = [k.strip() for k in keywords.split(",") if k.strip()]

    terms = [t for t in [industry, *keywords] if t]
    if not terms:
        terms = [profile.get("name", "business")]

    directories = ["site:facebook.com", "site:yellow.tn", "site:pagesjaunes.tn"]

    queries = []
    for term in terms[:4]:  # cap so one profile doesn't explode into dozens of queries
        directory_clause = " OR ".join(directories)
        parts = [f"({directory_clause})", f'"{term}"']
        if location:
            parts.append(location)
        if job_title:
            parts.append(job_title)
        parts.append("contact")
        queries.append(" ".join(parts))

    return queries


def extract_domain(url: str) -> str:
    try:
        netloc = urlparse(url).netloc
        return netloc.replace("www.", "").lower()
    except Exception:
        return ""


class RateLimiter:
    """Simple token-bucket limiter: max N requests per rolling 60s window,
    plus a random human-like delay between each request."""

    def __init__(self, max_per_minute: int, min_delay: float, max_delay: float):
        self.max_per_minute = max_per_minute
        self.min_delay = min_delay
        self.max_delay = max_delay
        self._timestamps: List[float] = []

    def wait(self):
        now = time.time()
        self._timestamps = [t for t in self._timestamps if now - t < 60]

        if len(self._timestamps) >= self.max_per_minute:
            sleep_for = 60 - (now - self._timestamps[0])
            if sleep_for > 0:
                time.sleep(sleep_for)

        time.sleep(random.uniform(self.min_delay, self.max_delay))
        self._timestamps.append(time.time())


_rate_limiter = RateLimiter(
    config.max_requests_per_minute, config.min_delay_seconds, config.max_delay_seconds
)


def _mock_search(query: str, max_results: int) -> List[SearchResult]:
    """Deterministic-ish fake results so demos/dev work without hitting Google."""
    fake_businesses = [
        ("Atelier Electro Sousse", "atelier-electro-sousse.tn"),
        ("Reparation Rapide Tunis", "reparation-rapide-tn.com"),
        ("TechFix Sfax", "techfix-sfax.tn"),
        ("Sara Comptabilite Freelance", "sara-comptable-tn.com"),
        ("Hisebi Partners", "hisebi-partners.tn"),
        ("Mecanique Plus Sousse", "mecaplus-sousse.tn"),
    ]
    random.seed(hash(query) % (2**32))
    sample = random.sample(fake_businesses, k=min(max_results, len(fake_businesses)))
    return [
        SearchResult(
            business_name=name,
            website_url=f"https://{domain}",
            snippet=f"Contact us — {query[:40]}...",
        )
        for name, domain in sample
    ]


def run_search(query: str, max_results: int = None) -> List[SearchResult]:
    max_results = max_results or config.results_per_query

    if config.mock_mode:
        return _mock_search(query, max_results)

    # Real scraping path — requires `playwright install chromium` to have
    # been run once. Kept isolated behind this import so mock mode never
    # needs Playwright installed at all.
    from playwright.sync_api import sync_playwright

    _rate_limiter.wait()

    user_agents = [
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36",
    ]

    results: List[SearchResult] = []

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(user_agent=random.choice(user_agents))
        try:
            url = f"https://www.google.com/search?q={quote_plus(query)}&num={max_results}"
            page.goto(url, timeout=15000)

            # Google's DOM classes change often — this is a best-effort
            # extraction of organic result blocks. Expect to need to
            # adjust these selectors over time, and expect occasional
            # CAPTCHA pages (see README: this is the #1 real-world risk
            # with Google scraping, per the spec's own risk table).
            blocks = page.query_selector_all("div.g")
            for block in blocks[:max_results]:
                link_el = block.query_selector("a")
                title_el = block.query_selector("h3")
                if not link_el or not title_el:
                    continue

                href = link_el.get_attribute("href") or ""
                title = title_el.inner_text() or ""
                snippet_el = block.query_selector("div[data-sncf], span")
                snippet = snippet_el.inner_text() if snippet_el else ""

                if href.startswith("http"):
                    results.append(SearchResult(business_name=title, website_url=href, snippet=snippet))
        finally:
            browser.close()

    return results
