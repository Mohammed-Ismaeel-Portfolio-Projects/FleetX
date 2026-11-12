import os
import sys
import json
import joblib
import pandas as pd
import numpy as np

def main():
    try:
        # Load feature manifest to ensure exact feature contract
        base_dir = os.path.abspath(os.path.join(os.path.dirname(__file__)))
        models_dir = os.path.join(base_dir, "models")
        
        manifest_path = os.path.join(models_dir, "feature_manifest.json")
        model_path = os.path.join(models_dir, "eta_residual_model.joblib")

        if not os.path.exists(manifest_path) or not os.path.exists(model_path):
            result = {
                "status": "error",
                "message": "Model or feature manifest artifact not found.",
                "predicted_residual_seconds": 0.0
            }
            print(json.dumps(result))
            sys.exit(1)

        with open(manifest_path, "r") as f:
            manifest = json.load(f)

        feature_cols = manifest["feature_names"]
        categorical_cols = manifest["categorical_features"]
        binary_cols = manifest["binary_features"]

        # Read feature payload from argument, file, or stdin
        input_json = None
        if len(sys.argv) > 1:
            arg = sys.argv[1]
            if os.path.exists(arg):
                with open(arg, "r") as f:
                    input_json = f.read()
            else:
                input_json = arg

        if not input_json or not input_json.strip():
            input_json = sys.stdin.read()

        if not input_json or not input_json.strip():
            result = {
                "status": "error",
                "message": "Empty feature input payload.",
                "predicted_residual_seconds": 0.0
            }
            print(json.dumps(result))
            sys.exit(1)

        input_data = json.loads(input_json)

        # Build single-row DataFrame matching manifest feature order
        row_dict = {}
        for col in feature_cols:
            val = input_data.get(col, None)
            if val is None:
                # Default safe fallbacks
                if col in categorical_cols:
                    val = "UNKNOWN"
                elif col in binary_cols:
                    val = 0
                else:
                    val = 0.0
            row_dict[col] = [val]

        df = pd.DataFrame(row_dict)[feature_cols]

        # Load trained artifact
        artifact = joblib.load(model_path)
        model = artifact["model"]
        encoder = artifact["encoder"]

        # Preprocessing
        df[categorical_cols] = encoder.transform(df[categorical_cols].astype(str))
        for b_col in binary_cols:
            df[b_col] = df[b_col].astype(int)

        # Inference
        predicted_residual = float(model.predict(df)[0])

        if np.isnan(predicted_residual) or np.isinf(predicted_residual):
            result = {
                "status": "error",
                "message": "Model produced non-finite prediction (NaN/Inf).",
                "predicted_residual_seconds": 0.0
            }
            print(json.dumps(result))
            sys.exit(1)

        result = {
            "status": "success",
            "predicted_residual_seconds": round(predicted_residual, 2),
            "model_type": "HistGradientBoostingRegressor",
            "model_version": "v1.0"
        }
        print(json.dumps(result))

    except Exception as e:
        result = {
            "status": "error",
            "message": str(e),
            "predicted_residual_seconds": 0.0
        }
        print(json.dumps(result))
        sys.exit(1)

if __name__ == "__main__":
    main()
