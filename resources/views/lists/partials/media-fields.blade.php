{{--
    El título que viaja con cada form de listas. Si todavía no está en la base,
    MediaCatalog lo crea con los datos de TMDB; esto es solo respaldo por si
    TMDB no responde.

    Props: $media (array TMDB con id), $type
--}}
<input type="hidden" name="tmdb_id" value="{{ $media['id'] }}">
<input type="hidden" name="media_type" value="{{ $type }}">
<input type="hidden" name="title" value="{{ $media['title'] ?? $media['name'] ?? '' }}">
<input type="hidden" name="poster_path" value="{{ $media['poster_path'] ?? '' }}">
<input type="hidden" name="release_date" value="{{ $media['release_date'] ?? $media['first_air_date'] ?? '' }}">
<input type="hidden" name="vote_average" value="{{ $media['vote_average'] ?? '' }}">
