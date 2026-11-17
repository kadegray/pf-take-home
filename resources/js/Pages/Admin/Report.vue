<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatMicros } from '@/money';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    startDate: { type: String, required: true },
    endDate: { type: String, required: true },
});

const startDate = ref(props.startDate);
const endDate = ref(props.endDate);

function applyRange() {
    router.get(
        route('admin.report'),
        { start_date: startDate.value, end_date: endDate.value },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Admin Report" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Credit spend &amp; provider cost</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div class="flex items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Start date</label>
                        <input v-model="startDate" type="date" class="mt-1 rounded-md border-gray-300 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">End date</label>
                        <input v-model="endDate" type="date" class="mt-1 rounded-md border-gray-300 text-sm" />
                    </div>
                    <button
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                        @click="applyRange"
                    >
                        Apply
                    </button>
                    <p class="ml-auto text-xs text-gray-400">
                        UTC, inclusive, matches Pixelforge's GET /v1/usage query semantics.
                    </p>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs text-gray-500">Generations</p>
                        <p class="text-lg font-semibold">{{ totals.generation_count }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs text-gray-500">Credit spend</p>
                        <p class="text-lg font-semibold">{{ formatMicros(totals.spend_micros) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs text-gray-500">Provider cost</p>
                        <p class="text-lg font-semibold">{{ formatMicros(totals.provider_cost_micros) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <p class="text-xs text-gray-500">Profit</p>
                        <p class="text-lg font-semibold">{{ formatMicros(totals.profit_micros) }}</p>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Day (UTC)</th>
                                <th class="px-4 py-3">User</th>
                                <th class="px-4 py-3">Generations</th>
                                <th class="px-4 py-3">Spend</th>
                                <th class="px-4 py-3">Provider cost</th>
                                <th class="px-4 py-3">Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in rows" :key="`${row.day}-${row.user_id}`">
                                <td class="px-4 py-3 font-mono text-xs">{{ row.day }}</td>
                                <td class="px-4 py-3">
                                    <div>{{ row.user_name }}</div>
                                    <div class="text-xs text-gray-400">{{ row.user_email }}</div>
                                </td>
                                <td class="px-4 py-3">{{ row.generation_count }}</td>
                                <td class="px-4 py-3">{{ formatMicros(row.spend_micros) }}</td>
                                <td class="px-4 py-3">{{ formatMicros(row.provider_cost_micros) }}</td>
                                <td class="px-4 py-3">{{ formatMicros(row.profit_micros) }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    No succeeded generations in this range.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
