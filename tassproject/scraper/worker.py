import argparse
import sys
import time
import traceback

import db
from config import config
from google_scraper import build_queries, extract_domain, run_search
from hunter_client import enrich_domain
from ml.predict import predict_score


def process_job(job: dict) -> None:
    job_id = job["id"]
    profile = db.get_profile(job["profile_id"])
    if not profile:
        print(f"[job {job_id}] profile {job['profile_id']} not found, marking failed")
        db.mark_job_finished(job_id, "failed")
        return

    print(f"[job {job_id}] starting — profile: {profile.get('name')} (mock_mode={config.mock_mode})")

    queries = build_queries(profile)
    if not queries:
        print(f"[job {job_id}] no usable search terms in profile, marking failed")
        db.mark_job_finished(job_id, "failed")
        return

    seen_domains_this_run = set()

    for query in queries:
        print(f"[job {job_id}] query: {query}")
        try:
            results = run_search(query)
        except Exception:
            print(f"[job {job_id}] search failed for query, skipping:")
            traceback.print_exc()
            db.increment_job_progress(job_id, queries_run=1)
            continue

        db.increment_job_progress(job_id, queries_run=1)

        for result in results:
            domain = extract_domain(result.website_url)
            if not domain or domain in seen_domains_this_run:
                continue
            seen_domains_this_run.add(domain)

            if db.lead_exists(domain=domain):
                continue  # already known — dedup

            match_score = predict_score(profile, result.business_name, result.snippet, domain, result.website_url)

            enriched = enrich_domain(domain)
            if enriched:
                db.increment_job_progress(job_id, emails_enriched=1)

            if not enriched:
                # No verified email found — per spec, discard/flag as
                # incomplete rather than clutter the approval queue.
                continue

            for e in enriched:
                if db.lead_exists(email=e.email):
                    continue
                db.insert_lead(
                    profile_id=profile["id"],
                    raw_name=result.business_name,
                    domain=domain,
                    email=e.email,
                    confidence_score=e.confidence,
                    match_score=match_score,
                    source_url=result.website_url,
                )
                db.increment_job_progress(job_id, leads_found=1)
                print(f"[job {job_id}] + lead: {e.email} (confidence={e.confidence}, match={match_score})")

    db.mark_job_finished(job_id, "completed")
    print(f"[job {job_id}] completed")


def main():
    parser = argparse.ArgumentParser(description="LeadCRM AI scraper worker")
    parser.add_argument(
        "--once", action="store_true", help="Process a single pending job (if any) then exit"
    )
    args = parser.parse_args()

    print(f"Scraper worker starting. mock_mode={config.mock_mode}, hunter_mock={config.hunter_mock}")

    while True:
        job = db.fetch_pending_job()
        if not job:
            if args.once:
                print("No pending jobs.")
                sys.exit(0)
            time.sleep(config.poll_interval_seconds)
            continue

        try:
            process_job(job)
        except Exception:
            print(f"[job {job['id']}] crashed:")
            traceback.print_exc()
            db.mark_job_finished(job["id"], "failed")

        if args.once:
            sys.exit(0)


if __name__ == "__main__":
    main()
