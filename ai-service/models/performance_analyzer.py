"""
OpsMan AI Service – Performance Analyzer
Calculates employee performance metrics, identifies bottlenecks, and
generates actionable recommendations.
"""

import logging
from collections import defaultdict

from utils.db_connector import fetch_all

logger = logging.getLogger(__name__)


class PerformanceAnalyzer:
    """Analyse task / employee data and surface insights."""

    # ------------------------------------------------------------------
    # Employee performance
    # ------------------------------------------------------------------

    def get_employee_scores(self) -> list[dict]:
        """
        Return a scored list of employees based on:
          - completion rate (weight 0.5)
          - on-time rate   (weight 0.3)
          - DB performance_score (weight 0.2)
        """
        rows = fetch_all(
            """
            SELECT e.id,
                   e.full_name,
                   e.employee_code,
                   e.department,
                   e.performance_score                              AS db_score,
                   COUNT(t.id)                                      AS total_tasks,
                   SUM(t.status = 'completed')                      AS completed,
                   SUM(t.status = 'overdue')                        AS overdue,
                   SUM(t.status = 'in_progress')                    AS in_progress
              FROM employees e
              LEFT JOIN tasks t ON t.assigned_to = e.id
             GROUP BY e.id, e.full_name, e.employee_code, e.department, e.performance_score
            """
        )

        results = []
        for r in rows:
            total     = int(r.get("total_tasks") or 0)
            completed = int(r.get("completed")   or 0)
            overdue   = int(r.get("overdue")      or 0)

            completion_rate = (completed / total * 100) if total > 0 else 100.0
            on_time_rate    = ((total - overdue) / total * 100) if total > 0 else 100.0
            db_score        = float(r.get("db_score") or 80)

            composite = (
                0.5 * completion_rate
                + 0.3 * on_time_rate
                + 0.2 * db_score
            )

            results.append({
                "employee_id":       r["id"],
                "full_name":         r["full_name"],
                "employee_code":     r["employee_code"],
                "department":        r["department"],
                "total_tasks":       total,
                "completed":         completed,
                "overdue":           overdue,
                "in_progress":       int(r.get("in_progress") or 0),
                "completion_rate":   round(completion_rate, 1),
                "on_time_rate":      round(on_time_rate, 1),
                "composite_score":   round(composite, 1),
                "recommendation":    self._recommend(composite, overdue, total),
            })

        results.sort(key=lambda x: x["composite_score"], reverse=True)
        return results

    # ------------------------------------------------------------------
    # Single employee scoring (for AI points)
    # ------------------------------------------------------------------

    def score_single_employee(self, employee_id: int) -> dict:
        """
        Score a single employee based on their task/step completion data.
        Returns points awarded and reasoning.
        """
        rows = fetch_all(
            """
            SELECT e.id, e.full_name, e.performance_score AS db_score,
                   COUNT(t.id) AS total_tasks,
                   SUM(t.status = 'completed') AS completed,
                   SUM(t.status = 'overdue') AS overdue
              FROM employees e
              LEFT JOIN tasks t ON t.assigned_to = e.id
             WHERE e.id = %s
             GROUP BY e.id, e.full_name, e.performance_score
            """,
            (employee_id,),
        )

        if not rows:
            return {
                "employee_id": employee_id,
                "points_awarded": 0,
                "reason": "Employee not found or no task data",
                "insights": [],
            }

        r = rows[0]
        total = int(r.get("total_tasks") or 0)
        completed = int(r.get("completed") or 0)
        overdue = int(r.get("overdue") or 0)

        # Compute points
        base_points = completed * 5
        speed_bonus = max(0, (completed - overdue)) * 2
        total_points = base_points + speed_bonus

        insights = []
        if total > 0:
            rate = completed / total * 100
            if rate >= 80:
                insights.append(f"High completion rate: {rate:.0f}%")
            if overdue == 0 and completed > 0:
                insights.append("Perfect on-time record")
            if overdue > total * 0.3:
                insights.append("Too many overdue tasks – consider lighter workload")

        reason = "Task completion scoring"
        if speed_bonus > 0:
            reason = f"Fast completion of {completed} tasks with {overdue} overdue"

        return {
            "employee_id": employee_id,
            "full_name": r.get("full_name", ""),
            "points_awarded": total_points,
            "reason": reason,
            "total_tasks": total,
            "completed": completed,
            "overdue": overdue,
            "insights": insights,
        }

    # ------------------------------------------------------------------
    # Bottleneck analysis
    # ------------------------------------------------------------------

    def identify_bottlenecks(self) -> dict:
        """
        Identify operational bottlenecks from task data.
        Returns a dict with lists of bottleneck findings and recommendations.
        """
        task_rows = fetch_all(
            """
            SELECT task_type, priority, status,
                   COALESCE(DATEDIFF(NOW(), deadline), 0) AS days_overdue
              FROM tasks
             WHERE deadline IS NOT NULL
            """
        )

        # Aggregate
        type_stats: dict = defaultdict(lambda: {"total": 0, "overdue": 0, "in_progress": 0})
        for r in task_rows:
            tt = r.get("task_type", "unknown")
            type_stats[tt]["total"] += 1
            if r.get("status") == "overdue":
                type_stats[tt]["overdue"] += 1
            if r.get("status") == "in_progress":
                type_stats[tt]["in_progress"] += 1

        bottlenecks = []
        for tt, stats in type_stats.items():
            total = stats["total"]
            if total == 0:
                continue
            delay_rate = stats["overdue"] / total * 100
            if delay_rate > 30:
                bottlenecks.append({
                    "type":        tt,
                    "total_tasks": total,
                    "overdue":     stats["overdue"],
                    "delay_rate":  round(delay_rate, 1),
                    "severity":    "critical" if delay_rate > 60 else "warning",
                })

        bottlenecks.sort(key=lambda x: x["delay_rate"], reverse=True)

        recommendations = self._bottleneck_recommendations(bottlenecks)

        return {
            "bottlenecks":       bottlenecks,
            "task_type_summary": [
                {
                    "task_type":  tt,
                    "total":      s["total"],
                    "overdue":    s["overdue"],
                    "in_progress":s["in_progress"],
                    "delay_rate": round(s["overdue"] / s["total"] * 100, 1) if s["total"] else 0,
                }
                for tt, s in type_stats.items()
            ],
            "recommendations": recommendations,
        }

    # ------------------------------------------------------------------
    # Insights
    # ------------------------------------------------------------------

    def get_performance_insights(self) -> dict:
        scores  = self.get_employee_scores()
        bottles = self.identify_bottlenecks()

        if not scores:
            return {
                "insights":   ["No task data available yet. Assign tasks to employees to generate insights."],
                "top_performers": [],
                "needs_attention": [],
            }

        top        = [s for s in scores if s["composite_score"] >= 85][:3]
        attention  = [s for s in scores if s["composite_score"] < 70]

        insights = []
        if top:
            insights.append(
                f"Top performer: {top[0]['full_name']} with a composite score of {top[0]['composite_score']}."
            )
        if attention:
            insights.append(
                f"{len(attention)} employee(s) need performance review (score < 70)."
            )
        if bottles["bottlenecks"]:
            worst = bottles["bottlenecks"][0]
            insights.append(
                f"Highest bottleneck: {worst['type'].replace('_', ' ').title()} "
                f"with {worst['delay_rate']}% delay rate."
            )

        return {
            "insights":        insights,
            "top_performers":  top,
            "needs_attention": attention,
            "bottlenecks":     bottles["bottlenecks"],
        }

    # ------------------------------------------------------------------

    @staticmethod
    def _recommend(score: float, overdue: int, total: int) -> str:
        if score >= 90:
            return "Excellent performance – consider for senior roles."
        if score >= 75:
            return "Good performance – maintain current workload."
        if score >= 60:
            return "Adequate – provide additional training or support."
        return "Needs improvement – schedule performance review."

    @staticmethod
    def _bottleneck_recommendations(bottlenecks: list) -> list[str]:
        recs = []
        for b in bottlenecks:
            tt = b["type"].replace("_", " ").title()
            if b["severity"] == "critical":
                recs.append(
                    f"CRITICAL: {tt} tasks have a {b['delay_rate']}% delay rate. "
                    "Reallocate resources immediately."
                )
            else:
                recs.append(
                    f"WARNING: {tt} tasks show elevated delays ({b['delay_rate']}%). "
                    "Review process and staffing."
                )
        if not recs:
            recs.append("No significant bottlenecks detected. Operations are running smoothly.")
        return recs
