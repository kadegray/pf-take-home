<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { formatMicros } from '@/money';
import { Head, router, usePage } from '@inertiajs/vue3';

defineProps({
    presetAmounts: { type: Array, required: true },
});

const page = usePage();

function buy(amountMicros) {
    router.post(route('credits.purchase'), { amount_micros: amountMicros });
}
</script>

<template>
    <Head title="Buy Credits" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Credits</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 text-center shadow-sm">
                    <p class="text-sm text-gray-500">Current balance</p>
                    <p class="text-3xl font-bold">{{ formatMicros(page.props.auth.user.balance_micros) }}</p>
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="mb-4 text-sm text-gray-500">
                        Buy credits (this is a fake purchase - no real payment is made).
                    </p>
                    <div class="grid grid-cols-3 gap-4">
                        <PrimaryButton
                            v-for="amount in presetAmounts"
                            :key="amount"
                            class="justify-center"
                            @click="buy(amount)"
                        >
                            {{ formatMicros(amount) }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
