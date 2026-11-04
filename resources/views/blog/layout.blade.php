<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Estatística e curiosidades sobre os resultados históricos da Lotece, a Loteria Estadual do Ceará.')">
    <meta name="robots" content="index, follow">
    <style>
        :root { --bg: #f6f5f1; --card: #ffffff; --text: #1c1917; --muted: #78716c; --border: #e7e5e4; --primary: #b45309; --soft: #fef3c7; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0c0a09; --card: #1c1917; --text: #f5f5f4; --muted: #a8a29e; --border: #292524; --primary: #f59e0b; --soft: #3b2f0b; } }
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: Georgia, 'Times New Roman', serif; line-height: 1.7; }
        a { color: var(--primary); }
        .notice { background: var(--soft); border-bottom: 1px solid var(--border); font: 13px/1.5 system-ui, sans-serif; text-align: center; padding: 8px 16px; }
        header.site { max-width: 760px; margin: 0 auto; padding: 36px 20px 8px; font-family: system-ui, sans-serif; }
        header.site a { text-decoration: none; color: var(--text); }
        header.site h1 { margin: 0; font-size: 28px; letter-spacing: -.02em; }
        header.site p { margin: 4px 0 0; color: var(--muted); font-size: 15px; }
        main { max-width: 760px; margin: 0 auto; padding: 16px 20px 56px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 22px 24px; margin: 18px 0; }
        .card h2 { margin: 4px 0 8px; font-size: 22px; line-height: 1.3; }
        .card h2 a { color: var(--text); text-decoration: none; }
        .card h2 a:hover { color: var(--primary); }
        .meta { font: 13px system-ui, sans-serif; color: var(--muted); }
        .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; font-family: system-ui, sans-serif; }
        .chip { font-size: 12.5px; padding: 3px 10px; border-radius: 999px; background: var(--soft); color: var(--primary); border: 1px solid var(--border); }
        article h1 { font-size: 32px; line-height: 1.25; margin: 6px 0 8px; letter-spacing: -.01em; }
        article h2 { font-size: 22px; margin: 28px 0 8px; }
        article blockquote { margin: 16px 0; padding: 4px 16px; border-left: 3px solid var(--primary); color: var(--muted); }
        article hr { border: 0; border-top: 1px solid var(--border); margin: 28px 0; }
        .box { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin: 24px 0; font-family: system-ui, sans-serif; font-size: 15px; }
        .box h3 { margin: 0 0 8px; font-size: 15px; }
        .pager { display: flex; justify-content: space-between; font-family: system-ui, sans-serif; }
        footer.site { max-width: 760px; margin: 0 auto; padding: 24px 20px 48px; font: 13px/1.6 system-ui, sans-serif; color: var(--muted); border-top: 1px solid var(--border); }
    </style>
</head>
<body>
<div class="notice"><strong>Conteúdo para maiores de 18 anos.</strong> Estatística e curiosidade sobre resultados já divulgados pela Lotece (Loteria Estadual do Ceará). O sorteio é aleatório e nenhum palpite garante resultado.</div>
<header class="site">
    <a href="{{ route('blog.index') }}"><h1>{{ config('app.name') }}</h1></a>
    <p>O que dizem os números do histórico do dia, sem promessas.</p>
</header>
<main>
    @yield('content')
</main>
<footer class="site">
    {{ \App\Support\Blog\Disclaimer::TEXT }}
</footer>
</body>
</html>
