<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMicros } from '@/money';
import { statusClasses } from '@/statusBadge';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    generation: { type: Object, required: true },
});

const imageBroken = ref(false);
</script>

<template>
    <Head title="Generation" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Generation</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <Link :href="route('history.index')" class="text-sm text-indigo-600 hover:underline">
                    « Back to history
                </Link>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="flex items-center justify-center bg-gray-100 p-4">
                        <img
                            v-if="generation.image_url && !imageBroken"
                            :src="generation.image_url"
                            alt=""
                            class="max-h-[32rem] w-full rounded object-contain"
                            @error="imageBroken = true"
                        />
                        <div v-else class="flex h-64 w-full items-center justify-center text-gray-400">
                            {{ generation.image_url ? 'Image unavailable (the link may have expired).' : 'No image yet.' }}
                        </div>
                    </div>

                    <div class="space-y-4 border-t border-gray-100 p-6">
                        <div>
                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="statusClasses[generation.status]"
                            >
                                {{ generation.status }}
                            </span>
                            <p
                                v-if="generation.status === 'failed' && generation.error_message"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ generation.error_message }}
                            </p>
                        </div>

                        <div>
                            <h3 class="text-xs font-medium uppercase text-gray-500">Prompt</h3>
                            <p class="mt-1 text-sm text-gray-900">{{ generation.prompt }}</p>
                        </div>

                        <dl class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                            <div>
                                <dt class="text-xs font-medium uppercase text-gray-500">Size</dt>
                                <dd class="mt-1 text-gray-900">{{ generation.size }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-gray-500">Quality</dt>
                                <dd class="mt-1 text-gray-900">{{ generation.quality }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-gray-500">Estimated</dt>
                                <dd class="mt-1 text-gray-900">{{ formatMicros(generation.estimated_cost_micros) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-gray-500">Actual</dt>
                                <dd class="mt-1 text-gray-900">
                                    {{
                                        ['succeeded', 'failed'].includes(generation.status)
                                            ? formatMicros(generation.actual_cost_micros)
                                            : '—'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
