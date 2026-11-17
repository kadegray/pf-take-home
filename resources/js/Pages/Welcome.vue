<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});

const user = usePage().props.auth?.user;

const steps = [
    {
        title: 'Describe it',
        body: 'Write a prompt for the image you want — as simple or as detailed as you like.',
    },
    {
        title: 'Generate',
        body: 'Pixelforge renders it in seconds and charges only for what you use, down to the fraction of a cent.',
    },
    {
        title: 'Keep what you make',
        body: 'Every generation lands in your history so you can revisit, reuse, or download it later.',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-100 bg-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-5 lg:px-8"
            >
                <div class="flex items-center gap-2">
                    <ApplicationLogo class="h-8 w-auto fill-current text-gray-800" />
                    <span class="text-lg font-semibold text-gray-900">Pixelforge</span>
                </div>

                <nav v-if="canLogin" class="flex items-center gap-3">
                    <Link
                        v-if="user"
                        :href="route('dashboard')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                    >
                        Go to dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:text-gray-900"
                        >
                            Log in
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                        >
                            Sign up
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-5xl px-6 py-20 text-center lg:px-8">
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Turn words into images,
                    <span class="text-indigo-600">on demand</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
                    Pixelforge generates images from a plain-text prompt and bills
                    your account by the generation — no subscriptions, no
                    wasted credits.
                </p>

                <div class="mt-10 flex items-center justify-center gap-4">
                    <Link
                        v-if="user"
                        :href="route('generate.create')"
                        class="rounded-md bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500"
                    >
                        Start generating
                    </Link>
                    <Link
                        v-else-if="canRegister"
                        :href="route('register')"
                        class="rounded-md bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500"
                    >
                        Create a free account
                    </Link>
                    <Link
                        v-if="!user && canLogin"
                        :href="route('login')"
                        class="rounded-md px-6 py-3 text-base font-semibold text-gray-700 hover:text-gray-900"
                    >
                        Log in
                    </Link>
                </div>
            </section>

            <section class="border-t border-gray-100 bg-white">
                <div class="mx-auto max-w-5xl px-6 py-16 lg:px-8">
                    <div class="grid gap-10 sm:grid-cols-3">
                        <div v-for="(step, index) in steps" :key="step.title">
                            <div
                                class="flex size-10 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white"
                            >
                                {{ index + 1 }}
                            </div>
                            <h2 class="mt-4 text-lg font-semibold text-gray-900">
                                {{ step.title }}
                            </h2>
                            <p class="mt-2 text-sm text-gray-600">
                                {{ step.body }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-gray-100 py-10 text-center text-sm text-gray-500">
            &copy; {{ new Date().getFullYear() }} Pixelforge
        </footer>
    </div>
</template>
