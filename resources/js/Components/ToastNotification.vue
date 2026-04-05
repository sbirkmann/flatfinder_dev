<script setup>
import { ref, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const show = ref(false);
const message = ref('');
const type = ref('success'); // success | error

const page = usePage();

const showToast = (msg, t = 'success') => {
    message.value = msg;
    type.value = t;
    show.value = true;
    setTimeout(() => { show.value = false; }, 4000);
};

watch(() => page.props.flash, (flash) => {
    if (flash?.success) showToast(flash.success, 'success');
    if (flash?.error) showToast(flash.error, 'error');
}, { immediate: true, deep: true });

defineExpose({ showToast });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-from-class="translate-y-4 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in transform"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-4 opacity-0"
        >
            <div
                v-if="show"
                class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 px-5 py-3.5 rounded-xl shadow-2xl border max-w-md"
                :class="type === 'success' 
                    ? 'bg-emerald-600 border-emerald-500 text-white' 
                    : 'bg-red-600 border-red-500 text-white'"
            >
                <CheckCircleIcon v-if="type === 'success'" class="w-5 h-5 shrink-0" />
                <ExclamationTriangleIcon v-else class="w-5 h-5 shrink-0" />
                <span class="text-sm font-medium flex-1">{{ message }}</span>
                <button @click="show = false" class="text-white/70 hover:text-white">
                    <XMarkIcon class="w-4 h-4" />
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
