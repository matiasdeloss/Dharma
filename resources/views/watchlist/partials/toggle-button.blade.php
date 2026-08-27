<form hx-post="{{ route('watchlist.toggle') }}" hx-swap="outerHTML" class="d-inline">
    @csrf
    <input type="hidden" name="tmdb_id" value="{{ $tmdbId }}">
    <input type="hidden" name="media_type" value="{{ $mediaType }}">
    <input type="hidden" name="title" value="{{ $title }}">
    <input type="hidden" name="poster_path" value="{{ $posterPath }}">
    <input type="hidden" name="release_date" value="{{ $releaseDate }}">
    <input type="hidden" name="vote_average" value="{{ $voteAverage }}">

    @if($inWatchlist)
        <button type="submit" class="btn btn-outline-info d-flex align-items-center gap-2">
            <i class="bi bi-bookmark-check-fill text-info"></i>
            <span>En tu Watchlist</span>
        </button>
    @else
        <button type="submit" class="btn btn-cine-secondary d-flex align-items-center gap-2">
            <i class="bi bi-bookmark-plus"></i>
            <span>Agregar a Watchlist</span>
        </button>
    @endif
</form>
