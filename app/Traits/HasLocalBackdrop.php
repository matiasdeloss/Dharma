<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;

trait HasLocalBackdrop
{
    /**
     * Get a local curated backdrop from public/images/auth/ (0 API calls, 0ms latency)
     */
    protected function getLocalBackdrop(): array
    {
        $dir = public_path('images/auth');
        $localFiles = File::exists($dir)
            ? collect(File::files($dir))
                ->filter(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp']))
                ->values()
            : collect();

        if ($localFiles->isNotEmpty()) {
            $chosen = $localFiles->random();
            $filename = $chosen->getFilename();
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $cleanTitle = '';
            if (!str_starts_with($filename, 'MV5') && !str_contains($filename, '@') && strlen($nameWithoutExt) < 40) {
                $cleanTitle = ucwords(str_replace(['-', '_'], ' ', $nameWithoutExt));
            }

            return [
                'url' => asset('images/auth/' . $filename),
                'title' => $cleanTitle,
            ];
        }

        return [
            'url' => 'https://image.tmdb.org/t/p/w1280/sAtoMqDVhNDQBc3QJL3RF6hlxGq.jpg',
            'title' => '',
        ];
    }
}
