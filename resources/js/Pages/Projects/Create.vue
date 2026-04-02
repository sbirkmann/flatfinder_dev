<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    address: '',
    zip: '',
    city: '',
    description: '',
    project_image: null,
    preview_image: null,
});

const submit = () => {
    form.post(route('projects.store'));
};
</script>

<template>
    <AppLayout title="Neues Projekt anlegen">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Neues Projekt anlegen
            </h2>
        </template>

        <div class="py-12">
            <div class="w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 max-w-3xl mx-auto">
                    <form @submit.prevent="submit">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Projektname</label>
                            <input v-model="form.name" type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                            <div v-if="form.errors.name" class="text-red-500 text-xs mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Adresse</label>
                            <input v-model="form.address" type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>

                        <div class="flex gap-4 mb-4">
                            <div class="w-1/3">
                                <label class="block text-gray-700 text-sm font-bold mb-2">PLZ</label>
                                <input v-model="form.zip" type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div class="w-2/3">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Stadt</label>
                                <input v-model="form.city" type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Beschreibung</label>
                            <textarea v-model="form.description" rows="4" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                        </div>

                        <h3 class="text-lg font-bold mt-8 mb-4 border-b pb-2">Bilder (Optional)</h3>
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Projektbild (Main)</label>
                            <input type="file" @change="e => form.project_image = e.target.files[0]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*">
                        </div>

                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Preview Image</label>
                            <input type="file" @change="e => form.preview_image = e.target.files[0]" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept="image/*">
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" :disabled="form.processing" class="bg-brand-500 hover:bg-brand-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" :class="{ 'opacity-50': form.processing }">
                                Projekt anlegen
                            </button>
                            <Link :href="route('projects.index')" class="inline-block align-baseline font-bold text-sm text-brand-500 hover:text-brand-800">
                                Abbrechen
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
