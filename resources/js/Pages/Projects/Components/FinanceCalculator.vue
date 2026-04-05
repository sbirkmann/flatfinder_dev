<template>
    <div v-if="settings && settings.active && hasPrice" class="mt-6">
        <!-- Kaufpreis-Rechner -->
        <div v-if="!isRental" class="bg-gradient-to-br from-[#fcfaf9] to-[#f5f0ec] rounded-[16px] p-5 border border-[#e8ddd5] shadow-[0_4px_20px_rgba(171,113,92,0.08)]">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[13px] font-black text-gray-800 uppercase tracking-widest flex items-center gap-2">
                    <span class="w-8 h-8 rounded-full bg-[#ab715c]/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2a3.001 3.001 0 01-3-2M12 6v2m0 8v2" /></svg>
                    </span>
                    Finanzierung
                </h3>
                <button @click="isExpanded = !isExpanded" class="w-7 h-7 rounded-full bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-50 transition text-gray-400">
                    <svg :class="['w-4 h-4 transition-transform', isExpanded ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </button>
            </div>

            <!-- Collapsed: Monthly Rate Preview -->
            <div v-if="!isExpanded" class="flex items-center justify-between bg-white rounded-[12px] px-4 py-3 border border-gray-100 cursor-pointer hover:border-[#ab715c]/30 transition" @click="isExpanded = true">
                <div class="flex flex-col">
                    <span class="text-[10px] font-bold text-gray-400 uppercase">Geschätzte Rate</span>
                    <span class="text-[22px] font-black text-[#ab715c] tracking-tight">{{ formatPrice(monthlyRate) }} €<span class="text-[12px] font-semibold text-gray-400"> / Monat</span></span>
                </div>
                <div class="text-[10px] text-gray-400 font-semibold text-right">
                    {{ downPaymentPercent }}% EK<br>
                    {{ interestRate }}% Zins
                </div>
            </div>

            <!-- Expanded -->
            <div v-if="isExpanded" class="space-y-4">
                <!-- Kostenübersicht -->
                <div class="bg-white rounded-[12px] p-4 border border-gray-100 space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-[12px] font-semibold text-gray-500">Kaufpreis</span>
                        <span class="text-[13px] font-bold text-gray-800">{{ formatPrice(price) }} €</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[12px] font-semibold text-gray-500">
                            Kaufnebenkosten
                            <span class="text-[10px] text-gray-400">(~{{ (additionalCostPercent * 100).toFixed(0) }}%)</span>
                        </span>
                        <span class="text-[13px] font-bold text-gray-600">+ {{ formatPrice(additionalCosts) }} €</span>
                    </div>
                    <div class="border-t border-gray-100 pt-2 flex justify-between items-center">
                        <span class="text-[12px] font-bold text-gray-700">Gesamtkosten</span>
                        <span class="text-[15px] font-black text-gray-900">{{ formatPrice(totalCost) }} €</span>
                    </div>
                </div>

                <!-- Nebenkosten-Slider -->
                <div class="bg-white rounded-[12px] p-4 border border-gray-100">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase">Kaufnebenkosten</span>
                        <span class="text-[12px] font-bold text-[#ab715c]">{{ (additionalCostPercent * 100).toFixed(1) }}%</span>
                    </div>
                    <input type="range" v-model.number="additionalCostPercentSlider" min="5" max="18" step="0.5" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#ab715c]" />
                    <div class="flex justify-between text-[9px] text-gray-300 mt-1">
                        <span>5%</span>
                        <span class="text-[10px] text-gray-400">Grunderwerbsteuer • Notar • Makler</span>
                        <span>18%</span>
                    </div>
                </div>

                <!-- Eigenkapital -->
                <div class="bg-white rounded-[12px] p-4 border border-gray-100">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[11px] font-bold text-gray-500 uppercase">Eigenkapital</span>
                        <span class="text-[12px] font-bold text-[#ab715c]">{{ formatPrice(downPayment) }} € <span class="text-gray-400">({{ downPaymentPercent }}%)</span></span>
                    </div>
                    <input type="range" v-model.number="downPaymentPercent" min="0" max="100" step="1" class="w-full h-1.5 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-[#ab715c]" />
                    <div class="flex justify-between text-[9px] text-gray-300 mt-1">
                        <span>0%</span>
                        <span>100%</span>
                    </div>
                </div>

                <!-- Zinssatz & Tilgung -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white rounded-[12px] p-3 border border-gray-100">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5">Sollzins p.a.</label>
                        <div class="flex items-center gap-1">
                            <input type="number" step="0.1" min="0" max="15" v-model.number="interestRate" class="w-full text-[14px] font-bold text-gray-800 border-0 border-b border-gray-200 focus:border-[#ab715c] focus:ring-0 px-0 py-1 bg-transparent" />
                            <span class="text-[12px] font-bold text-gray-400 shrink-0">%</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-[12px] p-3 border border-gray-100">
                        <label class="text-[10px] font-bold text-gray-400 uppercase block mb-1.5">Tilgung p.a.</label>
                        <div class="flex items-center gap-1">
                            <input type="number" step="0.1" min="0.5" max="15" v-model.number="repaymentRate" class="w-full text-[14px] font-bold text-gray-800 border-0 border-b border-gray-200 focus:border-[#ab715c] focus:ring-0 px-0 py-1 bg-transparent" />
                            <span class="text-[12px] font-bold text-gray-400 shrink-0">%</span>
                        </div>
                    </div>
                </div>

                <!-- Ergebnis-Box -->
                <div class="bg-gradient-to-br from-[#ab715c] to-[#96624f] rounded-[14px] p-5 text-center relative overflow-hidden">
                    <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.03\'%3E%3Cpath d=\'M0 40L40 0H20L0 20V40zM40 40V20L20 40H40z\'/%3E%3C/g%3E%3C/svg%3E')] opacity-50"></div>
                    <div class="relative z-10">
                        <div class="text-[10px] font-bold text-white/60 uppercase tracking-widest mb-1">Geschätzte monatliche Rate</div>
                        <div class="text-[34px] font-black text-white tracking-tight leading-none mb-1">{{ formatPrice(monthlyRate) }} €</div>
                        <div class="text-[11px] text-white/50 font-medium mt-2">Darlehenssumme: {{ formatPrice(loanAmount) }} €</div>
                        <div class="flex justify-center gap-4 mt-3">
                            <div class="text-center">
                                <div class="text-[16px] font-black text-white">{{ formatPrice(monthlyInterest) }} €</div>
                                <div class="text-[9px] text-white/50 uppercase font-bold">Zinsanteil</div>
                            </div>
                            <div class="w-px bg-white/20"></div>
                            <div class="text-center">
                                <div class="text-[16px] font-black text-white">{{ formatPrice(monthlyRepayment) }} €</div>
                                <div class="text-[9px] text-white/50 uppercase font-bold">Tilgungsanteil</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tilgungsplan Mini-Übersicht -->
                <div class="bg-white rounded-[12px] p-4 border border-gray-100">
                    <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Tilgungsvorschau</h4>
                    <div class="space-y-2">
                        <div v-for="year in repaymentPreview" :key="year.year" class="flex items-center gap-3">
                            <span class="text-[11px] font-bold text-gray-400 w-12 shrink-0">{{ year.year }}. Jahr</span>
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-[#ab715c] to-[#c89b86] rounded-full transition-all" :style="{ width: year.paidPercent + '%' }"></div>
                            </div>
                            <span class="text-[10px] font-bold text-gray-500 w-16 text-right">{{ formatPrice(year.remainingDebt) }} €</span>
                        </div>
                    </div>
                </div>

                <p class="text-[9px] text-gray-300 text-center leading-relaxed">
                    Unverbindliche Beispielrechnung. Für ein konkretes Angebot kontaktieren Sie Ihre Bank.
                </p>
            </div>
        </div>

        <!-- Mietkosten-Rechner -->
        <div v-if="isRental" class="bg-gradient-to-br from-[#fcfaf9] to-[#f5f0ec] rounded-[16px] p-5 border border-[#e8ddd5] shadow-[0_4px_20px_rgba(171,113,92,0.08)]">
            <h3 class="text-[13px] font-black text-gray-800 uppercase tracking-widest flex items-center gap-2 mb-5">
                <span class="w-8 h-8 rounded-full bg-[#ab715c]/10 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                </span>
                Mietkostenübersicht
            </h3>
            
            <div class="space-y-3">
                <!-- Kaltmiete -->
                <div class="bg-white rounded-[12px] px-4 py-3 border border-gray-100 flex justify-between items-center">
                    <span class="text-[12px] font-semibold text-gray-500">Kaltmiete</span>
                    <span class="text-[14px] font-black text-gray-800">{{ apartment.price ? formatPrice(apartment.price) + ' €' : '–' }}</span>
                </div>
                <!-- NK -->
                <div class="bg-white rounded-[12px] px-4 py-3 border border-gray-100 flex justify-between items-center">
                    <span class="text-[12px] font-semibold text-gray-500">Nebenkosten-Akonto</span>
                    <span class="text-[14px] font-bold text-gray-600">{{ apartment.additional_costs ? formatPrice(apartment.additional_costs) + ' €' : '–' }}</span>
                </div>
                <!-- Bruttomiete -->
                <div class="bg-gradient-to-br from-[#ab715c] to-[#96624f] rounded-[14px] px-4 py-4 flex justify-between items-center">
                    <span class="text-[12px] font-bold text-white/70">Bruttomiete</span>
                    <span class="text-[20px] font-black text-white">{{ apartment.warm_rent ? formatPrice(apartment.warm_rent) + ' €' : '–' }}</span>
                </div>
                <!-- Preis pro m² -->
                <div class="bg-white rounded-[12px] px-4 py-3 border border-gray-100 flex justify-between items-center" v-if="pricePerSqm">
                    <span class="text-[12px] font-semibold text-gray-500">Preis pro m²</span>
                    <span class="text-[13px] font-bold text-gray-700">{{ formatPrice(pricePerSqm) }} € / m²</span>
                </div>
                <!-- Einkommensbedarf -->
                <div class="bg-[#fcfaf9] rounded-[12px] p-4 border border-gray-100">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 text-[#ab715c]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                        <span class="text-[11px] font-bold text-gray-600 uppercase">Empfohlenes Haushaltseinkommen</span>
                    </div>
                    <div class="text-[20px] font-black text-[#ab715c]">{{ formatPrice(recommendedIncome) }} € <span class="text-[11px] font-semibold text-gray-400">netto / Monat</span></div>
                    <p class="text-[9px] text-gray-400 mt-1">Basierend auf der 30%-Faustregel: Maximal 30% des Nettoeinkommens für die Warmmiete.</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    apartment: Object,
    settings: Object
});

