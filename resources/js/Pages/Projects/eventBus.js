import { reactive } from 'vue';

// A simple global state acting as an event bus / shared store
export const eventBus = reactive({
    filters: {
        priceMin: null,
        priceMax: null,
        sqmMin: null,
        sqmMax: null,
        floors: [],
        rooms: [],
        availabilities: [],
        features: [],
    },
    activeView: '3d-finder',
    targetViewId: null,
    
    // Method to update filters
    setFilter(key, value) {
        this.filters[key] = value;
    },
    
    // Method to change view
    setView(viewKey) {
        this.activeView = viewKey;
    }
});
