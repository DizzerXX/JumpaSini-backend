<?php

namespace App\Services\Weather;

class WeatherAssessmentService
{
    public function __construct(
        private readonly CachedWeatherClient $weatherClient,
    ) {}

    public function assess(float $lat, float $lng, string $venueType): WeatherAssessment
    {
        $outdoorTypes = (array) config('jumpa.weather.outdoor_types', []);

        if (!in_array($venueType, $outdoorTypes, true)) {
            return new WeatherAssessment(
                severity:    'good',
                headline:    'Indoor venue',
                message:     'Indoor venue — weather not a concern',
                emoji:       '🏠',
                shouldWarn:  false,
                isSafe:      true,
                forecast:    [],
                temperature: 0.0,
                humidity:    0,
            );
        }

        $current  = $this->weatherClient->getCurrentWeather($lat, $lng);
        $severity = $this->classifyWeather($current);
        $message  = $this->buildMessage($current, $severity, $venueType);
        $forecast = $this->assessForecast($lat, $lng);

        [$headline, $emoji] = $this->headlineAndEmoji($severity, $current);

        return new WeatherAssessment(
            severity:    $severity,
            headline:    $headline,
            message:     $message,
            emoji:       $emoji,
            shouldWarn:  in_array($severity, ['caution', 'warning', 'severe'], true),
            isSafe:      $severity !== 'severe',
            forecast:    $forecast,
            temperature: $current->temperature,
            humidity:    $current->humidity,
        );
    }

    public function classifyWeather(WeatherData $data): string
    {
        $code              = $data->conditionCode;
        $thunderstormCodes = (array) config('jumpa.weather.thunderstorm_codes', []);
        $rainCodes         = (array) config('jumpa.weather.rain_codes', []);
        $drizzleCodes      = (array) config('jumpa.weather.drizzle_codes', []);
        $rainThreshold     = (float) config('jumpa.weather.rain_threshold_mm', 2.5);

        if (in_array($code, $thunderstormCodes, true)) {
            return 'severe';
        }

        if (in_array($code, $rainCodes, true) && $data->rainMmPerHour > $rainThreshold) {
            return 'warning';
        }

        if (
            in_array($code, $drizzleCodes, true)
            || (in_array($code, $rainCodes, true) && $data->rainMmPerHour <= $rainThreshold)
        ) {
            return 'caution';
        }

        return 'good';
    }

    public function buildMessage(WeatherData $data, string $severity, string $venueType): string
    {
        return match ($severity) {
            'severe'  => "⚠️ Thunderstorm warning near this {$venueType}. We strongly recommend choosing an indoor alternative.",
            'warning' => "🌧️ Heavy rain expected near this {$venueType}. Consider bringing an umbrella or choosing an indoor spot.",
            'caution' => "🌦️ Light rain possible near this {$venueType}. You may want to bring an umbrella just in case.",
            default   => "☀️ Weather looks good for your {$venueType} visit!",
        };
    }

    public function assessForecast(float $lat, float $lng): array
    {
        $entries = $this->weatherClient->getForecast($lat, $lng, 24);

        return array_map(static function (WeatherData $entry): array {
            return [
                'time'        => $entry->timestamp->format('Y-m-d H:i'),
                'condition'   => $entry->conditionMain,
                'temperature' => $entry->temperature,
                'emoji'       => self::emojiForCondition($entry->conditionMain),
            ];
        }, array_slice($entries, 0, 8));
    }

    private function headlineAndEmoji(string $severity, WeatherData $data): array
    {
        return match ($severity) {
            'severe'  => ['Thunderstorm warning', '⛈️'],
            'warning' => ['Heavy rain expected', '🌧️'],
            'caution' => ['Light rain possible', '🌦️'],
            default   => ['Weather looks good', '☀️'],
        };
    }

    private static function emojiForCondition(string $conditionMain): string
    {
        return match (strtolower($conditionMain)) {
            'thunderstorm'       => '⛈️',
            'drizzle', 'rain'    => '🌧️',
            'snow'               => '❄️',
            'mist', 'fog', 'haze',
            'smoke', 'dust',
            'sand', 'ash'        => '🌫️',
            'squall', 'tornado'  => '🌪️',
            'clouds'             => '☁️',
            default              => '☀️',
        };
    }
}