const isExpanded = ref(false);

const hasPrice = computed(() => {
    if (!props.apartment) return false;
    if (props.apartment.marketing_type === 'Miete') {
        return (props.apartment.warm_rent && props.apartment.warm_rent > 0) || (props.apartment.price && props.apartment.price > 0);
    }
    return props.apartment.price && props.apartment.price > 0;
});

const isRental = computed(() => props.apartment?.marketing_type === 'Miete');

const price = computed(() => parseFloat(props.apartment?.price) || 0);

// --- Kaufrechner ---
const additionalCostPercentSlider = ref(10);
const additionalCostPercent = computed(() => additionalCostPercentSlider.value / 100);
const additionalCosts = computed(() => price.value * additionalCostPercent.value);
const totalCost = computed(() => price.value + additionalCosts.value);

const downPaymentPercent = ref(20);
const downPayment = computed(() => totalCost.value * (downPaymentPercent.value / 100));
const loanAmount = computed(() => Math.max(0, totalCost.value - downPayment.value));

const interestRate = ref(parseFloat(props.settings?.interest_rate) || 3.5);
const repaymentRate = ref(parseFloat(props.settings?.repayment) || 2.0);

const monthlyRate = computed(() => {
    if (loanAmount.value <= 0) return 0;
    const annualRate = (interestRate.value + repaymentRate.value) / 100;
    return (loanAmount.value * annualRate) / 12;
});

