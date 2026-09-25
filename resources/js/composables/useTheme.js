import { ref, watch } from 'vue';

const STORAGE_KEY = 'educativo-theme';

function readStoredDark() {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'dark';
    } catch {
        return false;
    }
}

// One shared ref: every layout and menu toggles the same theme. The inline
// script in app.blade.php applies the stored theme before the first paint.
const dark = ref(typeof window !== 'undefined' && readStoredDark());

watch(dark, (value) => {
    document.documentElement.classList.toggle('dark', value);

    try {
        localStorage.setItem(STORAGE_KEY, value ? 'dark' : 'light');
    } catch {
        // Storage can be blocked (private mode); the theme still applies for this page.
    }
});

export function useTheme() {
    function toggleTheme() {
        dark.value = !dark.value;
    }

    return { dark, toggleTheme };
}
