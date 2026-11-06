@php
    $appName = config('app.name', 'Laravel');
    $pageTitle = $appName.' – Quality Products, Delivered to Your Door';
    $pageDescription = 'Shop the latest products at '.$appName.'. Browse our newest arrivals, enjoy great prices, and create a free account to start ordering today.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => null])
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        <link rel="canonical" href="{{ url('/') }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta name="twitter:card" content="summary">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Store',
                'name' => $appName,
                'url' => url('/'),
                'description' => $pageDescription,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
        </script>
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <header class="sticky top-0 z-10 border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <a href="{{ route('home') }}" class="flex items-center" aria-label="{{ $appName }} home">
                    <x-app-logo />
                </a>

                <nav aria-label="Main" class="hidden items-center gap-6 text-sm md:flex">
                    <a href="#products" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">New arrivals</a>
                    <a href="#contact" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Contact</a>
                </nav>

                <div class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" size="sm">Dashboard</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">Log in</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">Register</flux:button>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-6 py-20 text-center md:py-28">
                <flux:badge color="zinc" class="mb-4">New arrivals every week</flux:badge>
                <h1 class="mx-auto max-w-3xl text-4xl font-semibold tracking-tight text-zinc-900 md:text-6xl dark:text-white">
                    Quality products, delivered to your door
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
                    Discover our latest arrivals at {{ $appName }}. Create a free account to browse the full catalog and place your order.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    @guest
                        <flux:button :href="route('register')" variant="primary">Create free account</flux:button>
                        <flux:button :href="route('login')">Log in</flux:button>
                    @else
                        <flux:button :href="route('products.index')" variant="primary">Browse products</flux:button>
                    @endguest
                </div>
            </section>

            <section id="products" class="mx-auto max-w-6xl px-6 py-16" aria-labelledby="products-heading">
                <div class="mb-8 text-center">
                    <h2 id="products-heading" class="text-2xl font-semibold text-zinc-900 md:text-3xl dark:text-white">Latest products</h2>
                    <p class="mt-2 text-zinc-600 dark:text-zinc-400">Fresh in stock and ready for you.</p>
                </div>

                @if ($products->isEmpty())
                    <flux:callout icon="information-circle">
                        <flux:callout.heading>No products available yet</flux:callout.heading>
                        <flux:callout.text>Please check back soon for new arrivals.</flux:callout.text>
                    </flux:callout>
                @else
                    <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($products as $product)
                            <li>
                                <article class="flex h-full flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                                    @if ($product->category)
                                        <div><flux:badge size="sm" color="zinc">{{ $product->category->name }}</flux:badge></div>
                                    @endif
                                    <h3 class="text-lg font-medium text-zinc-900 dark:text-white">{{ $product->name }}</h3>
                                    <p class="grow text-sm text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($product->description, 120) }}</p>
                                    <p class="text-xl font-semibold text-zinc-900 dark:text-white">₱{{ number_format($product->price, 2) }}</p>
                                </article>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @guest
                    <p class="mt-8 text-center text-sm text-zinc-600 dark:text-zinc-400">
                        <a href="{{ route('login') }}" class="font-medium underline">Log in</a> or
                        <a href="{{ route('register') }}" class="font-medium underline">register</a> to start shopping.
                    </p>
                @endguest
            </section>

            <section id="contact" class="mx-auto max-w-6xl px-6 py-16" aria-labelledby="contact-heading">
                <div class="mx-auto max-w-xl">
                    <div class="mb-8 text-center">
                        <h2 id="contact-heading" class="text-2xl font-semibold text-zinc-900 md:text-3xl dark:text-white">Contact us</h2>
                        <p class="mt-2 text-zinc-600 dark:text-zinc-400">Questions about an order or a product? Send us a message.</p>
                    </div>

                    <form class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900" onsubmit="return false">
                        <flux:input name="name" label="Name" type="text" placeholder="Your name" autocomplete="name" />
                        <flux:input name="email" label="Email" type="email" placeholder="you@example.com" autocomplete="email" />
                        <flux:textarea name="message" label="Message" rows="4" placeholder="How can we help?" />
                        <flux:button type="submit" variant="primary" class="w-full">Send message</flux:button>
                    </form>
                </div>
            </section>
        </main>

        <footer class="mt-16 border-t border-zinc-200 dark:border-zinc-800">
            <div class="mx-auto grid max-w-6xl gap-8 px-6 py-12 md:grid-cols-3">
                <div class="flex flex-col gap-3">
                    <x-app-logo />
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Quality products at fair prices, delivered to your door.</p>
                </div>

                <nav aria-label="Footer" class="flex flex-col gap-2 text-sm">
                    <h2 class="font-medium text-zinc-900 dark:text-white">Quick links</h2>
                    <a href="#products" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">New arrivals</a>
                    <a href="#contact" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Contact us</a>
                    @guest
                        <a href="{{ route('login') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Log in</a>
                        <a href="{{ route('register') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Register</a>
                    @endguest
                </nav>

                <address class="flex flex-col gap-2 text-sm not-italic">
                    <h2 class="font-medium text-zinc-900 dark:text-white">Get in touch</h2>
                    <span class="text-zinc-600 dark:text-zinc-400">support@example.com</span>
                    <span class="text-zinc-600 dark:text-zinc-400">Mon–Fri, 9:00 AM – 6:00 PM</span>
                </address>
            </div>
            <div class="border-t border-zinc-200 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800">
                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
