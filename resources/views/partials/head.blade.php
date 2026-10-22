<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#16221c">
<meta name="description" content="{{ __('ui.app.description') }}">

<title>{{ isset($title) && $title ? $title.' · '.__('ui.app.name') : __('ui.app.name').' — '.__('ui.app.tagline') }}</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Manrope:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">

<script>
    (() => {
        try {
            const stored = localStorage.getItem('realitylog-theme');
            const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.dataset.theme = stored || @json($currentSettings->theme ?? 'light') || preferred;
        } catch (error) {
            document.documentElement.dataset.theme = @json($currentSettings->theme ?? 'light');
        }
    })();
</script>

@vite(['resources/js/app.js'])
