import os
import sqlite3
from contextlib import contextmanager
from datetime import datetime, timezone
from typing import Optional

from config import config

IS_SQLITE = config.db_connection == "sqlite"
PH = "?" if IS_SQLITE else "%s"  # SQL parameter placeholder style


def _now_str() -> str:
    """Laravel stores timestamps as 'Y-m-d H:i:s' text in SQLite."""
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


@contextmanager
def get_connection():
    if IS_SQLITE:
        db_path = os.path.normpath(
            os.path.join(os.path.dirname(__file__), config.db_sqlite_path)
        )
        if not os.path.exists(db_path):
            raise FileNotFoundError(
                f"SQLite database not found at {db_path}. Check DB_SQLITE_PATH in scraper/.env "
                f"— it should point to backend/database/database.sqlite"
            )
        conn = sqlite3.connect(db_path)
        conn.row_factory = sqlite3.Row
        conn.execute("PRAGMA foreign_keys = ON")
        try:
            yield conn
            conn.commit()
        finally:
            conn.close()
    else:
        import pymysql
        import pymysql.cursors

        conn = pymysql.connect(
            host=config.db_host,
            port=config.db_port,
            user=config.db_username,
            password=config.db_password,
            database=config.db_database,
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True,
        )
        try:
            yield conn
        finally:
            conn.close()


def _to_dict(row) -> Optional[dict]:
    if row is None:
        return None
    if isinstance(row, dict):
        return row
    return dict(row)  # sqlite3.Row -> dict


def fetch_pending_job() -> Optional[dict]:
    """Grabs the oldest pending job and atomically marks it running, so
    multiple worker instances never double-process the same job."""
    with get_connection() as conn:
        cur = conn.cursor()
        cur.execute(
            "SELECT * FROM scrape_jobs WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1"
        )
        job = _to_dict(cur.fetchone())
        if not job:
            return None

        cur.execute(
            f"UPDATE scrape_jobs SET status = 'running', started_at = {PH}, updated_at = {PH} "
            f"WHERE id = {PH} AND status = 'pending'",
            (_now_str(), _now_str(), job["id"]),
        )
        if cur.rowcount == 0:
            return None  # another worker beat us to it

        job["status"] = "running"
        return job


def get_profile(profile_id: int) -> Optional[dict]:
    with get_connection() as conn:
        cur = conn.cursor()
        cur.execute(f"SELECT * FROM target_profiles WHERE id = {PH}", (profile_id,))
        profile = _to_dict(cur.fetchone())

    if profile and profile.get("keywords"):
        # Laravel's JSON cast stores keywords as a JSON string in the DB;
        # decode it back into a Python list here.
        import json

        try:
            profile["keywords"] = json.loads(profile["keywords"])
        except (TypeError, ValueError):
            pass

    return profile


def increment_job_progress(job_id: int, *, queries_run=0, leads_found=0, emails_enriched=0) -> None:
    with get_connection() as conn:
        cur = conn.cursor()
        cur.execute(
            f"""
            UPDATE scrape_jobs
            SET queries_run = queries_run + {PH},
                leads_found = leads_found + {PH},
                emails_enriched = emails_enriched + {PH},
                updated_at = {PH}
            WHERE id = {PH}
            """,
            (queries_run, leads_found, emails_enriched, _now_str(), job_id),
        )


def mark_job_finished(job_id: int, status: str) -> None:
    """status: 'completed' or 'failed'"""
    with get_connection() as conn:
        cur = conn.cursor()
        cur.execute(
            f"UPDATE scrape_jobs SET status = {PH}, finished_at = {PH}, updated_at = {PH} WHERE id = {PH}",
            (status, _now_str(), _now_str(), job_id),
        )


def lead_exists(*, email: Optional[str] = None, domain: Optional[str] = None) -> bool:
    """Dedup against both leads_queue and the already-approved contacts table."""
    if not email and not domain:
        return False

    with get_connection() as conn:
        cur = conn.cursor()
        if email:
            cur.execute(f"SELECT id FROM leads_queue WHERE email = {PH} LIMIT 1", (email,))
            if cur.fetchone():
                return True
            cur.execute(f"SELECT id FROM contacts WHERE email = {PH} LIMIT 1", (email,))
            if cur.fetchone():
                return True
        if domain:
            cur.execute(f"SELECT id FROM leads_queue WHERE domain = {PH} LIMIT 1", (domain,))
            if cur.fetchone():
                return True
            cur.execute(f"SELECT id FROM contacts WHERE domain = {PH} LIMIT 1", (domain,))
            if cur.fetchone():
                return True
    return False


def insert_lead(
    *,
    profile_id: Optional[int],
    raw_name: Optional[str],
    domain: Optional[str],
    email: Optional[str],
    confidence_score: Optional[int],
    match_score: Optional[int],
    source_url: Optional[str],
) -> None:
    now = _now_str()
    with get_connection() as conn:
        cur = conn.cursor()
        cur.execute(
            f"""
            INSERT INTO leads_queue
                (profile_id, raw_name, domain, email, confidence_score, match_score,
                 source_url, approval_status, found_at, created_at, updated_at)
            VALUES ({PH}, {PH}, {PH}, {PH}, {PH}, {PH}, {PH}, 'pending_review', {PH}, {PH}, {PH})
            """,
            (profile_id, raw_name, domain, email, confidence_score, match_score, source_url, now, now, now),
        )
