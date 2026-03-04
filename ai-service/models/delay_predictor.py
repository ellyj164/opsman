"""
OpsMan AI Service – Delay Predictor
Uses a RandomForestClassifier trained on historical task data to predict
whether a task is likely to be delayed.
"""

import logging
from typing import Any

import numpy as np

try:
    from sklearn.ensemble import RandomForestClassifier
    from sklearn.preprocessing import LabelEncoder
    SKLEARN_AVAILABLE = True
except ImportError:  # pragma: no cover
    SKLEARN_AVAILABLE = False

from utils.db_connector import fetch_all

logger = logging.getLogger(__name__)

# Feature encoding maps
TASK_TYPE_MAP = {
    "customs_declaration": 0,
    "warehouse_inspection": 1,
    "border_transit_supervision": 2,
    "cargo_inspection": 3,
}
PRIORITY_MAP = {"low": 0, "medium": 1, "high": 2, "urgent": 3}


class DelayPredictor:
    """Train on historical tasks; predict delay probability for new tasks."""

    MIN_SAMPLES = 5  # minimum training rows required

    def __init__(self) -> None:
        self._model: Any = None
        self._trained = False

    # ------------------------------------------------------------------
    # Training
    # ------------------------------------------------------------------

    def _load_training_data(self) -> tuple[list, list]:
        """
        Pull completed/overdue tasks from the DB and build feature/label arrays.
        Returns (X, y) where X is list-of-lists and y is list-of-ints.
        """
        rows = fetch_all(
            """
            SELECT t.task_type,
                   t.priority,
                   COALESCE(DATEDIFF(t.deadline, t.created_at), 7) AS days_given,
                   COALESCE(e.performance_score, 80)               AS perf_score,
                   (t.status = 'overdue')                          AS is_delayed
              FROM tasks t
              LEFT JOIN employees e ON e.id = t.assigned_to
             WHERE t.status IN ('completed', 'overdue')
               AND t.deadline IS NOT NULL
            """
        )
        X, y = [], []
        for r in rows:
            X.append([
                TASK_TYPE_MAP.get(r.get("task_type", ""), 0),
                PRIORITY_MAP.get(r.get("priority", "medium"), 1),
                float(r.get("days_given") or 7),
                float(r.get("perf_score") or 80),
            ])
            y.append(int(r.get("is_delayed") or 0))
        return X, y

    def train(self) -> bool:
        """Train the model. Returns True on success, False on insufficient data."""
        if not SKLEARN_AVAILABLE:
            logger.warning("scikit-learn not available; skipping training.")
            return False

        X, y = self._load_training_data()
        if len(X) < self.MIN_SAMPLES:
            logger.info("Insufficient training data (%d rows); using heuristic.", len(X))
            return False

        self._model = RandomForestClassifier(n_estimators=50, random_state=42)
        self._model.fit(np.array(X), np.array(y))
        self._trained = True
        logger.info("DelayPredictor trained on %d samples.", len(X))
        return True

    # ------------------------------------------------------------------
    # Prediction
    # ------------------------------------------------------------------

    def predict(
        self,
        task_type: str,
        priority: str,
        days_until_deadline: float,
        employee_performance_score: float,
    ) -> dict:
        """
        Return a delay prediction dict with:
          - will_be_delayed: bool
          - delay_probability: float [0.0–1.0]
          - confidence: str ('high'|'medium'|'low')
          - factors: list[str]
        """
        features = [
            TASK_TYPE_MAP.get(task_type, 0),
            PRIORITY_MAP.get(priority, 1),
            float(days_until_deadline),
            float(employee_performance_score),
        ]

        if self._trained and self._model is not None:
            proba = self._model.predict_proba([features])[0]
            # proba shape: [p_no_delay, p_delay]
            delay_prob = float(proba[1]) if len(proba) > 1 else float(proba[0])
        else:
            # Heuristic fallback
            delay_prob = self._heuristic(features)

        will_delay = delay_prob >= 0.5
        confidence = "high" if abs(delay_prob - 0.5) > 0.3 else "medium" if abs(delay_prob - 0.5) > 0.1 else "low"

        factors = []
        if PRIORITY_MAP.get(priority, 1) >= 2:
            factors.append("High task priority increases risk")
        if days_until_deadline <= 1:
            factors.append("Deadline is imminent (≤1 day)")
        if employee_performance_score < 75:
            factors.append("Low employee performance score")
        if task_type in ("border_transit_supervision", "customs_declaration"):
            factors.append("Task type historically prone to delays")

        return {
            "will_be_delayed": will_delay,
            "delay_probability": round(delay_prob, 3),
            "confidence": confidence,
            "factors": factors,
            "model_trained": self._trained,
        }

    # ------------------------------------------------------------------

    @staticmethod
    def _heuristic(features: list) -> float:
        """Simple rule-based probability when no trained model is available."""
        _, priority_idx, days, perf = features
        score = 0.3  # base probability
        if priority_idx >= 3:
            score += 0.2
        if days <= 1:
            score += 0.25
        if days <= 0:
            score += 0.15
        if perf < 75:
            score += 0.15
        return min(score, 0.95)
