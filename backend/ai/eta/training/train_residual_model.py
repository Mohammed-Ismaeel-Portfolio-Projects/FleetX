import os
import sys
import json
import joblib
import pandas as pd
import numpy as np
from datetime import datetime
from typing import Dict, Any, List

# Add parent directory to path to allow importing data_loader
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
from data_loader import EtaDataLoader

from sklearn.ensemble import HistGradientBoostingRegressor
from sklearn.preprocessing import OrdinalEncoder
from sklearn.metrics import mean_absolute_error, root_mean_squared_error, median_absolute_error, r2_score


def main():
    print("=" * 70)
    print("FLeetX AI Phase 3 — ML ETA Residual Model Training & Offline Evaluation")
    print("=" * 70)

    # 1. Load Dataset
    df = EtaDataLoader.load_or_generate_dataset()
    print(f"Total Observation Dataset Samples: {len(df)}")

    # 2. Validate Schema & Ensure Zero Data Leakage
    feature_config = EtaDataLoader.FEATURE_CONFIG
    feature_cols = EtaDataLoader.get_all_feature_cols()
    leakage_cols = feature_config["leakage_cols"]

    print("\n[Leakage Audit]")
    for col in feature_cols:
        if col in leakage_cols:
            raise ValueError(f"CRITICAL DATA LEAKAGE: Feature '{col}' is in forbidden leakage list!")
    print("  SUCCESS: 0 leakage features found in feature matrix X.")

    # 3. Shipment-Level Data Splitting
    train_df, val_df, test_df = EtaDataLoader.shipment_level_split(df, train_ratio=0.70, val_ratio=0.15, test_ratio=0.15, random_seed=42)

    # Verify zero shipment overlap between train and test
    train_shipments = set(train_df["shipment_id"])
    test_shipments = set(test_df["shipment_id"])
    overlap = train_shipments.intersection(test_shipments)
    if overlap:
        raise ValueError(f"CRITICAL SPLIT FAILURE: {len(overlap)} shipments overlap between train and test sets!")
    print(f"  SUCCESS: 0 shipment overlap between splits. Test set completely isolated ({len(test_shipments)} shipments).")

    # 4. Feature Encoding & Preprocessing
    categorical_cols = feature_config["categorical_features"]
    encoder = OrdinalEncoder(handle_unknown="use_encoded_value", unknown_value=-1)

    X_train = train_df[feature_cols].copy()
    y_train = train_df[feature_config["target_col"]].values

    X_val = val_df[feature_cols].copy()
    y_val = val_df[feature_config["target_col"]].values

    X_test = test_df[feature_cols].copy()
    y_test = test_df[feature_config["target_col"]].values

    X_train[categorical_cols] = encoder.fit_transform(X_train[categorical_cols].astype(str))
    X_val[categorical_cols] = encoder.transform(X_val[categorical_cols].astype(str))
    X_test[categorical_cols] = encoder.transform(X_test[categorical_cols].astype(str))

    # Convert binary boolean columns to 0/1 integers
    for b_col in feature_config["binary_features"]:
        X_train[b_col] = X_train[b_col].astype(int)
        X_val[b_col] = X_val[b_col].astype(int)
        X_test[b_col] = X_test[b_col].astype(int)

    # 5. Train Residual Model
    print("\n[Model Training]")
    model = HistGradientBoostingRegressor(
        loss="squared_error",
        learning_rate=0.05,
        max_iter=150,
        max_leaf_nodes=31,
        min_samples_leaf=20,
        random_state=42
    )

    model.fit(X_train, y_train)
    val_score = model.score(X_val, y_val)
    print(f"  HistGradientBoostingRegressor trained successfully.")
    print(f"  Validation R^2 (Residual): {val_score:.4f}")

    # 6. Baseline vs AI Offline Evaluation on Test Set
    print("\n[Offline Evaluation on Test Set]")

    actual_remaining_test = test_df[feature_config["ground_truth_col"]].values
    baseline_remaining_test = test_df[feature_config["baseline_col"]].values

    # ML Predicted residual delta_hat
    predicted_residuals = model.predict(X_test)

    # AI Prediction: Baseline + predicted_residual (clamped to >= 0)
    ai_predictions = np.maximum(0.0, baseline_remaining_test + predicted_residuals)

    # Metrics Baseline
    mae_baseline = mean_absolute_error(actual_remaining_test, baseline_remaining_test)
    rmse_baseline = root_mean_squared_error(actual_remaining_test, baseline_remaining_test)

    # Metrics AI
    mae_ai = mean_absolute_error(actual_remaining_test, ai_predictions)
    rmse_ai = root_mean_squared_error(actual_remaining_test, ai_predictions)

    # Improvements
    mae_imp = mae_baseline - mae_ai
    mae_imp_pct = (mae_imp / mae_baseline) * 100.0 if mae_baseline > 0 else 0.0

    rmse_imp = rmse_baseline - rmse_ai
    rmse_imp_pct = (rmse_imp / rmse_baseline) * 100.0 if rmse_baseline > 0 else 0.0

    med_ae_baseline = float(median_absolute_error(actual_remaining_test, baseline_remaining_test))
    med_ae_ai = float(median_absolute_error(actual_remaining_test, ai_predictions))

    errors_baseline = np.abs(actual_remaining_test - baseline_remaining_test)
    errors_ai = np.abs(actual_remaining_test - ai_predictions)

    p90_baseline = float(np.percentile(errors_baseline, 90))
    p90_ai = float(np.percentile(errors_ai, 90))

    p95_baseline = float(np.percentile(errors_baseline, 95))
    p95_ai = float(np.percentile(errors_ai, 95))

    residual_r2 = float(r2_score(y_test, predicted_residuals))

    print(f"  Baseline MAE:  {mae_baseline:.2f} seconds ({mae_baseline/60.0:.2f} mins)")
    print(f"  AI MAE:        {mae_ai:.2f} seconds ({mae_ai/60.0:.2f} mins)")
    print(f"  MAE Improvement: {mae_imp:.2f} s ({mae_imp_pct:+.2f}%)")
    print(f"  Baseline RMSE: {rmse_baseline:.2f} s | AI RMSE: {rmse_ai:.2f} s ({rmse_imp_pct:+.2f}%)")
    print(f"  Residual R^2:  {residual_r2:.4f}")

    # 7. Journey Progress Error Analysis (Buckets)
    print("\n[Error Analysis by Journey Progress]")
    test_df["completion_pct"] = test_df["completion_percentage"]
    buckets = [
        ("0-20%", 0.0, 20.0),
        ("20-40%", 20.0, 40.0),
        ("40-60%", 40.0, 60.0),
        ("60-80%", 60.0, 80.0),
        ("80-100%", 80.0, 100.1),
    ]

    progress_analysis = {}

    for b_label, b_min, b_max in buckets:
        mask = (test_df["completion_pct"] >= b_min) & (test_df["completion_pct"] < b_max)
        sub_count = int(mask.sum())
        if sub_count > 0:
            sub_y = actual_remaining_test[mask]
            sub_base = baseline_remaining_test[mask]
            sub_ai = ai_predictions[mask]

            b_mae_base = float(mean_absolute_error(sub_y, sub_base))
            b_mae_ai = float(mean_absolute_error(sub_y, sub_ai))
            b_imp_pct = float(((b_mae_base - b_mae_ai) / b_mae_base) * 100.0) if b_mae_base > 0 else 0.0

            progress_analysis[b_label] = {
                "sample_count": sub_count,
                "baseline_mae_seconds": round(b_mae_base, 2),
                "ai_mae_seconds": round(b_mae_ai, 2),
                "mae_improvement_pct": round(b_imp_pct, 2)
            }
            print(f"  Bucket {b_label:7s} (n={sub_count:3d}): Baseline MAE = {b_mae_base:6.1f}s | AI MAE = {b_mae_ai:6.1f}s ({b_imp_pct:+6.2f}%)")

    # 8. Save Output Artifacts
    base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
    models_dir = os.path.join(base_dir, "models")
    eval_dir = os.path.join(base_dir, "evaluation")

    os.makedirs(models_dir, exist_ok=True)
    os.makedirs(eval_dir, exist_ok=True)

    # Save Model Artifacts
    model_payload = {
        "model": model,
        "encoder": encoder,
        "feature_cols": feature_cols,
        "categorical_cols": categorical_cols,
        "binary_cols": feature_config["binary_features"]
    }
    model_path = os.path.join(models_dir, "eta_residual_model.joblib")
    joblib.dump(model_payload, model_path)
    print(f"\nSaved model artifact to: {model_path}")

    # Feature Manifest
    manifest = {
        "feature_count": len(feature_cols),
        "feature_names": feature_cols,
        "numeric_features": feature_config["numeric_features"],
        "categorical_features": feature_config["categorical_features"],
        "binary_features": feature_config["binary_features"],
        "target_col": feature_config["target_col"],
        "excluded_leakage_cols": leakage_cols
    }
    manifest_path = os.path.join(models_dir, "feature_manifest.json")
    with open(manifest_path, "w") as f:
        json.dump(manifest, f, indent=2)

    # Model Metadata
    metadata = {
        "model_type": "HistGradientBoostingRegressor",
        "library_versions": {
            "sklearn": sys.modules["sklearn"].__version__,
            "pandas": sys.modules["pandas"].__version__,
            "numpy": sys.modules["numpy"].__version__
        },
        "hyperparameters": {
            "loss": "squared_error",
            "learning_rate": 0.05,
            "max_iter": 150,
            "max_leaf_nodes": 31,
            "min_samples_leaf": 20,
            "random_state": 42
        },
        "dataset_statistics": {
            "total_samples": len(df),
            "train_samples": len(train_df),
            "val_samples": len(val_df),
            "test_samples": len(test_df),
            "test_shipment_count": len(test_shipments)
        },
        "trained_at": datetime.utcnow().isoformat() + "Z"
    }
    metadata_path = os.path.join(models_dir, "model_metadata.json")
    with open(metadata_path, "w") as f:
        json.dump(metadata, f, indent=2)

    # Evaluation Metrics JSON
    metrics = {
        "baseline_mae_seconds": round(float(mae_baseline), 2),
        "baseline_rmse_seconds": round(float(rmse_baseline), 2),
        "baseline_medae_seconds": round(float(med_ae_baseline), 2),
        "baseline_p90_seconds": round(float(p90_baseline), 2),
        "baseline_p95_seconds": round(float(p95_baseline), 2),
        "ai_mae_seconds": round(float(mae_ai), 2),
        "ai_rmse_seconds": round(float(rmse_ai), 2),
        "ai_medae_seconds": round(float(med_ae_ai), 2),
        "ai_p90_seconds": round(float(p90_ai), 2),
        "ai_p95_seconds": round(float(p95_ai), 2),
        "mae_improvement_seconds": round(float(mae_imp), 2),
        "mae_improvement_percentage": round(float(mae_imp_pct), 2),
        "rmse_improvement_percentage": round(float(rmse_imp_pct), 2),
        "residual_r2": round(float(residual_r2), 4)
    }
    metrics_path = os.path.join(eval_dir, "baseline_vs_ai_metrics.json")
    with open(metrics_path, "w") as f:
        json.dump(metrics, f, indent=2)

    # Progress Bucket Analysis JSON
    bucket_path = os.path.join(eval_dir, "progress_bucket_analysis.json")
    with open(bucket_path, "w") as f:
        json.dump(progress_analysis, f, indent=2)

    # Detailed Evaluation CSV
    report_df = pd.DataFrame({
        "shipment_id": test_df["shipment_id"].values,
        "observation_time": test_df["observation_time"].values,
        "completion_percentage": test_df["completion_percentage"].values,
        "actual_remaining_seconds": actual_remaining_test,
        "baseline_remaining_seconds": baseline_remaining_test,
        "predicted_residual_seconds": predicted_residuals,
        "ai_remaining_seconds": ai_predictions,
        "baseline_error_seconds": errors_baseline,
        "ai_error_seconds": errors_ai,
    })
    csv_path = os.path.join(eval_dir, "evaluation_report.csv")
    report_df.to_csv(csv_path, index=False)
    print(f"Saved evaluation report CSV to: {csv_path}")

    print("\nPhase 3 Training & Evaluation Pipeline execution completed successfully.")
    print("=" * 70)


if __name__ == "__main__":
    main()