const monthlyInterest = computed(() => {
    if (loanAmount.value <= 0) return 0;
    return (loanAmount.value * interestRate.value / 100) / 12;
});

const monthlyRepayment = computed(() => {
    return monthlyRate.value - monthlyInterest.value;
});

// Tilgungsplan: Schnappschuss nach 5, 10, 15, 20, 25 Jahren
const repaymentPreview = computed(() => {
    const years = [5, 10, 15, 20, 25];
    const result = [];
    let remaining = loanAmount.value;
    const annualRepaymentAmount = remaining * (repaymentRate.value / 100);
    
    for (const y of years) {
        if (remaining <= 0) break;
        // Simplified: remaining debt decreases by annual repayment (ignoring compounding for simplicity in preview)
        remaining = Math.max(0, loanAmount.value - (annualRepaymentAmount * y));
        const paidPercent = loanAmount.value > 0 ? Math.min(100, ((loanAmount.value - remaining) / loanAmount.value) * 100) : 0;
        result.push({ year: y, remainingDebt: remaining, paidPercent });
    }
    return result;
});

// --- Mietrechner ---
const pricePerSqm = computed(() => {
    if (!props.apartment?.price || !props.apartment?.sqm) return null;
    return props.apartment.price / props.apartment.sqm;
});

const recommendedIncome = computed(() => {
    const warmRent = parseFloat(props.apartment?.warm_rent) || parseFloat(props.apartment?.price) || 0;
    if (warmRent <= 0) return 0;
    return warmRent / 0.3; // 30% Regel
});

const formatPrice = (value) => {
    return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(value);
};
</script>
