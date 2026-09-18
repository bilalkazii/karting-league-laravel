<?php

namespace Tests\Unit;

use App\Support\RaceUtils;
use PHPUnit\Framework\TestCase;

class RaceUtilsTest extends TestCase
{
    public function test_format_lap_time_null_returns_dash(): void
    {
        $this->assertSame('—', RaceUtils::formatLapTime(null));
        $this->assertSame('—', RaceUtils::formatLapTime(-5));
    }

    public function test_format_lap_time_with_minutes(): void
    {
        $this->assertSame('1:23.500', RaceUtils::formatLapTime(83500));
    }

    public function test_format_lap_time_without_minutes(): void
    {
        $this->assertSame('32.415', RaceUtils::formatLapTime(32415));
    }

    public function test_format_lap_time_rolls_up_rounding(): void
    {
        $this->assertSame('32.416', RaceUtils::formatLapTime(32416));
    }

    public function test_format_stopwatch_pads_seconds(): void
    {
        $this->assertSame('04.215', RaceUtils::formatStopwatchMs(4215));
        $this->assertSame('1:02.000', RaceUtils::formatStopwatchMs(62000));
    }

    public function test_parse_lap_time_seconds_fraction(): void
    {
        $this->assertSame(32415, RaceUtils::parseLapTime('32.415'));
    }

    public function test_parse_lap_time_mm_ss_msm(): void
    {
        $this->assertSame(83500, RaceUtils::parseLapTime('1:23.5'));
    }

    public function test_parse_lap_time_rejects_garbage(): void
    {
        $this->assertNull(RaceUtils::parseLapTime('abcd'));
        $this->assertNull(RaceUtils::parseLapTime('0'));
        $this->assertNull(RaceUtils::parseLapTime('1:23'));
        $this->assertNull(RaceUtils::parseLapTime(''));
    }

    public function test_is_valid_qualifying_time(): void
    {
        $valid = ['qualifying_time_ms' => 32415, 'qualifying_status' => 'completed'];
        $corrected = ['qualifying_time_ms' => 32415, 'qualifying_status' => 'manually_corrected'];
        $invalid = ['qualifying_time_ms' => 32415, 'qualifying_status' => 'invalid'];
        $none = ['qualifying_time_ms' => null, 'qualifying_status' => 'not_started'];

        $this->assertTrue(RaceUtils::isValidQualifyingTime($valid));
        $this->assertTrue(RaceUtils::isValidQualifyingTime($corrected));
        $this->assertFalse(RaceUtils::isValidQualifyingTime($invalid));
        $this->assertFalse(RaceUtils::isValidQualifyingTime($none));
    }

    public function test_qualifying_rows_orders_official_then_invalid_then_none(): void
    {
        $entries = [
            ['driver_id' => 1, 'qualifying_time_ms' => null, 'qualifying_status' => 'not_started', 'confirmed' => true],
            ['driver_id' => 2, 'qualifying_time_ms' => 41800, 'qualifying_status' => 'completed', 'confirmed' => true],
            ['driver_id' => 3, 'qualifying_time_ms' => 42000, 'qualifying_status' => 'invalid', 'confirmed' => true],
            ['driver_id' => 4, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed', 'confirmed' => true],
            ['driver_id' => 5, 'qualifying_time_ms' => null, 'qualifying_status' => 'not_started', 'confirmed' => false],
        ];

        $ids = array_column(RaceUtils::qualifyingRows($entries), 'driver_id');

        $this->assertSame([4, 2, 3, 1, 5], $ids);
    }

    public function test_pole_time_returns_fastest_valid_only(): void
    {
        $entries = [
            ['driver_id' => 1, 'qualifying_time_ms' => 42000, 'qualifying_status' => 'invalid'],
            ['driver_id' => 2, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed'],
            ['driver_id' => 3, 'qualifying_time_ms' => null, 'qualifying_status' => 'not_started'],
        ];

        $this->assertSame(41750, RaceUtils::poleTime($entries));
    }

    public function test_pole_time_null_when_no_valid_times(): void
    {
        $this->assertNull(RaceUtils::poleTime([]));
    }

    public function test_compute_grid_rows_applies_penalty_in_seconds(): void
    {
        $entries = [
            ['driver_id' => 1, 'kart_number' => 7, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed', 'ready' => true, 'grid_position' => null, 'grid_penalty_seconds' => 0],
            ['driver_id' => 2, 'kart_number' => 14, 'qualifying_time_ms' => 41800, 'qualifying_status' => 'completed', 'ready' => true, 'grid_position' => null, 'grid_penalty_seconds' => 0],
        ];

        // 1s penalty drops driver 1 behind driver 2.
        $entries[0]['grid_penalty_seconds'] = 1;

        $rows = RaceUtils::computeGridRows($entries);

        // Rows keep qualifying order; the grid_position reflects the penalty swap.
        $this->assertSame(1, $rows[0]['driver_id']);
        $this->assertSame(2, $rows[0]['grid_position']);
        $this->assertSame(2, $rows[1]['driver_id']);
        $this->assertSame(1, $rows[1]['grid_position']);
    }

    public function test_compute_grid_rows_dropped_to_rear_without_valid_time(): void
    {
        $entries = [
            ['driver_id' => 1, 'kart_number' => 7, 'qualifying_time_ms' => 41750, 'qualifying_status' => 'completed', 'ready' => true, 'grid_position' => null, 'grid_penalty_seconds' => 0],
            ['driver_id' => 2, 'kart_number' => 3, 'qualifying_time_ms' => null, 'qualifying_status' => 'not_started', 'ready' => false, 'grid_position' => null, 'grid_penalty_seconds' => 0],
            ['driver_id' => 3, 'kart_number' => 9, 'qualifying_time_ms' => 41760, 'qualifying_status' => 'completed', 'ready' => true, 'grid_position' => null, 'grid_penalty_seconds' => 0],
        ];

        $rows = RaceUtils::computeGridRows($entries);

        $this->assertSame(1, $rows[0]['driver_id']);
        $this->assertSame(3, $rows[1]['driver_id']);
        $this->assertSame(2, $rows[2]['driver_id']);
    }

    public function test_build_final_results_orders_and_assigns_missing_positions(): void
    {
        $entries = [
            ['driver_id' => 1, 'status' => 'finished', 'finish_position' => null, 'grid_position' => 2, 'qualifying_time_ms' => 41750, 'penalty_total_seconds' => 0, 'notes' => ''],
            ['driver_id' => 2, 'status' => 'finished', 'finish_position' => null, 'grid_position' => 1, 'qualifying_time_ms' => 41700, 'penalty_total_seconds' => 0, 'notes' => ''],
            ['driver_id' => 3, 'status' => 'dnf', 'finish_position' => null, 'grid_position' => 3, 'qualifying_time_ms' => 41800, 'penalty_total_seconds' => 5, 'notes' => ''],
        ];

        $results = RaceUtils::buildFinalResults($entries);

        $this->assertSame(2, $results[0]['driver_id']);
        $this->assertSame(1, $results[0]['finish_position']);
        $this->assertSame(1, $results[1]['driver_id']);
        $this->assertSame(2, $results[1]['finish_position']);
        $this->assertSame(3, $results[2]['driver_id']);
        $this->assertNull($results[2]['finish_position']);
    }
}
