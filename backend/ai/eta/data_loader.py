import os
import json
import sqlite3
import pandas as pd
import numpy as np
from typing import Dict, Any, Tuple, List, Optional

class EtaDataLoader:
    """
    Data Loader for ETA Observation Dataset Samples.
    Supports querying PostgreSQL/SQLite or loading from a synthetic/exported dataset fixture.
    """

    FEATURE_CONFIG = {
        "numeric_features": [
            "base_eta_seconds",
            "base_distance_meters",
            "remaining_distance_meters",
            "total_distance_meters",
            "distance_travelled_meters",
            "completion_percentage",
            "current_latitude",
            "current_longitude",
            "current_speed_kmh",
            "heading",
            "gps_accuracy",
            "driver_years_experience",
            "driver_score",
            "vehicle_gross_weight_kg",
            "cargo_weight",
            "hour_of_day",
            "day_of_week",
        ],
        "categorical_features": [
            "movement_status",
            "cargo_type",
            "baseline_routing_provider",
        ],
        "binary_features": [
            "temperature_controlled",
            "hazardous_cargo",
        ],
        "target_col": "residual_seconds",
        "ground_truth_col": "actual_remaining_seconds",
        "baseline_col": "baseline_remaining_seconds",
        "group_col": "shipment_id",
        "leakage_cols": [
            "id",
            "actual_delivery_at",
            "ended_at",
            "actual_remaining_seconds",
            "residual_seconds",
            "actual_total_duration_seconds",
            "shipment_id",
            "assignment_id",
            "tracking_log_id",
            "observation_time",
            "created_at",
            "updated_at",
        ],
    }

    @classmethod
    def get_all_feature_cols(cls) -> List[str]:
        return (
            cls.FEATURE_CONFIG["numeric_features"]
            + cls.FEATURE_CONFIG["categorical_features"]
            + cls.FEATURE_CONFIG["binary_features"]
        )

    @classmethod
    def load_from_db(cls, db_connection_str: Optional[str] = None) -> pd.DataFrame:
        """
        Loads observations directly from DB via psycopg2 or sqlite3.
        """
        import psycopg2

        host = os.getenv("DB_HOST", "127.0.0.1")
        port = os.getenv("DB_PORT", "5432")
        database = os.getenv("DB_DATABASE", "fleetx_db")
        user = os.getenv("DB_USERNAME", "fleetx_user")
        password = os.getenv("DB_PASSWORD", "FleetX@2026")

        conn = psycopg2.connect(
            host=host,
            port=port,
            dbname=database,
            user=user,
            password=password
        )

        query = "SELECT * FROM eta_observation_dataset_samples ORDER BY observation_time ASC"
        df = pd.read_sql(query, conn)
        conn.close()

        return df

    @classmethod
    def load_or_generate_dataset(cls, db_fallback_synthetic: bool = True) -> pd.DataFrame:
        """
        Tries DB connection; if DB is empty or unavailable during offline testing,
        generates realistic, deterministically seeded simulation observations.
        """
        try:
            df = cls.load_from_db()
            if not df.empty and len(df) >= 20:
                print(f"Loaded {len(df)} samples from database table 'eta_observation_dataset_samples'.")
                return df
        except Exception as e:
            print(f"DB load query notice: {e}")

        if db_fallback_synthetic:
            print("Generating realistic benchmark observations dataset for offline Phase 3 training...")
            return cls.generate_synthetic_observations(n_shipments=100, samples_per_shipment=15)

        raise RuntimeError("No dataset samples found in database and synthetic fallback disabled.")

    @classmethod
    def generate_synthetic_observations(
        cls,
        n_shipments: int = 100,
        samples_per_shipment: int = 15,
        random_seed: int = 42
    ) -> pd.DataFrame:
        """
        Generates realistic, leak-free historical ETA observations for offline benchmark training.
        Includes traffic delay dynamics, weather, driver behavior variations, and vehicle load effects.
        """
        np.random.seed(random_seed)
        records = []

        providers = ["graphhopper", "osrm", "simulator"]
        cargo_types = ["General Freight", "Refrigerated Food", "Electronics", "Pharmaceuticals", "Heavy Machinery"]
        movement_statuses = ["MOVING", "MOVING", "MOVING", "SLOW", "STOPPED"]

        for s_idx in range(1, n_shipments + 1):
            shipment_id = f"shp-synth-{s_idx:04d}"
            assignment_id = 1000 + s_idx
            driver_id = (s_idx % 25) + 1
            vehicle_id = f"vh-synth-{(s_idx % 20) + 1:03d}"

            # Static shipment attributes
            base_distance_meters = float(np.random.randint(50000, 450000)) # 50km to 450km
            base_eta_seconds = float(base_distance_meters / (80.0 / 3.6)) # cruise speed 80 km/h baseline
            provider = np.random.choice(providers)
            cargo = np.random.choice(cargo_types)
            is_temp = cargo in ["Refrigerated Food", "Pharmaceuticals"]
            is_haz = cargo in ["Heavy Machinery"]
            cargo_w = float(np.random.uniform(2000, 25000))
            veh_w = float(cargo_w + np.random.uniform(8000, 15000))
            driver_exp = int(np.random.randint(1, 25))
            driver_sc = float(np.random.uniform(70.0, 99.5))

            # Ground truth factor: traffic congestion + driver aggression bias
            # Positive residual = delayed trip, Negative residual = faster trip
            traffic_bias = np.random.choice([0.0, 300.0, 900.0, 1800.0], p=[0.4, 0.3, 0.2, 0.1])
            driver_bias = -180.0 if driver_sc > 90 else (360.0 if driver_sc < 75 else 0.0)
            weather_bias = np.random.choice([0.0, 600.0], p=[0.8, 0.2])
            trip_residual_total = traffic_bias + driver_bias + weather_bias + np.random.normal(0, 120)

            actual_total_duration = max(base_eta_seconds + 300, base_eta_seconds + trip_residual_total)

            start_timestamp = 1772438400 + (s_idx * 3600) # Base time epoch
            delivery_timestamp = start_timestamp + actual_total_duration

            # Generate chronological observations along the trip
            sample_times = np.sort(np.random.uniform(start_timestamp, delivery_timestamp - 60, samples_per_shipment))

            for t_idx, t_obs in enumerate(sample_times):
                actual_remaining_sec = float(delivery_timestamp - t_obs)
                obs_fraction = (t_obs - start_timestamp) / actual_total_duration
                obs_fraction = float(np.clip(obs_fraction, 0.0, 0.99))

                # Route progress at observation time
                dist_travelled = float(base_distance_meters * obs_fraction)
                rem_dist = float(max(0.0, base_distance_meters - dist_travelled))
                completion_pct = float(round(obs_fraction * 100.0, 2))
                segment_idx = int(obs_fraction * 10)
                polyline_idx = int(obs_fraction * 50)

                # Dynamic telemetry
                speed_kmh = float(np.clip(np.random.normal(75 if obs_fraction < 0.8 else 35, 15), 0.0, 120.0))
                heading = float(np.random.uniform(0.0, 360.0))
                accuracy = float(np.random.uniform(2.0, 15.0))
                mov_status = "STOPPED" if speed_kmh < 1.0 else ("SLOW" if speed_kmh < 15.0 else "MOVING")

                # Baseline remaining estimate: (rem_dist / 1000) / 80.0 * 3600
                baseline_rem_sec = float((rem_dist / 1000.0) / 80.0 * 3600.0)

                # Target residual for this observation
                residual_sec = float(actual_remaining_sec - baseline_rem_sec)

                # Temporal features
                dt_obs = pd.to_datetime(t_obs, unit="s")
                hour = int(dt_obs.hour)
                day_of_week = int(dt_obs.dayofweek + 1)

                records.append({
                    "shipment_id": shipment_id,
                    "assignment_id": assignment_id,
                    "tracking_log_id": s_idx * 100 + t_idx,
                    "observation_time": dt_obs,
                    "base_eta_seconds": base_eta_seconds,
                    "base_distance_meters": base_distance_meters,
                    "baseline_routing_provider": provider,
                    "remaining_distance_meters": rem_dist,
                    "total_distance_meters": base_distance_meters,
                    "distance_travelled_meters": dist_travelled,
                    "completion_percentage": completion_pct,
                    "segment_index": segment_idx,
                    "polyline_index": polyline_idx,
                    "current_latitude": 33.5 + (0.01 * t_idx),
                    "current_longitude": 36.2 + (0.01 * t_idx),
                    "current_speed_kmh": speed_kmh,
                    "heading": heading,
                    "gps_accuracy": accuracy,
                    "movement_status": mov_status,
                    "driver_id": driver_id,
                    "driver_years_experience": driver_exp,
                    "driver_score": driver_sc,
                    "vehicle_id": vehicle_id,
                    "vehicle_gross_weight_kg": veh_w,
                    "cargo_weight": cargo_w,
                    "cargo_type": cargo,
                    "temperature_controlled": is_temp,
                    "hazardous_cargo": is_haz,
                    "hour_of_day": hour,
                    "day_of_week": day_of_week,
                    "actual_total_duration_seconds": actual_total_duration,
                    "actual_remaining_seconds": actual_remaining_sec,
                    "baseline_remaining_seconds": baseline_rem_sec,
                    "residual_seconds": residual_sec,
                })

        return pd.DataFrame(records)

    @classmethod
    def shipment_level_split(
        cls,
        df: pd.DataFrame,
        train_ratio: float = 0.70,
        val_ratio: float = 0.15,
        test_ratio: float = 0.15,
        random_seed: int = 42
    ) -> Tuple[pd.DataFrame, pd.DataFrame, pd.DataFrame]:
        """
        Splits dataset by shipment_id guarantees ZERO observation overlap between splits.
        """
        unique_shipments = df[cls.FEATURE_CONFIG["group_col"]].unique()
        np.random.seed(random_seed)
        np.random.shuffle(unique_shipments)

        n_total = len(unique_shipments)
        n_train = int(round(n_total * train_ratio))
        n_val = int(round(n_total * val_ratio))

        train_shipments = set(unique_shipments[:n_train])
        val_shipments = set(unique_shipments[n_train:n_train + n_val])
        test_shipments = set(unique_shipments[n_train + n_val:])

        train_df = df[df[cls.FEATURE_CONFIG["group_col"]].isin(train_shipments)].copy()
        val_df = df[df[cls.FEATURE_CONFIG["group_col"]].isin(val_shipments)].copy()
        test_df = df[df[cls.FEATURE_CONFIG["group_col"]].isin(test_shipments)].copy()

        print(f"Shipment-Level Split complete:")
        print(f"  Train: {len(train_shipments)} shipments ({len(train_df)} samples)")
        print(f"  Val:   {len(val_shipments)} shipments ({len(val_df)} samples)")
        print(f"  Test:  {len(test_shipments)} shipments ({len(test_df)} samples)")

        return train_df, val_df, test_df
