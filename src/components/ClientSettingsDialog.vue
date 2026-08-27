<script setup>
import { ref, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import api from '../api/client.js'
import { loadClients, loadProjects } from '../store/index.js'

const form = ref(null)

function open(client) {
	form.value = {
		id: client.id,
		name: client.name,
		hourlyRate: client.hourlyRate ?? '',
		currencySymbol: client.currencySymbol,
	}
}

function onOpenChange(isOpen) {
	if (!isOpen) {
		form.value = null
	}
}

async function submit() {
	const rateStr = String(form.value.hourlyRate).trim()
	const hourlyRate = rateStr === '' ? null : Number(rateStr)
	await api.updateClient(form.value.id, {
		name: form.value.name,
		hourlyRate,
		hourlyRateProvided: true,
		currencySymbol: form.value.currencySymbol || '€',
	})
	form.value = null
	await Promise.all([loadClients(), loadProjects()])
}

const buttons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: () => { form.value = null } },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

defineExpose({ open })
</script>

<template>
	<NcDialog
		:open="form !== null"
		:name="t('projectmanager', 'Client settings')"
		is-form
		size="small"
		:buttons="buttons"
		@update:open="onOpenChange"
		@submit.prevent="submit">
		<div v-if="form" class="dialog-form">
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Name') }}</span>
				<input v-model="form.name" type="text" required>
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Hourly rate') }}</span>
				<input v-model="form.hourlyRate" type="number" step="0.01" :placeholder="t('projectmanager', 'Leave empty to disable')">
			</label>
			<label class="dialog-field">
				<span class="dialog-label">{{ t('projectmanager', 'Currency symbol') }}</span>
				<input v-model="form.currencySymbol" type="text" maxlength="8">
			</label>
			<p class="dialog-hint">{{ t('projectmanager', "Projects under this client use this hourly rate unless they set their own, and always use this client's currency.") }}</p>
		</div>
	</NcDialog>
</template>

<style scoped>
.dialog-form {
	display: flex;
	flex-direction: column;
	gap: 14px;
	padding: 4px 0 8px;
}

.dialog-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	font-size: 13px;
}

.dialog-label {
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.dialog-field input {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.dialog-field input:focus {
	border-color: var(--color-primary-element);
	outline: none;
}

.dialog-hint {
	margin: -6px 0 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
