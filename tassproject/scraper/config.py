import os
from dataclasses import dataclass
from dotenv import load_dotenv

load_dotenv()


def _bool(name: str, default: bool) -> bool:
    val = os.getenv(name)
    if val is None:
        return default
    return val.strip().lower() in ("1", "true", "yes", "on")


def _int(name: str, default: int) -> int:
    val = os.getenv(name)
    return int(val) if val else default


@dataclass(frozen=True)
class Config:
    # DB_CONNECTION selects which backend db.py talks to: "sqlite" or "mysql".
    # Must match backend/.env's DB_CONNECTION.
    db_connection: str = os.getenv("DB_CONNECTION", "sqlite").strip().lower()

    # Used when db_connection == "sqlite" — path to the .sqlite file,
    # relative to this scraper folder (default matches Laravel's default
    # location: ../backend/database/database.sqlite)
    db_sqlite_path: str = os.getenv("DB_SQLITE_PATH", "../backend/database/database.sqlite")

    # Used when db_connection == "mysql"
    db_host: str = os.getenv("DB_HOST", "127.0.0.1")
    db_port: int = _int("DB_PORT", 3306)
    db_database: str = os.getenv("DB_DATABASE", "tassproject")
    db_username: str = os.getenv("DB_USERNAME", "root")
    db_password: str = os.getenv("DB_PASSWORD", "")

    hunter_api_key: str = os.getenv("HUNTER_API_KEY", "").strip()
    mock_mode: bool = _bool("MOCK_MODE", True)

    max_requests_per_minute: int = _int("MAX_REQUESTS_PER_MINUTE", 10)
    min_delay_seconds: float = float(os.getenv("MIN_DELAY_SECONDS", 2))
    max_delay_seconds: float = float(os.getenv("MAX_DELAY_SECONDS", 5))
    results_per_query: int = _int("RESULTS_PER_QUERY", 10)
    min_confidence_score: int = _int("MIN_CONFIDENCE_SCORE", 70)
    poll_interval_seconds: int = _int("POLL_INTERVAL_SECONDS", 5)

    @property
    def hunter_mock(self) -> bool:
        """Hunter enrichment is mocked if no real key is set, regardless of MOCK_MODE."""
        return self.mock_mode or not self.hunter_api_key or self.hunter_api_key.lower() == "mock"


config = Config()
