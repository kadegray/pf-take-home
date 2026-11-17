<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    generations: { type: Array, required: true },
});

function hideBrokenImage(event) {
    event.target.style.visibility = 'hidden';
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="generations.length === 0" class="overflow-hidden bg-white p-6 text-center text-gray-500 shadow-sm sm:rounded-lg">
                    No images yet —
                    <Link :href="route('generate.create')" class="text-indigo-600 hover:underline">generate one</Link>.
                </div>

                <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="generation in generations"
                        :key="generation.id"
                        :href="route('history.show', generation.id)"
                        class="group overflow-hidden rounded-lg bg-white shadow-sm"
                    >
                        <img
                            :src="generation.image_url"
                            :alt="generation.prompt"
                            class="aspect-square w-full object-cover transition group-hover:opacity-90"
                            @error="hideBrokenImage"
                        />
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
