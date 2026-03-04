"""
OpsMan AI Service – MySQL connector utility.
Reads credentials from environment / .env file.
"""

import os
import logging

try:
    import mysql.connector
    from mysql.connector import Error
    MYSQL_AVAILABLE = True
except ImportError:  # pragma: no cover
    MYSQL_AVAILABLE = False

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

logger = logging.getLogger(__name__)


def get_connection():
    """Return a MySQL connection or None on failure."""
    if not MYSQL_AVAILABLE:
        logger.warning("mysql-connector-python is not installed.")
        return None

    try:
        conn = mysql.connector.connect(
            host=os.getenv("DB_HOST", "localhost"),
            port=int(os.getenv("DB_PORT", "3306")),
            database=os.getenv("DB_NAME", "opsman"),
            user=os.getenv("DB_USER", "root"),
            password=os.getenv("DB_PASSWORD", ""),
            connection_timeout=5,
        )
        return conn
    except Error as exc:
        logger.error("DB connection error: %s", exc)
        return None


def fetch_all(query: str, params: tuple = ()) -> list[dict]:
    """
    Execute *query* with *params* and return all rows as a list of dicts.
    Returns an empty list if the connection fails.
    """
    conn = get_connection()
    if conn is None:
        return []

    try:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params)
        rows = cursor.fetchall()
        return rows
    except Error as exc:
        logger.error("Query error: %s", exc)
        return []
    finally:
        try:
            cursor.close()
        except Exception:
            pass
        conn.close()
