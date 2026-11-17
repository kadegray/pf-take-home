<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    sizes: { type: Array, required: true },
    qualities: { type: Array, required: true },
    maxReferenceImages: { type: Number, required: true },
});

const page = usePage();
const lastGeneration = computed(() => page.props.flash?.generation ?? null);

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

function formatMicros(micros) {
    if (micros === null || micros === undefined) {
        return '—';
    }

    return '$' + (micros / 1_000_000).toFixed(4);
}

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

function submit() {
    form.reference_images = referenceImageInputs.value.filter((url) => url.trim() !== '');

    form.post(route('generate.store'), {
        onSuccess: () => {
            form.reset('prompt');
            referenceImageInputs.value = Array.from({ length: props.maxReferenceImages }, () => '');
        },
    });
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
                <div
                    v-if="lastGeneration"
                    class="rounded-lg border p-4"
                    :class="{
                        'border-green-300 bg-green-50': lastGeneration.status !== 'failed',
                        'border-red-300 bg-red-50': lastGeneration.status === 'failed',
                    }"
                >
                    <p class="font-medium">
                        Submitted — status: <span class="font-mono">{{ lastGeneration.status }}</span>
                    </p>
                    <p class="text-sm text-gray-600">
                        Estimated cost: {{ formatMicros(lastGeneration.estimated_cost_micros) }}
                    </p>
                    <p v-if="lastGeneration.error_message" class="text-sm text-red-700">
                        {{ lastGeneration.error_message }}
                    </p>
                </div>

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
                            </div>

                            <PrimaryButton :disabled="form.processing">Generate</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
