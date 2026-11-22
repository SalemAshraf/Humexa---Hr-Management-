<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    employeeId: { type: [String, Number], required: true },
    employeeName: { type: String, required: true },
    canUpdate: { type: Boolean, default: false },
    hasPhoto: { type: Boolean, default: true },
});

const photoUrl = ref(null);
const loading = ref(true);
const uploading = ref(false);
const deleting = ref(false);
const errorMessage = ref('');
const fileInput = ref(null);

function releasePhotoUrl() {
    if (photoUrl.value) {
        URL.revokeObjectURL(photoUrl.value);
        photoUrl.value = null;
    }
}

async function loadPhoto() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const response = await window.axios.get(`/employees/${props.employeeId}/profile-photo`, {
            responseType: 'blob',
            headers: { Accept: 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8' },
        });

        releasePhotoUrl();
        photoUrl.value = URL.createObjectURL(response.data);
    } catch (error) {
        releasePhotoUrl();

        if (error.response?.status !== 404) {
            errorMessage.value = 'Unable to load the profile photo.';
        }
    } finally {
        loading.value = false;
    }
}

function choosePhoto() {
    fileInput.value?.click();
}

async function uploadPhoto(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    const formData = new FormData();
    formData.append('photo', file);

    uploading.value = true;
    errorMessage.value = '';

    try {
        await window.axios.post(`/employees/${props.employeeId}/profile-photo`, formData);
        await loadPhoto();
    } catch (error) {
        errorMessage.value = error.response?.data?.errors?.photo?.[0]
            || 'Unable to upload the profile photo. Please try again.';
    } finally {
        uploading.value = false;
    }
}

async function removePhoto() {
    if (!window.confirm('Remove this employee profile photo?')) return;

    deleting.value = true;
    errorMessage.value = '';

    try {
        // A dedicated POST endpoint avoids sharing DELETE semantics with employee archival.
        await window.axios.post(`/employees/${props.employeeId}/profile-photo/remove`);
        releasePhotoUrl();
    } catch (error) {
        errorMessage.value = 'Unable to remove the profile photo. Please try again.';
    } finally {
        deleting.value = false;
    }
}

const initials = props.employeeName
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');

onMounted(() => {
    if (props.hasPhoto) {
        loadPhoto();
    } else {
        loading.value = false;
    }
});
onBeforeUnmount(releasePhotoUrl);
</script>

<template>
    <div class="flex flex-col items-center gap-3">
        <div class="relative flex h-28 w-28 items-center justify-center overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-slate-100 to-cyan-50 shadow-sm">
            <img v-if="photoUrl" :src="photoUrl" :alt="`${employeeName} profile photo`" class="h-full w-full object-cover">
            <span v-else class="text-3xl font-bold tracking-wide text-cyan-800">{{ initials || '?' }}</span>
            <span v-if="loading" class="absolute inset-0 flex items-center justify-center bg-white/70 text-xs font-medium text-slate-600">{{ $t('Loading…') }}</span>
        </div>

        <div v-if="canUpdate" class="flex flex-wrap justify-center gap-2">
            <button type="button" :disabled="uploading || deleting" class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-800 disabled:cursor-not-allowed disabled:opacity-60" @click.stop.prevent="choosePhoto">
                {{ uploading ? $t('Uploading…') : photoUrl ? $t('Change photo') : $t('Upload photo') }}
            </button>
            <button v-if="photoUrl" type="button" :disabled="uploading || deleting" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60" @click.stop.prevent="removePhoto">
                {{ deleting ? $t('Removing…') : $t('Remove') }}
            </button>
            <input ref="fileInput" type="file" accept="image/jpeg,image/png" class="hidden" @change="uploadPhoto">
        </div>

        <p v-if="canUpdate" class="text-center text-[11px] text-slate-400">{{ $t('JPG or PNG · Maximum 5 MB') }}</p>
        <p v-if="errorMessage" class="max-w-52 text-center text-xs text-red-600" role="alert">{{ $t(errorMessage) }}</p>
    </div>
</template>
