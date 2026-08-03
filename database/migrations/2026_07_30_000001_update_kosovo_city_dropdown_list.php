<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $cities = [
        'Deçan',
        'Drenas',
        'Ferizaj',
        'Fushë Kosovë',
        'Gjakovë',
        'Gjilan',
        'Klinë',
        'Lipjan',
        'Malishevë',
        'Mitrovicë',
        'Obiliq',
        'Pejë',
        'Podujevë',
        'Prishtinë',
        'Prizren',
        'Shtime',
        'Suharekë',
        'Viti',
        'Vushtrri',
    ];

    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->renameCity('Kastriot', 'Obiliq');
        $this->renameCity('Fushe Kosove', 'Fushë Kosovë');
        $this->renameCity('Fushë Kosove', 'Fushë Kosovë');
        $this->renameCity('Podujeve', 'Podujevë');
        $this->renameCity('Suhareke', 'Suharekë');
        $this->renameCity('Decan', 'Deçan');
        $this->renameCity('Kline', 'Klinë');
        $this->renameCity('Kamenice', 'Kamenicë');

        foreach ($this->cities as $name) {
            $city = $this->cityByExactName($name);

            if ($city) {
                DB::table('cities')->where('id', $city->id)->update(['is_active' => true, 'updated_at' => now()]);
            } else {
                DB::table('cities')->insert([
                    'name' => $name,
                    'country' => 'XK',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('cities')
            ->whereIn('name', ['Gjithë Kosovën', 'Jashtë Vendit'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Keep city data on rollback to avoid breaking existing organizations or fields.
    }

    private function renameCity(string $from, string $to): void
    {
        $source = $this->cityByExactName($from);

        if (! $source) {
            return;
        }

        $target = $this->cityByExactName($to);

        if ($target && $target->id === $source->id) {
            DB::table('cities')->where('id', $source->id)->update([
                'name' => $to,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        if (! $target) {
            DB::table('cities')->where('id', $source->id)->update([
                'name' => $to,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        foreach (['organizations', 'football_fields', 'analytics_events'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->where('city_id', $source->id)->update(['city_id' => $target->id]);
            }
        }

        DB::table('cities')->where('id', $source->id)->delete();
    }

    private function cityByExactName(string $name): ?object
    {
        $query = DB::table('cities')->where('country', 'XK');

        if (DB::connection()->getDriverName() === 'mysql') {
            return $query->whereRaw('BINARY `name` = ?', [$name])->first();
        }

        return $query->where('name', $name)->first();
    }
};
