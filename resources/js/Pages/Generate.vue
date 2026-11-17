<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { formatMicros } from '@/money';

const page = usePage();
const balanceMicros = computed(() => page.props.auth.user.balance_micros);

const props = defineProps({
    sizes: { type: Array, required: true },
    qualities: { type: Array, required: true },
    maxReferenceImages: { type: Number, required: true },
});

const form = useForm({
    prompt: '',
    size: props.sizes[0],
    quality: props.qualities[0],
    reference_images: [],
});

const referenceImageInputs = ref(Array.from({ length: props.maxReferenceImages }, () => ''));

const estimatedCostMicros = ref(null);
const estimating = ref(false);
let estimateTimer = null;

function scheduleEstimate() {
    clearTimeout(estimateTimer);
    estimateTimer = setTimeout(fetchEstimate, 350);
}

async function fetchEstimate() {
    estimating.value = true;

    try {
        const response = await window.axios.post(route('generate.estimate'), {
            prompt: form.prompt,
            size: form.size,
            quality: form.quality,
            reference_images: referenceImageInputs.value,
        });

        estimatedCostMicros.value = response.data.estimated_cost_micros;
    } catch (error) {
        // Estimate is a convenience preview; a transient failure here
        // shouldn't block the user from submitting the real request.
        estimatedCostMicros.value = null;
    } finally {
        estimating.value = false;
    }
}

watch(
    [() => form.prompt, () => form.size, () => form.quality, referenceImageInputs],
    scheduleEstimate,
    { deep: true, immediate: true },
);

const canAfford = computed(() => {
    return estimatedCostMicros.value === null || estimatedCostMicros.value <= balanceMicros.value;
});

function submit() {
    form.reference_images = referenceImageInputs.value.filter((url) => url.trim() !== '');

    // On success the backend redirects to History, so there's nothing to
    // reset here - the component unmounts. On failure (e.g. a rejected
    // prompt), the fields intentionally stay as the user left them.
    form.post(route('generate.store'));
}
</script>

<template>
    <Head title="Generate" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Generate an image</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <form class="space-y-6" @submit.prevent="submit">
                        <div>
                            <InputLabel for="prompt" value="Prompt" />
                            <textarea
                                id="prompt"
                                v-model="form.prompt"
                                rows="4"
                                maxlength="4000"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="A watercolor fox reading a book"
                            />
                            <InputError class="mt-2" :message="form.errors.prompt" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="size" value="Size" />
                                <select
                                    id="size"
                                    v-model="form.size"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="size in sizes" :key="size" :value="size">{{ size }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.size" />
                            </div>

                            <div>
                                <InputLabel for="quality" value="Quality" />
                                <select
                                    id="quality"
                                    v-model="form.quality"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option v-for="quality in qualities" :key="quality" :value="quality">
                                        {{ quality }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.quality" />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Reference images (optional URLs)" />
                            <div class="mt-1 space-y-2">
                                <TextInput
                                    v-for="(_, index) in referenceImageInputs"
                                    :key="index"
                                    v-model="referenceImageInputs[index]"
                                    type="url"
                                    class="block w-full"
                                    :placeholder="`https://example.com/reference-${index + 1}.png`"
                                />
                            </div>
                            <InputError class="mt-2" :message="form.errors.reference_images" />
                        </div>

                        <div class="flex items-center justify-between rounded-md bg-gray-50 p-4">
                            <div>
                                <p class="text-sm text-gray-500">Estimated cost (markup included)</p>
                                <p class="text-lg font-semibold">
                                    {{ estimating ? 'Calculating…' : formatMicros(estimatedCostMicros) }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    Estimate only — the final cost is set once the generation completes.
                                </p>
                                <p v-if="!canAfford" class="text-xs font-medium text-red-600">
                                    This exceeds your balance ({{ formatMicros(balanceMicros) }}).
                                    <Link :href="route('credits.create')" class="underline">Buy more credits</Link>.
                                </p>
                            </div>

                            <PrimaryButton :disabled="form.processing || !canAfford">Generate</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
