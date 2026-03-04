"""
OpsMan AI Micro-Service
Flask app exposing ML-powered analytics endpoints on port 5001.

Endpoints
---------
GET /api/predict-delay
GET /api/bottlenecks
GET /api/performance-insights
GET /api/employee-score
"""

import logging
import os
import sys

from flask import Flask, jsonify, request

try:
    from flask_cors import CORS
    CORS_AVAILABLE = True
except ImportError:
    CORS_AVAILABLE = False

# Ensure the ai-service directory is in the path so relative imports work
sys.path.insert(0, os.path.dirname(__file__))

from models.delay_predictor import DelayPredictor
from models.performance_analyzer import PerformanceAnalyzer

# ── App setup ─────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-8s %(name)s: %(message)s",
)
logger = logging.getLogger("opsman-ai")

app = Flask(__name__)
if CORS_AVAILABLE:
    CORS(app)

# Instantiate models once at startup
predictor = DelayPredictor()
predictor.train()   # trains if enough DB data exists; otherwise uses heuristic

analyzer = PerformanceAnalyzer()

# ── Routes ────────────────────────────────────────────────────────────


@app.route("/api/predict-delay", methods=["GET"])
def predict_delay():
    """
    Query params:
      - task_type                  (str)
      - priority                   (str: low|medium|high|urgent)
      - days_until_deadline        (float)
      - employee_performance_score (float)
    """
    task_type  = request.args.get("task_type", "cargo_inspection")
    priority   = request.args.get("priority", "medium")
    days       = float(request.args.get("days_until_deadline", 3))
    perf_score = float(request.args.get("employee_performance_score", 80))

    result = predictor.predict(task_type, priority, days, perf_score)
    return jsonify({"success": True, "data": result})


@app.route("/api/bottlenecks", methods=["GET"])
def bottlenecks():
    """Return identified operational bottlenecks."""
    result = analyzer.identify_bottlenecks()
    return jsonify({"success": True, "data": result})


@app.route("/api/performance-insights", methods=["GET"])
def performance_insights():
    """Return overall performance insights."""
    result = analyzer.get_performance_insights()
    return jsonify({"success": True, "data": result})


@app.route("/api/employee-score", methods=["GET"])
def employee_score():
    """Return composite performance scores for all employees."""
    result = analyzer.get_employee_scores()
    return jsonify({"success": True, "data": result})


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok", "service": "opsman-ai", "version": "1.0.0"})


# ── Entry point ───────────────────────────────────────────────────────

if __name__ == "__main__":
    port = int(os.getenv("AI_SERVICE_PORT", "5001"))
    debug = os.getenv("FLASK_DEBUG", "false").lower() == "true"
    logger.info("Starting OpsMan AI service on port %d (debug=%s)", port, debug)
    app.run(host="0.0.0.0", port=port, debug=debug)
