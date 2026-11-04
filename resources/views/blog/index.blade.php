@extends('blog.layout')

@section('title', config('app.name').' - posts do dia')

@section('content')
    @forelse ($posts as $post)
        <article class="card">
            <div class="meta">{{ $post->post_date->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }}</div>
            <h2><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)<p style="margin:0">{{ $post->excerpt }}</p>@endif
            @if (! empty($post->guesses['animals']))
                <div class="chips">
                    @foreach ($post->guesses['animals'] as $number)
                        <span class="chip">{{ \App\Support\Bicho\Bicho::animalName((int) $number) }}</span>
                    @endforeach
                </div>
            @endif
        </article>
    @empty
        <div class="card"><p style="margin:0">Ainda não há posts publicados.</p></div>
    @endforelse

    @if ($posts->hasPages())
        <div class="pager">
            <span>@if (! $posts->onFirstPage())<a href="{{ $posts->previousPageUrl() }}">&larr; Mais recentes</a>@endif</span>
            <span>@if ($posts->hasMorePages())<a href="{{ $posts->nextPageUrl() }}">Anteriores &rarr;</a>@endif</span>
        </div>
    @endif
@endsection
