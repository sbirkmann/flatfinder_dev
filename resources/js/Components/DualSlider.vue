<template>
  <div class="relative w-full flex items-center h-1 bg-gray-200 rounded-full mt-4 mb-2 select-none">
    <div class="absolute h-full bg-[#ab715c] rounded-full pointer-events-none" :style="{ left: minPercent + '%', right: (100 - maxPercent) + '%' }"></div>
    
    <input type="range" :min="min" :max="max" :step="step" v-model.number="localMin" class="multi-range absolute w-full" @input="handleMinInput" />
    <input type="range" :min="min" :max="max" :step="step" v-model.number="localMax" class="multi-range absolute w-full" @input="handleMaxInput" />
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  min: { type: Number, required: true },
  max: { type: Number, required: true },
  step: { type: Number, default: 1 },
  modelValue: { type: Object, default: () => ({ min: null, max: null }) }
});

const emit = defineEmits(['update:modelValue']);

const localMin = ref(props.modelValue.min != null ? props.modelValue.min : props.min);
const localMax = ref(props.modelValue.max != null ? props.modelValue.max : props.max);

watch(() => props.modelValue, (val) => {
    localMin.value = val.min != null ? val.min : props.min;
    localMax.value = val.max != null ? val.max : props.max;
}, { deep: true });

const handleMinInput = () => {
    if (localMin.value > localMax.value) {
        localMin.value = localMax.value;
    }
    emit('update:modelValue', { min: localMin.value, max: localMax.value });
};

const handleMaxInput = () => {
    if (localMax.value < localMin.value) {
        localMax.value = localMin.value;
    }
    emit('update:modelValue', { min: localMin.value, max: localMax.value });
};

const minPercent = computed(() => ((localMin.value - props.min) / (props.max - props.min)) * 100 || 0);
const maxPercent = computed(() => ((localMax.value - props.min) / (props.max - props.min)) * 100 || 0);
</script>

<style scoped>
.multi-range {
  -webkit-appearance: none;
  appearance: none;
  background: transparent;
  pointer-events: none;
  height: 100%;
  margin: 0;
  padding: 0;
  outline: none;
}
.multi-range::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  pointer-events: auto;
  width: 22px;
  height: 22px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 50%;
  cursor: grab;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.multi-range::-moz-range-thumb {
  pointer-events: auto;
  width: 22px;
  height: 22px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 50%;
  cursor: grab;
  box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.multi-range:active::-webkit-slider-thumb {
  cursor: grabbing;
  transform: scale(1.1);
  transition: transform 0.1s;
}
</style>
