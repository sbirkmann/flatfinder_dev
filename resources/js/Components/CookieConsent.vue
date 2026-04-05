<script setup>
import { ref, onMounted } from 'vue';

const CONSENT_KEY = 'cookie_consent_v1';
const show = ref(false);
const consentGiven = ref(false);

onMounted(() => {
    const stored = localStorage.getItem(CONSENT_KEY);
    if (!stored) {
        show.value = true;
    } else {
        consentGiven.value = true;
    }
});

const emit = defineEmits(['consent']);

const accept = () => {
    localStorage.setItem(CONSENT_KEY, JSON.stringify({ tracking: true, analytics: true, date: new Date().toISOString() }));
    show.value = false;
    consentGiven.value = true;
    emit('consent', true);
};

const decline = () => {
    localStorage.setItem(CONSENT_KEY, JSON.stringify({ tracking: false, analytics: false, date: new Date().toISOString() }));
    show.value = false;
    consentGiven.value = false;
    emit('consent', false);
};

const hasConsent = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(CONSENT_KEY));
        return stored?.tracking === true;
    } catch {
        return false;
    }
};

defineExpose({ hasConsent, consentGiven });
</script>

<template>
    <Transition
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-300 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div v-if="show" class="fixed bottom-0 left-0 right-0 z-[9999] p-4 sm:p-6">
            <div class="max-w-3xl mx-auto bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-200 p-6 sm:flex items-start gap-6">
                <div class="flex-1 mb-4 sm:mb-0">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">🍪 Cookie-Einstellungen</h3>
                    <p class="text-xs text-gray-600 leading-relaxed">
                        Wir verwenden Cookies und ähnliche Technologien, um Ihr Nutzungserlebnis zu verbessern und anonyme Besucherstatistiken zu erheben. 
                        Mit „Akzeptieren" stimmen Sie der Verwendung von Tracking-Cookies zu. 
                        Sie können Ihre Einwilligung jederzeit widerrufen.
                    </p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button 
                        @click="decline" 
                        class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition border border-gray-200"
                    >
                        Nur notwendige
                    </button>
                    <button 
                        @click="accept" 
                        class="px-5 py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl transition shadow-lg shadow-indigo-500/20"
                    >
                        Akzeptieren
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
