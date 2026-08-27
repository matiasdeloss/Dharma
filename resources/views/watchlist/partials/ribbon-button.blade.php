<form hx-post="{{ route('watchlist.toggle') }}" hx-swap="outerHTML" hx-disabled-elt="find button" class="d-flex m-0 p-0">
    @csrf
    <input type="hidden" name="style" value="ribbon">
    <input type="hidden" name="tmdb_id" value="{{ $tmdbId }}">
    <input type="hidden" name="media_type" value="{{ $mediaType }}">
    <input type="hidden" name="title" value="{{ $title }}">
    <input type="hidden" name="poster_path" value="{{ $posterPath }}">
    <input type="hidden" name="release_date" value="{{ $releaseDate }}">
    <input type="hidden" name="vote_average" value="{{ $voteAverage }}">

    @if($inWatchlist)
        <button type="submit" class="ribbon-btn shadow p-0" title="En tu Watchlist (Clic para quitar)">
            <i class="bi bi-bookmark-check-fill ribbon-icon-active"></i>
        </button>
    @else
        <button type="submit" class="ribbon-btn shadow p-0" title="Guardar en Watchlist">
            <i class="bi bi-bookmark-plus ribbon-icon-inactive"></i>
        </button>
    @endif
</form>
