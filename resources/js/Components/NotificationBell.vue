<script setup>
import { ref, onMounted } from 'vue';
import { BellIcon, CheckIcon } from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    initialCount: { type: Number, default: 0 },
});

const isOpen = ref(false);
const notifications = ref([]);
const unreadCount = ref(props.initialCount);
const isLoading = ref(false);

const typeIcons = {
    'inquiry_received': '📩',
    'apartment_status_changed': '🏠',
    'info': 'ℹ️',
};

const fetchNotifications = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/notifications');
        notifications.value = response.data.notifications || [];
        unreadCount.value = response.data.unread_count || 0;
    } catch (e) {
        console.error('Failed to fetch notifications', e);
    } finally {
        isLoading.value = false;
    }
};

const toggle = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) fetchNotifications();
};

const markAllRead = async () => {
    try {
        await axios.post('/notifications/mark-all-read');
        unreadCount.value = 0;
        notifications.value.forEach(n => n.read_at = 'just now');
    } catch (e) {
        console.error(e);
    }
};

const closePanel = () => {
    isOpen.value = false;
};

// Poll for new notifications every 30s
let pollInterval;
onMounted(() => {
    pollInterval = setInterval(async () => {
        try {
            const r = await axios.get('/notifications');
            unreadCount.value = r.data.unread_count || 0;
        } catch (_) {}
    }, 30000);
});
</script>

<template>
    <div class="relative">
        <button @click="toggle" class="relative p-2 text-gray-400 hover:text-white rounded-lg hover:bg-gray-700/50 transition">
            <BellIcon class="w-5 h-5" />
            <span v-if="unreadCount > 0" class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold min-w-[18px] h-[18px] flex items-center justify-center rounded-full px-1 shadow-lg animate-bounce">
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown Panel -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-1 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-1 scale-95"
        >
            <div v-if="isOpen" class="absolute right-0 mt-2 w-96 bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden z-50">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-bold text-gray-800">Benachrichtigungen</h3>
                    <button v-if="unreadCount > 0" @click="markAllRead" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center gap-1">
                        <CheckIcon class="w-3.5 h-3.5" /> Alle gelesen
                    </button>
                </div>

                <!-- Loading -->
                <div v-if="isLoading" class="p-6 text-center text-gray-400 text-sm">
                    <svg class="animate-spin h-5 w-5 mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </div>

                <!-- Notification List -->
                <div v-else-if="notifications.length" class="max-h-[400px] overflow-y-auto divide-y divide-gray-100">
                    <div
                        v-for="n in notifications"
                        :key="n.id"
                        class="px-4 py-3 hover:bg-gray-50 transition cursor-pointer"
                        :class="!n.read_at ? 'bg-indigo-50/50' : ''"
                    >
                        <div class="flex items-start gap-3">
                            <span class="text-lg mt-0.5">{{ typeIcons[n.type] || '🔔' }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate" :class="!n.read_at ? 'text-indigo-900' : ''">{{ n.title }}</p>
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ n.body }}</p>
                                <p class="text-[10px] text-gray-400 mt-1">{{ n.created_at }}</p>
                            </div>
                            <span v-if="!n.read_at" class="w-2 h-2 bg-indigo-500 rounded-full shrink-0 mt-2"></span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="p-8 text-center text-gray-400 text-sm">
                    <BellIcon class="w-8 h-8 mx-auto mb-2 text-gray-300" />
                    Keine Benachrichtigungen
                </div>
            </div>
        </Transition>

        <!-- Click-outside overlay -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="closePanel"></div>
    </div>
</template>
