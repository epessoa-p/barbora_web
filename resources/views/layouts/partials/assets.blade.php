<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('img/logo.svg') }}">

{{--
    Carga de estilos y scripts.

    Lo normal es servirlos con Vite (`npm install && npm run build`, o
    `npm run dev`). Mientras no se hayan compilado —por ejemplo en una máquina
    sin Node recién clonada— se cae al CDN para que la aplicación siga
    funcionando. En cuanto exista el manifest, Vite toma el control solo.
--}}
@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
@else
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
@endif
