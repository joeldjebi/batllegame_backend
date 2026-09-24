@props(['title', 'admin' => false, 'wide' => false, 'fit' => false])

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Battle Game</title>
    <script>
        (() => {
            const mode = localStorage.getItem('theme') ?? 'system';
            if (mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['h-full bg-white font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100', 'overflow-hidden' => $fit])>
    {{-- fit: the page holds in the viewport (no scroll); the slot manages its own height. --}}
    <div @class(['flex', 'min-h-full' => ! $fit, 'h-dvh overflow-hidden' => $fit])>
        <div @class([
            'flex flex-1 flex-col lg:flex-none',
            'justify-center px-6 py-12 sm:px-12 lg:px-20 xl:px-28' => ! $fit,
            'min-h-0 px-5 pt-5 pb-4 sm:px-12 sm:pt-8 sm:pb-6 lg:w-[34rem] lg:px-16 xl:w-[38rem] xl:px-20' => $fit,
        ])>
            <div @class(['mx-auto w-full animate-slide-up', 'max-w-sm' => ! $wide, 'max-w-md' => $wide, 'flex min-h-0 flex-1 flex-col' => $fit])>
                <x-bo.logo :admin="$admin" />
                {{ $slot }}
            </div>
        </div>

        <div @class(['relative hidden flex-1 overflow-hidden lg:block', 'bg-slate-950' => $admin, 'bg-brand-700' => ! $admin])>


            <div class="relative flex h-full flex-col justify-between p-12 text-white xl:p-16">
                {{ $aside }}
            </div>
        </div>
    </div>
</body>
</html>
