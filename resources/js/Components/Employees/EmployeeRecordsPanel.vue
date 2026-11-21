<script setup>
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    employeeId: { type: [String, Number], required: true },
    canUpdate: { type: Boolean, default: false },
    section: { type: String, required: true },
});

const addresses = ref([]);
const contacts = ref([]);
const identities = ref([]);
const documents = ref([]);
const documentTypes = ref([]);
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const validationErrors = ref({});
const addressEditorOpen = ref(false);
const contactEditorOpen = ref(false);
const typeEditorOpen = ref(false);
const editingAddressId = ref(null);
const editingContactId = ref(null);
const editingTypeId = ref(null);

const addressForm = reactive({
    address_type: 'residential',
    address_line_1: '',
    address_line_2: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    is_primary: false,
});
const contactForm = reactive({
    full_name: '',
    relationship: '',
    phone: '',
    alternate_phone: '',
    email: '',
    notes: '',
});
const identityForm = reactive({
    identity_type: 'national_id',
    identity_number: '',
    issuing_country: '',
    issue_date: '',
    expiry_date: '',
});
const documentForm = reactive({
    document_type_id: '',
    title: '',
    issue_date: '',
    expiry_date: '',
    notes: '',
});
const typeForm = reactive({ name: '', description: '', is_active: true });
const uploadFile = ref(null);

const endpoints = computed(() => ({
    addresses: '/employees/' + props.employeeId + '/addresses',
    contacts: '/employees/' + props.employeeId + '/emergency-contacts',
    identities: '/employees/' + props.employeeId + '/identity-records',
    documents: '/employees/' + props.employeeId + '/documents',
    types: '/employee-document-types',
}));

function resetMessages() {
    errorMessage.value = '';
    successMessage.value = '';
    validationErrors.value = {};
}

function applyError(error) {
    validationErrors.value = error.response?.data?.errors || {};
    errorMessage.value = error.response?.data?.message || 'Something went wrong. Please try again.';
}

async function loadSection() {
    resetMessages();
    loading.value = true;
    try {
        if (props.section === 'addresses') {
            const response = await window.axios.get(endpoints.value.addresses, { headers: { Accept: 'application/json' } });
            addresses.value = response.data.data || [];
        } else if (props.section === 'contacts') {
            const response = await window.axios.get(endpoints.value.contacts, { headers: { Accept: 'application/json' } });
            contacts.value = response.data.data || [];
        } else if (props.section === 'identity') {
            const response = await window.axios.get(endpoints.value.identities, { headers: { Accept: 'application/json' } });
            identities.value = response.data.data || [];
        } else if (props.section === 'documents') {
            const [docsResponse, typesResponse] = await Promise.all([
                window.axios.get(endpoints.value.documents, { headers: { Accept: 'application/json' } }),
                window.axios.get(endpoints.value.types, { headers: { Accept: 'application/json' } }),
            ]);
            documents.value = docsResponse.data.data || [];
            documentTypes.value = typesResponse.data.data || [];
        }
    } catch (error) {
        applyError(error);
    } finally {
        loading.value = false;
    }
}

