<button type="button" class="video-facade" data-youtube="{{ $id }}" aria-label="Video afspelen{{ filled($caption ?? null) ? ': '.$caption : '' }}">
    <img src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg" alt="" loading="lazy">
    <span class="video-play" aria-hidden="true"><svg viewBox="0 0 68 48" width="68" height="48"><path d="M66.5 7.7a8.5 8.5 0 0 0-6-6C55.3.3 34 .3 34 .3S12.7.3 7.5 1.7a8.5 8.5 0 0 0-6 6C.1 12.9.1 24 .1 24s0 11.1 1.4 16.3a8.5 8.5 0 0 0 6 6C12.7 47.7 34 47.7 34 47.7s21.3 0 26.5-1.4a8.5 8.5 0 0 0 6-6C67.9 35.1 67.9 24 67.9 24s0-11.1-1.4-16.3z" fill="#e00"/><path d="M45 24 27 14v20z" fill="#fff"/></svg></span>
</button>
