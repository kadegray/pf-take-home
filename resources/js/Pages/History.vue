<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMicros } from '@/money';
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive } from 'vue';

const props = defineProps({
    generations: { type: Object, required: true },
});

const rows = reactive(props.generations.data.map((generation) => ({ ...generation })));

const TERMINAL_STATUSES = ['succeeded', 'failed'];

function isTerminal(status) {
    return TERMINAL_STATUSES.includes(status);
}

function hasNonTerminalRows() {
    return rows.some((row) => !isTerminal(row.status));
}

let pollTimer = null;

async function poll() {
    try {
        const response = await window.axios.get(route('history.poll'));

        for (const update of response.data.generations) {
            const row = rows.find((r) => r.id === update.id);

            if (row) {
                Object.assign(row, update);
            }
        }
    } catch (error) {
        // Transient poll failure - just try again on the next tick.
    }

    if (!hasNonTerminalRows() && pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

onMounted(() => {
    if (hasNonTerminalRows()) {
        pollTimer = setInterval(poll, 4000);
    }
});

onUnmounted(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});

const statusClasses = {
    queued: 'bg-gray-100 text-gray-700',
    processing: 'bg-blue-100 text-blue-700',
    succeeded: 'bg-green-100 text-green-700',
    failed: 'bg-red-100 text-red-700',
};

function hideBrokenImage(event) {
    event.target.style.visibility = 'hidden';
}
</script>

<template>
    <Head title="History" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Your generations</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Prompt</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Estimated</th>
                                <th class="px-4 py-3">Actual</th>
                                <th class="px-4 py-3">Image</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in rows" :key="row.id">
                                <td class="max-w-xs truncate px-4 py-3" :title="row.prompt">{{ row.prompt }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-xs font-medium"
                                        :class="statusClasses[row.status]"
                                    >
                                        {{ row.status }}
                                    </span>
                                    <p
                                        v-if="row.status === 'failed' && row.error_message"
                                        class="mt-1 max-w-xs text-xs text-red-600"
                                    >
                                        {{ row.error_message }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">{{ formatMicros(row.estimated_cost_micros) }}</td>
                                <td class="px-4 py-3">
                                    {{ isTerminal(row.status) ? formatMicros(row.actual_cost_micros) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <img
                                        v-if="row.image_url"
                                        :src="row.image_url"
                                        alt=""
                                        class="h-12 w-12 rounded object-cover"
                                        @error="hideBrokenImage"
                                    />
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No generations yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between text-sm">
                    <Link
                        v-if="generations.prev_page_url"
                        :href="generations.prev_page_url"
                        class="text-indigo-600 hover:underline"
                    >
                        « Previous
                    </Link>
                    <span v-else />
                    <Link
                        v-if="generations.next_page_url"
                        :href="generations.next_page_url"
                        class="text-indigo-600 hover:underline"
                    >
                        Next »
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
