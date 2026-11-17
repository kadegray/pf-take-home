<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);

const form = useForm({});

const toggleAdmin = () => {
    form.patch(route('profile.toggle-admin'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Admin Access
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                You are currently
                <span class="font-semibold">{{ user.is_admin ? 'an admin' : 'not an admin' }}</span>.
                Toggling this grants or revokes access to the admin report.
            </p>
        </header>

        <SecondaryButton class="mt-6" :disabled="form.processing" @click="toggleAdmin">
            {{ user.is_admin ? 'Revoke admin access' : 'Grant myself admin access' }}
        </SecondaryButton>
    </section>
</template>
