<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    integrations: Array
});

// Helper to initialize forms safely per integration
const getForms = () => {
    let forms = {};
    props.integrations.forEach(integration => {
        let defaultCreds = {};
        if (integration.platform_name === 'onoffice') defaultCreds = { token: '', secret: '' };
        if (integration.platform_name === 'flowfact') defaultCreds = { client_id: '', api_key: '' };
        if (integration.platform_name === 'propstack') defaultCreds = { api_key: '' };
        
        forms[integration.id] = useForm({
            is_active: integration.is_active,
            credentials: integration.credentials || defaultCreds
        });
    });
    return forms;
};

const forms = getForms();

const submit = (integrationId) => {
    forms[integrationId].put(route('integrations.update', integrationId), {
        preserveScroll: true,
    });
};

const getTitle = (name) => {
    const map = {
        'onoffice': 'onOffice',
        'flowfact': 'FlowFact',
        'propstack': 'Propstack'
    };
    return map[name] || name;
}
</script>

<template>
    <AppLayout title="Anbindungen">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Anbindungen (CRM)
            </h2>
        </template>

        <div class="py-12 bg-gray-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Cards per Platform -->
                    <div v-for="integration in integrations" :key="integration.id" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                            <h3 class="text-lg font-bold text-gray-900">{{ getTitle(integration.platform_name) }}</h3>
                            <div class="flex items-center">
                                <label class="flex items-center cursor-pointer">
                                    <div class="relative">
                                        <input type="checkbox" v-model="forms[integration.id].is_active" class="sr-only" />
                                        <div class="block bg-gray-200 w-10 h-6 rounded-full transition-colors" :class="{'bg-emerald-500': forms[integration.id].is_active}"></div>
                                        <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" :class="{'translate-x-4': forms[integration.id].is_active}"></div>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-700">Aktiv</span>
                                </label>
                            </div>
                        </div>

                        <form @submit.prevent="submit(integration.id)" class="p-6 flex-1 flex flex-col">
                            <div class="flex-1 space-y-4">
                                <!-- Dynamic fields based on platform -->
                                <template v-if="integration.platform_name === 'onoffice'">
                                    <div>
                                        <InputLabel value="API Token" />
                                        <TextInput v-model="forms[integration.id].credentials.token" type="text" class="mt-1 block w-full" placeholder="Token" />
                                    </div>
                                    <div>
                                        <InputLabel value="API Secret" />
                                        <TextInput v-model="forms[integration.id].credentials.secret" type="password" class="mt-1 block w-full" placeholder="Secret" />
                                    </div>
                                </template>

                                <template v-if="integration.platform_name === 'flowfact'">
                                    <div>
                                        <InputLabel value="Client ID" />
                                        <TextInput v-model="forms[integration.id].credentials.client_id" type="text" class="mt-1 block w-full" placeholder="Client ID" />
                                    </div>
                                    <div>
                                        <InputLabel value="API Key" />
                                        <TextInput v-model="forms[integration.id].credentials.api_key" type="password" class="mt-1 block w-full" placeholder="API Key" />
                                    </div>
                                </template>

                                <template v-if="integration.platform_name === 'propstack'">
                                    <div>
                                        <InputLabel value="API Key" />
                                        <TextInput v-model="forms[integration.id].credentials.api_key" type="password" class="mt-1 block w-full" placeholder="API Key" />
                                    </div>
                                </template>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <PrimaryButton :class="{ 'opacity-25': forms[integration.id].processing }" :disabled="forms[integration.id].processing">
                                    Speichern
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.dot {
    transition: transform 0.2s ease-in-out;
}
</style>
