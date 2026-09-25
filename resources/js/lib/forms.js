import { computed } from 'vue';

// Nuxt UI selects use null for "nothing selected", while these forms post ''
// for an empty field. Bind the select to this instead of `form[key]`.
export function nullableField(form, key) {
    return computed({
        get: () => (form[key] === '' || form[key] === undefined ? null : form[key]),
        set: (value) => {
            form[key] = value ?? '';
        },
    });
}
