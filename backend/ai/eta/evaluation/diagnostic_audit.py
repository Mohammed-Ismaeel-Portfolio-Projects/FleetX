import os
import sys
import json
import pandas as pd
import numpy as np
from sklearn.metrics import mean_absolute_error, root_mean_squared_error, median_absolute_error, r2_score

def run_detailed_diagnostic():
    base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
    csv_report_path = os.path.join(base_dir, "evaluation", "evaluation_report.csv")
    df_csv = pd.read_csv(csv_report_path)

    actual = df_csv["actual_remaining_seconds"].values
    base = df_csv["baseline_remaining_seconds"].values
    pred_res = df_csv["predicted_residual_seconds"].values
    ai_rem = df_csv["ai_remaining_seconds"].values
    target_res = actual - base

    print("=== DETAILED METRIC FORMULA DIAGNOSTICS ===")

    # 1. Total Remaining Time Error (AI_Remaining - Actual_Remaining)
    err_base_total = np.abs(actual - base)
    err_ai_total = np.abs(actual - ai_rem)

    # 2. Residual Error (Predicted_Residual - Target_Residual)
    err_res = np.abs(target_res - pred_res)

    print("\n[A] Total Remaining Time Error Definition (ai_remaining - actual_remaining):")
    print(f"  MAE Baseline: {np.mean(err_base_total):.2f}s")
    print(f"  MAE AI:       {np.mean(err_ai_total):.2f}s")
    print(f"  MedAE Baseline: {np.median(err_base_total):.2f}s")
    print(f"  MedAE AI:       {np.median(err_ai_total):.2f}s")
    print(f"  P90 Baseline:   {np.percentile(err_base_total, 90):.2f}s")
    print(f"  P90 AI:         {np.percentile(err_ai_total, 90):.2f}s")
    print(f"  P95 Baseline:   {np.percentile(err_base_total, 95):.2f}s")
    print(f"  P95 AI:         {np.percentile(err_ai_total, 95):.2f}s")

    print("\n[B] Residual Target Error Definition (predicted_residual - target_residual):")
    print(f"  MAE Residual Error:   {np.mean(err_res):.2f}s")
    print(f"  MedAE Residual Error: {np.median(err_res):.2f}s")
    print(f"  P90 Residual Error:   {np.percentile(err_res, 90):.2f}s")
    print(f"  P95 Residual Error:   {np.percentile(err_res, 95):.2f}s")

    print("\n[C] Percentile Interpolation Methods on Total Remaining Time Error:")
    for method in ['linear', 'lower', 'higher', 'midpoint', 'nearest']:
        p90_b = np.percentile(err_base_total, 90, method=method)
        p90_a = np.percentile(err_ai_total, 90, method=method)
        p95_b = np.percentile(err_base_total, 95, method=method)
        p95_a = np.percentile(err_ai_total, 95, method=method)
        print(f"  Method {method:8s} -> P90 Base: {p90_b:.2f}s, P90 AI: {p90_a:.2f}s | P95 Base: {p95_b:.2f}s, P95 AI: {p95_a:.2f}s")

    print("\n[D] R2 Metrics Breakdown:")
    print(f"  1. Residual Regressor R2 (target_residual vs predicted_residual): {r2_score(target_res, pred_res):.4f}")
    print(f"  2. Total Remaining Time R2 (actual_remaining vs ai_remaining):        {r2_score(actual, ai_rem):.4f}")
    print(f"  3. Total Remaining Time R2 (actual_remaining vs baseline_remaining):  {r2_score(actual, base):.4f}")

if __name__ == "__main__":
    run_detailed_diagnostic()
