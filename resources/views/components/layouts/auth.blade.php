@props(['title', 'admin' => false])

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
<body class="h-full bg-white font-sans text-slate-900 dark:bg-slate-950 dark:text-slate-100">
    <div class="flex min-h-full">
        <div class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-12 lg:flex-none lg:px-20 xl:px-28">
            <div class="mx-auto w-full max-w-sm animate-slide-up">
                <x-bo.logo :admin="$admin" />
                {{ $slot }}
            </div>
        </div>

        <div @class(['relative hidden flex-1 overflow-hidden lg:block', 'bg-slate-950' => $admin, 'bg-brand-gradient' => ! $admin])>
            <div class="bg-grid absolute inset-0 opacity-25"></div>
            <div @class(['absolute -top-32 -right-32 size-[32rem] rounded-full blur-3xl', 'bg-emerald-500/10' => $admin, 'bg-white/10' => ! $admin])></div>
            <div @class(['absolute -bottom-40 -left-20 size-[28rem] rounded-full blur-3xl', 'bg-brand-600/20' => $admin, 'bg-fuchsia-400/30' => ! $admin])></div>

            <div class="relative flex h-full flex-col justify-between p-12 text-white xl:p-16">
                {{ $aside }}
            </div>
        </div>
    </div>
</body>
</html>
