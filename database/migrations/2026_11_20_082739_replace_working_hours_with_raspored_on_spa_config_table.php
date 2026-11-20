<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single working-hours window (radno_od/radno_do/broj_slotova, equal slots
     * every day) with a weekly schedule: per ISO weekday (1 = ponedeljak … 7 = nedelja)
     * a list of {start, end} termini. Existing configs get their old slots on every day.
     */
    public function up(): void
    {
        Schema::table('spa_config', function (Blueprint $table) {
            $table->json('raspored')->nullable()->after('horizont_dana');
        });

        foreach (DB::table('spa_config')->get() as $row) {
            $termini = $this->equalSlots((string) $row->radno_od, (string) $row->radno_do, (int) $row->broj_slotova);
            $raspored = array_fill_keys(range(1, 7), $termini);

            DB::table('spa_config')->where('id', $row->id)->update(['raspored' => json_encode($raspored)]);
        }

        Schema::table('spa_config', function (Blueprint $table) {
            $table->dropColumn(['radno_od', 'radno_do', 'broj_slotova']);
        });
    }

    /**
     * Reverse the migrations. The weekly schedule collapses to Monday's span and slot count.
     */
    public function down(): void
    {
        Schema::table('spa_config', function (Blueprint $table) {
            $table->time('radno_od')->default('12:00')->after('horizont_dana');
            $table->time('radno_do')->default('21:00')->after('radno_od');
            $table->unsignedTinyInteger('broj_slotova')->default(3)->after('radno_do');
        });

        foreach (DB::table('spa_config')->get() as $row) {
            /** @var array<int|string, list<array{start: string, end: string}>> $raspored */
            $raspored = json_decode((string) $row->raspored, true) ?: [];
            $ponedeljak = $raspored[1] ?? $raspored['1'] ?? [];

            if ($ponedeljak === []) {
                continue;
            }

            DB::table('spa_config')->where('id', $row->id)->update([
                'radno_od' => $ponedeljak[0]['start'],
                'radno_do' => $ponedeljak[count($ponedeljak) - 1]['end'],
                'broj_slotova' => count($ponedeljak),
            ]);
        }

        Schema::table('spa_config', function (Blueprint $table) {
            $table->dropColumn('raspored');
        });
    }

    /**
     * The old slot rule: equal-length blocks that evenly fill radno_od..radno_do.
     *
     * @return list<array{start: string, end: string}>
     */
    private function equalSlots(string $od, string $do, int $broj): array
    {
        $start = $this->minutes($od);
        $length = intdiv($this->minutes($do) - $start, max(1, $broj));
        $termini = [];

        for ($i = 0; $i < $broj; $i++) {
            $termini[] = [
                'start' => $this->format($start + $length * $i),
                'end' => $this->format($start + $length * ($i + 1)),
            ];
        }

        return $termini;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time);

        return (int) $hour * 60 + (int) $minute;
    }

    private function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
};
