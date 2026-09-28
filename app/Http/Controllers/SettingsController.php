<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Ajustes del usuario: región y plataformas de streaming.
 *
 * Las plataformas salen de TMDB para la región elegida (cambiar la región
 * recarga la lista sin guardar); al guardar se reemplaza el set completo.
 */
class SettingsController extends Controller
{
    public function __construct(protected TmdbService $tmdb)
    {
    }

    public function edit(Request $request)
    {
        $user = Auth::user();
        $regions = $this->tmdb->getWatchRegions();

        // La región del selector puede diferir de la guardada mientras el
        // usuario explora qué plataformas hay en cada una.
        $region = strtoupper((string) $request->input('region', $user->region));
        if (! isset($regions[$region])) {
            $region = $user->region;
        }

        return view('settings.edit', [
            'regions' => $regions,
            'region' => $region,
            'providers' => array_map(fn ($p) => $p + [
                'logo_url' => $p['logo_path'] ? $this->tmdb->imageUrl($p['logo_path'], 'w92') : null,
            ], $this->tmdb->getWatchProviders($region)),
            'selected' => $user->providerIds(),
            'bienvenida' => $request->boolean('bienvenida'),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $regions = $this->tmdb->getWatchRegions();

        $validated = $request->validate([
            'region' => ['required', 'string', 'size:2', Rule::in(array_keys($regions))],
            'providers' => ['nullable', 'array'],
            'providers.*' => ['integer'],
        ]);

        // Solo se aceptan ids que TMDB ofrece para esa región; de paso se
        // toma de ahí el nombre y el logo actuales.
        $available = collect($this->tmdb->getWatchProviders($validated['region']))->keyBy('provider_id');

        $chosen = collect($validated['providers'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn ($id) => $available->has($id))
            ->map(fn ($id) => [
                'provider_id' => $id,
                'name' => $available[$id]['provider_name'],
                'logo_path' => $available[$id]['logo_path'],
            ]);

        $user->update(['region' => $validated['region']]);
        $user->providers()->delete();
        $user->providers()->createMany($chosen->values()->all());

        return redirect()->route('settings.edit')
            ->with('success', $chosen->isEmpty()
                ? 'Región guardada. No elegiste plataformas todavía.'
                : 'Plataformas guardadas: '.$chosen->pluck('name')->join(', ', ' y ').'.');
    }
}
