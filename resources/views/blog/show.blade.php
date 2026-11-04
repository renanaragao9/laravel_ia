@extends('blog.layout')

@section('title', $post->title.' - '.config('app.name'))
@section('description', $post->excerpt)

@section('content')
    <article>
        <div class="meta">{{ $post->post_date->locale('pt_BR')->translatedFormat('l, d \d\e F \d\e Y') }}</div>
        <h1>{{ $post->title }}</h1>

        {!! \Illuminate\Support\Str::markdown($post->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}

        @if (! empty($post->guesses['animals']) || ! empty($post->guesses['tens']))
            <div class="box">
                <h3>Palpites do dia (só curiosidade)</h3>
                @if (! empty($post->guesses['animals']))
                    <div class="chips">
                        @foreach ($post->guesses['animals'] as $number)
                            <span class="chip">{{ (int) $number }} - {{ \App\Support\Bicho\Bicho::animalName((int) $number) }}</span>
                        @endforeach
                    </div>
                @endif
                @if (! empty($post->guesses['tens']))
                    <div class="chips">
                        @foreach ($post->guesses['tens'] as $ten)
                            <span class="chip">{{ $ten }}</span>
                        @endforeach
                    </div>
                @endif
                <p class="meta" style="margin:10px 0 0">Gerado a partir de estatística do histórico. O sorteio é aleatório: isto não aumenta a chance de acerto.</p>
            </div>
        @endif
    </article>
    <p><a href="{{ route('blog.index') }}">&larr; Todos os posts</a></p>
@endsection