function resetAddressForm() {
    Object.assign(addressForm, {
        address_type: 'residential', address_line_1: '', address_line_2: '',
        city: '', state: '', postal_code: '', country: '', is_primary: false,
    });
    editingAddressId.value = null;
}
function editAddress(item) {
    Object.assign(addressForm, {
        address_type: item.address_type || 'residential',
        address_line_1: item.address_line_1 || '',
        address_line_2: item.address_line_2 || '',
        city: item.city || '',
        state: item.state || '',
        postal_code: item.postal_code || '',
        country: item.country || '',
        is_primary: Boolean(item.is_primary),
    });
    editingAddressId.value = item.id;
    addressEditorOpen.value = true;
}
async function saveAddress() {
    saving.value = true;
    resetMessages();
    try {
        const url = endpoints.value.addresses + (editingAddressId.value ? '/' + editingAddressId.value : '');
        if (editingAddressId.value) await window.axios.put(url, { ...addressForm }, { headers: { Accept: 'application/json' } });
        else await window.axios.post(url, { ...addressForm }, { headers: { Accept: 'application/json' } });
        addressEditorOpen.value = false;
        resetAddressForm();
        successMessage.value = 'Address saved successfully.';
        const response = await window.axios.get(endpoints.value.addresses, { headers: { Accept: 'application/json' } });
        addresses.value = response.data.data || [];
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}
async function deleteAddress(item) {
    if (!window.confirm('Delete this address?')) return;
    saving.value = true;
    resetMessages();
    try {
        await window.axios.delete(endpoints.value.addresses + '/' + item.id, { headers: { Accept: 'application/json' } });
        addresses.value = addresses.value.filter((row) => row.id !== item.id);
        successMessage.value = 'Address deleted.';
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}

function resetContactForm() {
    Object.assign(contactForm, { full_name: '', relationship: '', phone: '', alternate_phone: '', email: '', notes: '' });
    editingContactId.value = null;
}
function editContact(item) {
    Object.assign(contactForm, {
        full_name: item.full_name || '', relationship: item.relationship || '',
        phone: item.phone || '', alternate_phone: item.alternate_phone || '',
        email: item.email || '', notes: item.notes || '',
    });
    editingContactId.value = item.id;
    contactEditorOpen.value = true;
}
async function saveContact() {
    saving.value = true;
    resetMessages();
    try {
        const url = endpoints.value.contacts + (editingContactId.value ? '/' + editingContactId.value : '');
        if (editingContactId.value) await window.axios.put(url, { ...contactForm }, { headers: { Accept: 'application/json' } });
        else await window.axios.post(url, { ...contactForm }, { headers: { Accept: 'application/json' } });
        contactEditorOpen.value = false;
        resetContactForm();
        successMessage.value = 'Emergency contact saved successfully.';
        const response = await window.axios.get(endpoints.value.contacts, { headers: { Accept: 'application/json' } });
        contacts.value = response.data.data || [];
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}
async function deleteContact(item) {
    if (!window.confirm('Delete this emergency contact?')) return;
    saving.value = true;
    resetMessages();
    try {
        await window.axios.delete(endpoints.value.contacts + '/' + item.id, { headers: { Accept: 'application/json' } });
        contacts.value = contacts.value.filter((row) => row.id !== item.id);
        successMessage.value = 'Emergency contact deleted.';
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}

const editingIdentity = ref(null);
const identityEdit = reactive({ issuing_country: '', issue_date: '', expiry_date: '' });
function startIdentityEdit(item) {
    editingIdentity.value = editingIdentity.value === item.id ? null : item.id;
    Object.assign(identityEdit, { issuing_country: item.issuing_country ?? '', issue_date: item.issue_date ?? '', expiry_date: item.expiry_date ?? '' });
}
async function saveIdentityEdit(item) {
    saving.value = true;
    try {
        await window.axios.put(endpoints.value.identities + '/' + item.id, { ...identityEdit, issuing_country: identityEdit.issuing_country || null, issue_date: identityEdit.issue_date || null, expiry_date: identityEdit.expiry_date || null }, { headers: { Accept: 'application/json' } });
        editingIdentity.value = null;
        successMessage.value = 'Identity record updated.';
        await load();
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'The identity record could not be updated.';
    } finally { saving.value = false; }
}
async function deleteIdentity(item) {
    if (!window.confirm('Delete this identity record? This cannot be undone.')) return;
    saving.value = true;
    try {
        await window.axios.delete(endpoints.value.identities + '/' + item.id, { headers: { Accept: 'application/json' } });
        successMessage.value = 'Identity record deleted.';
        await load();
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'The identity record could not be deleted.';
    } finally { saving.value = false; }
}

async function saveIdentity() {
    saving.value = true;
    resetMessages();
    try {
        await window.axios.post(endpoints.value.identities, { ...identityForm }, { headers: { Accept: 'application/json' } });
        Object.assign(identityForm, {
            identity_type: 'national_id', identity_number: '',
            issuing_country: '', issue_date: '', expiry_date: '',
        });
        successMessage.value = 'Identity record saved. The previous record is retained in history.';
        const response = await window.axios.get(endpoints.value.identities, { headers: { Accept: 'application/json' } });
        identities.value = response.data.data || [];
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}

async function uploadDocument() {
    if (!uploadFile.value) {
        errorMessage.value = 'Choose a PDF, JPG or PNG file first.';
        return;
    }
    const payload = new FormData();
    Object.entries(documentForm).forEach(([key, value]) => {
        if (value !== '' && value !== null) payload.append(key, value);
    });
    payload.append('file', uploadFile.value);
    saving.value = true;
    resetMessages();
    try {
        await window.axios.post(endpoints.value.documents, payload, { headers: { Accept: 'application/json' } });
        uploadFile.value = null;
        const fileInput = document.querySelector('[data-employee-document-file]');
        if (fileInput) fileInput.value = '';
        Object.assign(documentForm, { document_type_id: '', title: '', issue_date: '', expiry_date: '', notes: '' });
        await loadSection();
        successMessage.value = 'Document uploaded successfully.';
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}
async function archiveDocument(item) {
    if (!window.confirm('Archive this document? The file and its metadata will be retained.')) return;
    saving.value = true;
    resetMessages();
    try {
        await window.axios.post(endpoints.value.documents + '/' + item.id + '/archive', {}, { headers: { Accept: 'application/json' } });
        await loadSection();
        successMessage.value = 'Document archived. Its file and metadata are retained.';
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}

async function deleteDocument(item) {
    if (!window.confirm('Permanently delete this document and its stored file? This cannot be undone.')) return;
    saving.value = true;
    resetMessages();
    try {
        await window.axios.post(endpoints.value.documents + '/' + item.id + '/delete', {}, { headers: { Accept: 'application/json' } });
        await loadSection();
        successMessage.value = 'Document permanently deleted.';
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}

function resetTypeForm() {
    Object.assign(typeForm, { name: '', description: '', is_active: true });
    editingTypeId.value = null;
}
function editType(item) {
    Object.assign(typeForm, { name: item.name, description: item.description || '', is_active: item.is_active });
    editingTypeId.value = item.id;
    typeEditorOpen.value = true;
}
async function saveType() {
    saving.value = true;
    resetMessages();
    try {
        const url = endpoints.value.types + (editingTypeId.value ? '/' + editingTypeId.value : '');
        if (editingTypeId.value) await window.axios.put(url, { ...typeForm }, { headers: { Accept: 'application/json' } });
        else await window.axios.post(url, { ...typeForm }, { headers: { Accept: 'application/json' } });
        typeEditorOpen.value = false;
        resetTypeForm();
        successMessage.value = 'Document type saved.';
        const response = await window.axios.get(endpoints.value.types, { headers: { Accept: 'application/json' } });
        documentTypes.value = response.data.data || [];
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}
async function archiveType(item) {
    if (!window.confirm('Archive this document type? Existing employee documents will remain available.')) return;
    saving.value = true;
    resetMessages();
    try {
        await window.axios.delete(endpoints.value.types + '/' + item.id, { headers: { Accept: 'application/json' } });
        await loadSection();
        successMessage.value = 'Document type archived.';
    } catch (error) { applyError(error); }
    finally { saving.value = false; }
}

function formatBytes(value) {
    const size = Number(value || 0);
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
}
function dateLabel(value) {
    if (!value) return '—';
    return String(value).slice(0, 10);
}

watch(() => [props.section, props.employeeId], loadSection, { immediate: true });
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-cyan-700">{{ $t('Employee records') }}</p>
                <h2 class="mt-1 text-lg font-bold text-slate-900">
                    {{ section === 'addresses' ? $t('Addresses') : section === 'contacts' ? $t('Emergency contacts') : section === 'identity' ? $t('Identity records') : $t('Documents') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ section === 'addresses' ? $t('Manage residential and mailing addresses.') : section === 'contacts' ? $t('Keep emergency contacts available to authorized HR users.') : section === 'identity' ? $t('Sensitive identity numbers are encrypted and displayed in masked form.') : $t('Upload, preview and archive private employee documents.') }}
                </p>
            </div>
            <span v-if="loading" class="text-xs font-medium text-slate-400">{{ $t('Loading…') }}</span>
        </div>

        <div v-if="successMessage" class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ $t(successMessage) }}</div>
        <div v-if="errorMessage" class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            {{ $t(errorMessage) }}
            <ul v-if="Object.keys(validationErrors).length" class="mt-2 list-inside list-disc">
                <li v-for="(messages, key) in validationErrors" :key="key">{{ $t(messages[0]) }}</li>
            </ul>
        </div>

        <template v-if="section === 'addresses'">
            <div class="mt-5 flex justify-end" v-if="canUpdate">
                <button type="button" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white" @click="resetAddressForm(); addressEditorOpen = !addressEditorOpen">{{ $t('Add address') }}</button>
            </div>
            <form v-if="addressEditorOpen && canUpdate" class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-2" @submit.prevent="saveAddress">
                <label class="text-xs font-semibold text-slate-600">{{ $t('Address type') }}<select v-model="addressForm.address_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="residential">{{ $t('Residential') }}</option><option value="mailing">{{ $t('Mailing') }}</option><option value="other">{{ $t('Other') }}</option></select>
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Country *') }}<input v-model="addressForm.country" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600 md:col-span-2">{{ $t('Address line 1 *') }}<input v-model="addressForm.address_line_1" required maxlength="255" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600 md:col-span-2">{{ $t('Address line 2') }}<input v-model="addressForm.address_line_2" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('City *') }}<input v-model="addressForm.city" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('State / Governorate') }}<input v-model="addressForm.state" maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Postal code') }}<input v-model="addressForm.postal_code" maxlength="30" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="flex items-center gap-2 self-end text-sm font-medium text-slate-700"><input v-model="addressForm.is_primary" type="checkbox" class="rounded border-slate-300">{{ $t('Primary address') }}</label>
                <div class="flex gap-2 md:col-span-2">
                    <button :disabled="saving" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? $t('Saving…') : $t('Save address') }}</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="addressEditorOpen = false; resetAddressForm()">{{ $t('Cancel') }}</button>
                </div>
            </form>
            <div v-if="!loading && !addresses.length" class="mt-6 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">{{ $t('No addresses have been added.') }}</div>
            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <article v-for="item in addresses" :key="item.id" class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-bold text-slate-800">{{ $t(item.address_type + ' address') }}</h3><span v-if="item.is_primary" class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">{{ $t('Primary') }}</span></div><p class="mt-2 text-sm text-slate-600">{{ item.address_line_1 }}<span v-if="item.address_line_2">, {{ item.address_line_2 }}</span></p><p class="mt-1 text-xs text-slate-500">{{ [item.city, item.state, item.postal_code, item.country].filter(Boolean).join(', ') }}</p></div>
                        <div v-if="canUpdate" class="flex gap-2"><button type="button" class="text-xs font-semibold text-cyan-700" @click="editAddress(item)">{{ $t('Edit') }}</button><button type="button" class="text-xs font-semibold text-red-600" @click="deleteAddress(item)">{{ $t('Delete') }}</button></div>
                    </div>
                </article>
            </div>
        </template>

        <template v-else-if="section === 'contacts'">
            <div class="mt-5 flex justify-end" v-if="canUpdate"><button type="button" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white" @click="resetContactForm(); contactEditorOpen = !contactEditorOpen">{{ $t('Add contact') }}</button></div>
            <form v-if="contactEditorOpen && canUpdate" class="mt-4 grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-2" @submit.prevent="saveContact">
                <label class="text-xs font-semibold text-slate-600">{{ $t('Full name *') }}<input v-model="contactForm.full_name" required maxlength="190" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Relationship *') }}<input v-model="contactForm.relationship" required maxlength="100" :placeholder="$t('Spouse, parent…')" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Phone *') }}<input v-model="contactForm.phone" required maxlength="30" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Alternate phone') }}<input v-model="contactForm.alternate_phone" maxlength="30" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600 md:col-span-2">{{ $t('Email') }}<input v-model="contactForm.email" type="email" maxlength="190" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600 md:col-span-2">{{ $t('Notes') }}<textarea v-model="contactForm.notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                </label>
                <div class="flex gap-2 md:col-span-2"><button :disabled="saving" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">{{ saving ? $t('Saving…') : $t('Save contact') }}</button><button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="contactEditorOpen = false; resetContactForm()">{{ $t('Cancel') }}</button></div>
            </form>
            <div v-if="!loading && !contacts.length" class="mt-6 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">{{ $t('No emergency contacts have been added.') }}</div>
            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
                <article v-for="item in contacts" :key="item.id" class="rounded-xl border border-slate-200 p-4">
                    <div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-bold text-slate-800">{{ item.full_name }}</h3><p class="mt-1 text-xs text-cyan-700">{{ item.relationship }}</p><p class="mt-3 text-sm text-slate-600">{{ item.phone }}</p><p v-if="item.alternate_phone" class="mt-1 text-xs text-slate-500">{{ $t('Alternate') }}: {{ item.alternate_phone }}</p><p v-if="item.email" class="mt-1 text-xs text-slate-500">{{ item.email }}</p><p v-if="item.notes" class="mt-2 whitespace-pre-line text-xs text-slate-500">{{ item.notes }}</p></div><div v-if="canUpdate" class="flex gap-2"><button type="button" class="text-xs font-semibold text-cyan-700" @click="editContact(item)">{{ $t('Edit') }}</button><button type="button" class="text-xs font-semibold text-red-600" @click="deleteContact(item)">{{ $t('Delete') }}</button></div></div>
                </article>
            </div>
        </template>

        <template v-else-if="section === 'identity'">
            <form v-if="canUpdate" class="mt-5 grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-4 md:grid-cols-2" @submit.prevent="saveIdentity">
                <div class="md:col-span-2"><h3 class="text-sm font-bold text-slate-800">{{ $t('Add or update identity record') }}</h3><p class="mt-1 text-xs text-slate-500">{{ $t('A previous number is retained in history. Only the last four characters are displayed.') }}</p></div>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Identity type *') }}<select v-model="identityForm.identity_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="national_id">{{ $t('National ID') }}</option><option value="passport">{{ $t('Passport') }}</option></select>
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Identity number *') }}<input v-model="identityForm.identity_number" required minlength="4" maxlength="100" autocomplete="off" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Issuing country') }}<input v-model="identityForm.issuing_country" maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Issue date') }}<input v-model="identityForm.issue_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <label class="text-xs font-semibold text-slate-600">{{ $t('Expiry date') }}<input v-model="identityForm.expiry_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                </label>
                <div class="flex items-end"><button :disabled="saving" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? $t('Saving…') : $t('Save identity') }}</button></div>
            </form>
            <div v-if="!loading && !identities.length" class="mt-6 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">{{ $t('No identity records have been added.') }}</div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                <table v-if="identities.length" class="w-full min-w-[600px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">{{ $t('Type') }}</th><th class="px-4 py-3">{{ $t('Masked number') }}</th><th class="px-4 py-3">{{ $t('Issuing country') }}</th><th class="px-4 py-3">{{ $t('Validity') }}</th><th class="px-4 py-3">{{ $t('Record') }}</th><th v-if="canUpdate" class="px-4 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100"><template v-for="item in identities" :key="item.id"><tr><td class="px-4 py-3 font-semibold text-slate-800">{{ item.identity_type === 'national_id' ? $t('National ID') : $t('Passport') }}</td><td class="px-4 py-3 font-mono text-slate-700">{{ item.masked_number }}</td><td class="px-4 py-3 text-slate-600">{{ item.issuing_country || '—' }}</td><td class="px-4 py-3 text-xs text-slate-600">{{ dateLabel(item.issue_date) }} – {{ dateLabel(item.expiry_date) }}</td><td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-bold" :class="item.is_current ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ item.is_current ? $t('Current') : $t('History') }}</span></td><td v-if="canUpdate" class="whitespace-nowrap px-4 py-3 text-xs font-semibold"><button class="mr-3 text-cyan-700" @click="startIdentityEdit(item)">{{ editingIdentity === item.id ? $t('Close') : $t('Edit') }}</button><button class="text-red-600" @click="deleteIdentity(item)">{{ $t('Delete') }}</button></td></tr><tr v-if="editingIdentity === item.id"><td colspan="6" class="bg-slate-50 px-4 py-3"><form class="grid grid-cols-1 gap-3 md:grid-cols-4" @submit.prevent="saveIdentityEdit(item)"><label class="text-xs font-semibold text-slate-600">{{ $t('Issuing country') }}<input v-model="identityEdit.issuing_country" maxlength="100" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-semibold text-slate-600">{{ $t('Issue date') }}<input v-model="identityEdit.issue_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="text-xs font-semibold text-slate-600">{{ $t('Expiry date') }}<input v-model="identityEdit.expiry_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><div class="flex items-end"><button :disabled="saving" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">{{ $t('Save changes') }}</button></div><p class="text-xs text-slate-500 md:col-span-4">{{ $t('To change the number itself, add a new record above. The old number stays in history.') }}</p></form></td></tr></template></tbody></table>
            </div>
        </template>

        <template v-else-if="section === 'documents'">
            <div v-if="canUpdate" class="mt-5 rounded-xl bg-slate-50 p-4">
                <h3 class="text-sm font-bold text-slate-800">{{ $t('Upload employee document') }}</h3>
                <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="uploadDocument">
                    <label class="text-xs font-semibold text-slate-600">{{ $t('Document type *') }}<select v-model="documentForm.document_type_id" required class="mt-1 w-full rounded-lg border-slate-300 text-sm"><option value="">{{ $t('Choose type') }}</option><option v-for="type in documentTypes.filter((item) => item.is_active)" :key="type.id" :value="type.id">{{ type.name }}</option></select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">{{ $t('Document title *') }}<input v-model="documentForm.title" required maxlength="190" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold text-slate-600">{{ $t('Issue date') }}<input v-model="documentForm.issue_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold text-slate-600">{{ $t('Expiry date') }}<input v-model="documentForm.expiry_date" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold text-slate-600 md:col-span-2">File (PDF, JPG, PNG · max 10 MB) *
                        <input data-employee-document-file type="file" required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="mt-1 block w-full text-sm text-slate-600" @change="uploadFile = $event.target.files?.[0] || null">
                    </label>
                    <label class="text-xs font-semibold text-slate-600 md:col-span-2">{{ $t('Notes') }}<textarea v-model="documentForm.notes" rows="2" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea>
                    </label>
                    <div class="md:col-span-2"><button :disabled="saving || !documentTypes.some((item) => item.is_active)" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ saving ? $t('Uploading…') : $t('Upload document') }}</button></div>
                </form>
            </div>

            <div v-if="canUpdate" class="mt-5 rounded-xl border border-slate-200 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-sm font-bold text-slate-800">{{ $t('Document types') }}</h3><p class="mt-1 text-xs text-slate-500">{{ $t('Company-managed types used for employee documents.') }}</p></div><button type="button" class="text-xs font-bold text-cyan-700" @click="resetTypeForm(); typeEditorOpen = !typeEditorOpen">{{ $t('Add type') }}</button></div>
                <form v-if="typeEditorOpen" class="mt-3 grid grid-cols-1 gap-3 bg-slate-50 p-3 md:grid-cols-2" @submit.prevent="saveType">
                    <label class="text-xs font-semibold text-slate-600">{{ $t('Name *') }}<input v-model="typeForm.name" required maxlength="150" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="text-xs font-semibold text-slate-600">{{ $t('Description') }}<input v-model="typeForm.description" maxlength="5000" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input v-model="typeForm.is_active" type="checkbox" class="rounded border-slate-300">{{ $t('Active for new uploads') }}</label>
                    <div class="flex gap-2"><button :disabled="saving" class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white">{{ $t('Save type') }}</button><button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs" @click="typeEditorOpen = false; resetTypeForm()">{{ $t('Cancel') }}</button></div>
                </form>
                <div class="mt-3 flex flex-wrap gap-2">
                    <div v-for="type in documentTypes" :key="type.id" class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                        <span class="font-semibold text-slate-700">{{ type.name }}</span><span :class="type.is_active ? 'text-emerald-700' : 'text-slate-400'">{{ type.is_active ? $t('Active') : $t('Inactive') }}</span>
                        <button type="button" class="font-semibold text-cyan-700" @click="editType(type)">{{ $t('Edit') }}</button>
                        <button type="button" class="font-semibold text-red-600" @click="archiveType(type)">{{ $t('Archive') }}</button>
                    </div>
                    <p v-if="!documentTypes.length" class="text-xs text-slate-500">{{ $t('No document types configured yet.') }}</p>
                </div>
            </div>

            <div v-if="!loading && !documents.length" class="mt-6 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">{{ $t('No employee documents have been uploaded.') }}</div>
            <div class="mt-4 space-y-3">
                <article v-for="item in documents" :key="item.id" class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="break-words text-sm font-bold text-slate-800">{{ item.title }}</h3><span v-if="item.is_archived" class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500">{{ $t('Archived') }}</span><span v-else-if="item.is_expired" class="rounded-full bg-red-50 px-2 py-1 text-[10px] font-bold text-red-700">{{ $t('Expired') }}</span></div><p class="mt-1 text-xs text-slate-500">{{ item.document_type_name || $t('Archived type') }} · {{ item.original_file_name }} · {{ formatBytes(item.file_size) }}</p><p class="mt-1 text-xs text-slate-400">{{ $t('Issued') }} {{ dateLabel(item.issue_date) }} · {{ $t('Expires') }} {{ dateLabel(item.expiry_date) }}</p></div>
                    <div class="flex shrink-0 flex-wrap gap-3 text-xs font-semibold"><a :href="endpoints.documents + '/' + item.id + '/preview'" target="_blank" rel="noopener" class="text-cyan-700">{{ $t('Preview') }}</a><a :href="endpoints.documents + '/' + item.id + '/download'" class="text-cyan-700">{{ $t('Download') }}</a><button v-if="canUpdate && !item.is_archived" type="button" class="text-amber-700" @click.stop.prevent="archiveDocument(item)">{{ $t('Archive') }}</button><button v-if="canUpdate" type="button" class="text-red-600" @click.stop.prevent="deleteDocument(item)">{{ $t('Delete permanently') }}</button></div>
                </article>
            </div>
        </template>
    </section>
</template>
