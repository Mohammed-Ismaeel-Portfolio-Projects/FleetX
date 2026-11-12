<?php

/**
 * European Fleet Locations
 *
 * Central source of truth for FleetX EU logistics hubs.
 * All simulation positions and shipment city geocoding must use this config.
 * Do NOT generate random global latitude/longitude.
 */

return [

    'country_weights' => [
        'Germany' => 20,
        'France' => 15,
        'Netherlands' => 10,
        'Italy' => 10,
        'Spain' => 10,
        'Poland' => 10,
        'Belgium' => 5,
        'Austria' => 5,
        'Czech Republic' => 5,
        'Sweden' => 3,
        'Denmark' => 2,
        'Hungary' => 2,
        'Portugal' => 2,
        'Romania' => 1,
    ],

    'eu_bounds' => [
        'min_lat' => 36.0,
        'max_lat' => 70.0,
        'min_lng' => -10.5,
        'max_lng' => 30.0,
    ],

    /*
    | Approximate open-water boxes to reject (coarse filter).
    | Primary land guarantee is proximity to logistics hubs.
    */
    'water_exclusion_zones' => [
        ['name' => 'atlantic_west', 'min_lat' => 36.0, 'max_lat' => 52.0, 'min_lng' => -20.0, 'max_lng' => -10.0],
        ['name' => 'mediterranean_central', 'min_lat' => 36.5, 'max_lat' => 41.2, 'min_lng' => 4.0, 'max_lng' => 15.5],
        ['name' => 'north_sea', 'min_lat' => 54.0, 'max_lat' => 58.0, 'min_lng' => 1.5, 'max_lng' => 6.5],
        ['name' => 'baltic_open', 'min_lat' => 55.0, 'max_lat' => 59.5, 'min_lng' => 17.0, 'max_lng' => 21.5],
        ['name' => 'adriatic_open', 'min_lat' => 41.0, 'max_lat' => 44.0, 'min_lng' => 14.0, 'max_lng' => 17.5],
    ],

    'max_hub_distance_km' => 45.0,

    'locations' => [
        // Syria & Regional Logistics Hubs
        ['country' => 'Syria', 'city' => 'Damascus', 'latitude' => 33.5138, 'longitude' => 36.2765],
        ['country' => 'Syria', 'city' => 'Aleppo', 'latitude' => 36.2021, 'longitude' => 37.1343],
        ['country' => 'Syria', 'city' => 'Homs', 'latitude' => 34.7324, 'longitude' => 36.7137],
        ['country' => 'Syria', 'city' => 'Hama', 'latitude' => 35.1318, 'longitude' => 36.7578],
        ['country' => 'Syria', 'city' => 'Latakia', 'latitude' => 35.5317, 'longitude' => 35.7915],
        ['country' => 'Syria', 'city' => 'Tartus', 'latitude' => 34.8890, 'longitude' => 35.8866],
        ['country' => 'Syria', 'city' => 'Daraa', 'latitude' => 32.6256, 'longitude' => 36.1054],

        ['country' => 'Jordan', 'city' => 'Amman', 'latitude' => 31.9454, 'longitude' => 35.9284],
        ['country' => 'Lebanon', 'city' => 'Beirut', 'latitude' => 33.8938, 'longitude' => 35.5018],
        ['country' => 'Turkey', 'city' => 'Gaziantep', 'latitude' => 37.0662, 'longitude' => 37.3833],
        ['country' => 'Turkey', 'city' => 'Kilis', 'latitude' => 36.7161, 'longitude' => 37.1150],
        ['country' => 'Iraq', 'city' => 'Baghdad', 'latitude' => 33.3152, 'longitude' => 44.3661],
        ['country' => 'Iraq', 'city' => 'Erbil', 'latitude' => 36.1901, 'longitude' => 44.0091],

        ['country' => 'Germany', 'city' => 'Berlin', 'latitude' => 52.5200, 'longitude' => 13.4050],
        ['country' => 'Germany', 'city' => 'Hamburg', 'latitude' => 53.5511, 'longitude' => 9.9937],
        ['country' => 'Germany', 'city' => 'Munich', 'latitude' => 48.1351, 'longitude' => 11.5820],
        ['country' => 'Germany', 'city' => 'Frankfurt', 'latitude' => 50.1109, 'longitude' => 8.6821],
        ['country' => 'Germany', 'city' => 'Cologne', 'latitude' => 50.9375, 'longitude' => 6.9603],
        ['country' => 'Germany', 'city' => 'Stuttgart', 'latitude' => 48.7758, 'longitude' => 9.1829],
        ['country' => 'Germany', 'city' => 'Leipzig', 'latitude' => 51.3397, 'longitude' => 12.3731],
        ['country' => 'Germany', 'city' => 'Düsseldorf', 'latitude' => 51.2277, 'longitude' => 6.7735],

        ['country' => 'France', 'city' => 'Paris', 'latitude' => 48.8566, 'longitude' => 2.3522],
        ['country' => 'France', 'city' => 'Lyon', 'latitude' => 45.7640, 'longitude' => 4.8357],
        ['country' => 'France', 'city' => 'Marseille', 'latitude' => 43.2965, 'longitude' => 5.3698],
        ['country' => 'France', 'city' => 'Lille', 'latitude' => 50.6292, 'longitude' => 3.0573],
        ['country' => 'France', 'city' => 'Strasbourg', 'latitude' => 48.5734, 'longitude' => 7.7521],
        ['country' => 'France', 'city' => 'Bordeaux', 'latitude' => 44.8378, 'longitude' => -0.5792],
        // Coastal land anchor between Barcelona and Marseille (avoids Med chords in simulator fallback)
        ['country' => 'France', 'city' => 'Montpellier', 'latitude' => 43.6108, 'longitude' => 3.8767],

        ['country' => 'Netherlands', 'city' => 'Amsterdam', 'latitude' => 52.3676, 'longitude' => 4.9041],
        ['country' => 'Netherlands', 'city' => 'Rotterdam', 'latitude' => 51.9225, 'longitude' => 4.4792],
        ['country' => 'Netherlands', 'city' => 'Eindhoven', 'latitude' => 51.4416, 'longitude' => 5.4697],

        ['country' => 'Italy', 'city' => 'Milan', 'latitude' => 45.4642, 'longitude' => 9.1900],
        ['country' => 'Italy', 'city' => 'Rome', 'latitude' => 41.9028, 'longitude' => 12.4964],
        ['country' => 'Italy', 'city' => 'Turin', 'latitude' => 45.0703, 'longitude' => 7.6869],
        ['country' => 'Italy', 'city' => 'Bologna', 'latitude' => 44.4949, 'longitude' => 11.3426],
        ['country' => 'Italy', 'city' => 'Naples', 'latitude' => 40.8518, 'longitude' => 14.2681],
        ['country' => 'Italy', 'city' => 'Verona', 'latitude' => 45.4384, 'longitude' => 10.9916],

        ['country' => 'Spain', 'city' => 'Madrid', 'latitude' => 40.4168, 'longitude' => -3.7038],
        ['country' => 'Spain', 'city' => 'Barcelona', 'latitude' => 41.3851, 'longitude' => 2.1734],
        ['country' => 'Spain', 'city' => 'Valencia', 'latitude' => 39.4699, 'longitude' => -0.3763],
        ['country' => 'Spain', 'city' => 'Zaragoza', 'latitude' => 41.6488, 'longitude' => -0.8891],
        ['country' => 'Spain', 'city' => 'Seville', 'latitude' => 37.3891, 'longitude' => -5.9845],

        ['country' => 'Poland', 'city' => 'Warsaw', 'latitude' => 52.2297, 'longitude' => 21.0122],
        ['country' => 'Poland', 'city' => 'Krakow', 'latitude' => 50.0647, 'longitude' => 19.9450],
        ['country' => 'Poland', 'city' => 'Wroclaw', 'latitude' => 51.1079, 'longitude' => 17.0385],
        ['country' => 'Poland', 'city' => 'Poznan', 'latitude' => 52.4064, 'longitude' => 16.9252],
        ['country' => 'Poland', 'city' => 'Gdansk', 'latitude' => 54.3520, 'longitude' => 18.6466],

        ['country' => 'Belgium', 'city' => 'Brussels', 'latitude' => 50.8503, 'longitude' => 4.3517],
        ['country' => 'Belgium', 'city' => 'Antwerp', 'latitude' => 51.2194, 'longitude' => 4.4025],
        ['country' => 'Belgium', 'city' => 'Liege', 'latitude' => 50.6326, 'longitude' => 5.5797],

        ['country' => 'Austria', 'city' => 'Vienna', 'latitude' => 48.2082, 'longitude' => 16.3738],
        ['country' => 'Austria', 'city' => 'Linz', 'latitude' => 48.3069, 'longitude' => 14.2858],
        ['country' => 'Austria', 'city' => 'Salzburg', 'latitude' => 47.8095, 'longitude' => 13.0550],

        ['country' => 'Czech Republic', 'city' => 'Prague', 'latitude' => 50.0755, 'longitude' => 14.4378],
        ['country' => 'Czech Republic', 'city' => 'Brno', 'latitude' => 49.1951, 'longitude' => 16.6068],

        ['country' => 'Sweden', 'city' => 'Stockholm', 'latitude' => 59.3293, 'longitude' => 18.0686],
        ['country' => 'Sweden', 'city' => 'Malmo', 'latitude' => 55.6050, 'longitude' => 13.0038],
        ['country' => 'Denmark', 'city' => 'Copenhagen', 'latitude' => 55.6761, 'longitude' => 12.5683],
        ['country' => 'Hungary', 'city' => 'Budapest', 'latitude' => 47.4979, 'longitude' => 19.0402],
        ['country' => 'Portugal', 'city' => 'Lisbon', 'latitude' => 38.7223, 'longitude' => -9.1393],
        ['country' => 'Portugal', 'city' => 'Porto', 'latitude' => 41.1579, 'longitude' => -8.6291],
        ['country' => 'Romania', 'city' => 'Bucharest', 'latitude' => 44.4268, 'longitude' => 26.1025],
    ],

    'facility_offsets' => [
        ['lat' => 0.0000, 'lng' => 0.0000],
        ['lat' => 0.0280, 'lng' => -0.0220],
        ['lat' => -0.0240, 'lng' => 0.0310],
        ['lat' => 0.0150, 'lng' => 0.0180],
        ['lat' => -0.0180, 'lng' => -0.0150],
    ],

    /*
    | Land-only neighbor graph (city keys). Edges avoid open-sea shortcuts
    | (e.g. Barcelona↔Rome, Amsterdam↔Copenhagen over North Sea).
    */
    'land_neighbors' => [
        'damascus' => ['homs', 'daraa', 'beirut', 'amman', 'baghdad', 'aleppo'],
        'aleppo' => ['hama', 'homs', 'gaziantep', 'kilis', 'erbil', 'damascus'],
        'homs' => ['damascus', 'hama', 'tartus', 'beirut'],
        'hama' => ['homs', 'aleppo'],
        'latakia' => ['tartus', 'aleppo'],
        'tartus' => ['latakia', 'homs'],
        'daraa' => ['damascus', 'amman'],
        'amman' => ['daraa', 'damascus'],
        'beirut' => ['damascus', 'homs'],
        'gaziantep' => ['kilis', 'aleppo'],
        'kilis' => ['gaziantep', 'aleppo'],
        'baghdad' => ['damascus', 'erbil'],
        'erbil' => ['baghdad', 'aleppo'],
        'berlin' => ['hamburg', 'leipzig', 'prague', 'warsaw', 'frankfurt', 'poznan'],
        'hamburg' => ['berlin', 'cologne', 'amsterdam', 'copenhagen', 'leipzig'],
        'munich' => ['stuttgart', 'vienna', 'salzburg', 'verona', 'prague', 'frankfurt'],
        'frankfurt' => ['cologne', 'stuttgart', 'berlin', 'munich', 'strasbourg', 'amsterdam'],
        'cologne' => ['düsseldorf', 'frankfurt', 'brussels', 'amsterdam', 'liege', 'hamburg'],
        'stuttgart' => ['frankfurt', 'munich', 'strasbourg', 'lyon'],
        'leipzig' => ['berlin', 'prague', 'hamburg', 'wroclaw'],
        'düsseldorf' => ['cologne', 'amsterdam', 'antwerp'],
        'paris' => ['lille', 'lyon', 'strasbourg', 'brussels', 'bordeaux'],
        'lyon' => ['paris', 'marseille', 'turin', 'stuttgart', 'milan', 'strasbourg'],
        'marseille' => ['lyon', 'montpellier', 'turin'],
        'montpellier' => ['marseille', 'barcelona', 'lyon'],
        'lille' => ['paris', 'brussels', 'amsterdam'],
        'strasbourg' => ['frankfurt', 'stuttgart', 'paris', 'lyon'],
        'bordeaux' => ['paris', 'zaragoza', 'madrid'],
        'amsterdam' => ['rotterdam', 'eindhoven', 'brussels', 'cologne', 'hamburg', 'düsseldorf'],
        'rotterdam' => ['amsterdam', 'antwerp', 'eindhoven', 'brussels'],
        'eindhoven' => ['amsterdam', 'rotterdam', 'antwerp', 'cologne'],
        'milan' => ['turin', 'verona', 'bologna', 'lyon'],
        'rome' => ['naples', 'bologna'],
        'turin' => ['milan', 'lyon', 'marseille'],
        'bologna' => ['milan', 'rome', 'verona', 'naples'],
        'naples' => ['rome', 'bologna'],
        'verona' => ['milan', 'munich', 'bologna', 'vienna'],
        'madrid' => ['barcelona', 'valencia', 'zaragoza', 'seville', 'lisbon', 'porto'],
        'barcelona' => ['madrid', 'valencia', 'zaragoza', 'montpellier'],
        'valencia' => ['madrid', 'barcelona', 'zaragoza'],
        'zaragoza' => ['madrid', 'barcelona', 'bordeaux', 'valencia'],
        'seville' => ['madrid', 'lisbon'],
        'warsaw' => ['krakow', 'berlin', 'poznan', 'gdansk', 'wroclaw'],
        'krakow' => ['warsaw', 'wroclaw', 'budapest', 'vienna'],
        'wroclaw' => ['krakow', 'poznan', 'prague', 'leipzig', 'berlin'],
        'poznan' => ['warsaw', 'wroclaw', 'berlin'],
        'gdansk' => ['warsaw', 'malmo'],
        'brussels' => ['antwerp', 'liege', 'amsterdam', 'lille', 'cologne', 'paris', 'rotterdam'],
        'antwerp' => ['brussels', 'rotterdam', 'eindhoven', 'liege', 'düsseldorf'],
        'liege' => ['brussels', 'cologne', 'antwerp'],
        'vienna' => ['linz', 'budapest', 'prague', 'munich', 'krakow', 'verona', 'salzburg'],
        'linz' => ['vienna', 'salzburg', 'prague'],
        'salzburg' => ['munich', 'vienna', 'linz'],
        'prague' => ['berlin', 'vienna', 'munich', 'wroclaw', 'brno', 'leipzig'],
        'brno' => ['prague', 'vienna', 'budapest'],
        'stockholm' => ['malmo', 'copenhagen'],
        'malmo' => ['copenhagen', 'stockholm', 'gdansk', 'hamburg'],
        'copenhagen' => ['malmo', 'hamburg', 'stockholm'],
        'budapest' => ['vienna', 'brno', 'krakow', 'bucharest'],
        'lisbon' => ['porto', 'madrid', 'seville'],
        'porto' => ['lisbon', 'madrid'],
        'bucharest' => ['budapest'],
    ],
];
