<?php

namespace App\Simulation\Domain\Environment\Services;

use App\Simulation\Domain\Environment\Models\Weather;
use App\Simulation\Core\SimulationClock;

/**
 * WeatherGenerator Service.
 * Generates deterministic weather based on simulation time and scenario.
 * Never uses randomness.
 */
class WeatherGenerator
{
    private string $scenario = 'normal'; // normal, rain, storm, fog, snow, extreme

    /**
     * Generate weather based on simulation time.
     */
    public function generate(SimulationClock $clock): Weather
    {
        $time = $clock->getCurrentTime();
        $hour = (int)$time->format('H');

        // TODO: Implement deterministic weather generation based on scenario
        // This is architecture preparation only
        
        // Placeholder: Return weather based on hour and scenario
        return match($this->scenario) {
            'rain' => $this->generateRainWeather($hour),
            'storm' => $this->generateStormWeather($hour),
            'fog' => $this->generateFogWeather($hour),
            'snow' => $this->generateSnowWeather($hour),
            'extreme' => $this->generateExtremeWeather($hour),
            default => $this->generateNormalWeather($hour),
        };
    }

    /**
     * Generate normal weather.
     */
    private function generateNormalWeather(int $hour): Weather
    {
        // Clear during day, cloudy at night
        if ($hour >= 6 && $hour <= 18) {
            return Weather::clear(0.0);
        }
        return Weather::cloudy(0.3);
    }

    /**
     * Generate rain weather.
     */
    private function generateRainWeather(int $hour): Weather
    {
        // Rain intensity varies by time
        $intensity = 0.5;
        if ($hour >= 12 && $hour <= 16) {
            $intensity = 0.7; // Heavier rain in afternoon
        }
        return Weather::rain($intensity);
    }

    /**
     * Generate storm weather.
     */
    private function generateStormWeather(int $hour): Weather
    {
        // Storm intensity peaks in afternoon
        $intensity = 0.8;
        if ($hour >= 14 && $hour <= 16) {
            $intensity = 1.0; // Peak storm
        }
        return Weather::storm($intensity);
    }

    /**
     * Generate fog weather.
     */
    private function generateFogWeather(int $hour): Weather
    {
        // Fog is heavier in morning
        $intensity = 0.6;
        if ($hour >= 5 && $hour <= 9) {
            $intensity = 0.9; // Heavy morning fog
        }
        return Weather::fog($intensity);
    }

    /**
     * Generate snow weather.
     */
    private function generateSnowWeather(int $hour): Weather
    {
        // Snow intensity varies
        $intensity = 0.5;
        if ($hour >= 10 && $hour <= 14) {
            $intensity = 0.7; // Heavier snow during day
        }
        return Weather::snow($intensity);
    }

    /**
     * Generate extreme weather.
     */
    private function generateExtremeWeather(int $hour): Weather
    {
        // Extreme conditions vary by hour
        return match(true) {
            $hour >= 6 && $hour <= 12 => Weather::storm(0.9),
            $hour >= 12 && $hour <= 18 => Weather::rain(0.8),
            default => Weather::fog(0.7),
        };
    }

    /**
     * Set scenario.
     */
    public function setScenario(string $scenario): void
    {
        $this->scenario = $scenario;
    }

    /**
     * Get scenario.
     */
    public function getScenario(): string
    {
        return $this->scenario;
    }
}
