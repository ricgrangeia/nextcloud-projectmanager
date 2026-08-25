<script setup>
import { ref, watch, computed } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import api from '../api/client.js'
import StatusPill from '../components/StatusPill.vue'
import EditableCell from '../components/EditableCell.vue'
import { statusLabel } from '../utils/statusLabels.js'

const props = defineProps({
	id: { type: [String, Number], required: true },
})

const features = ref([])
const featureForm = ref(null)
const STATUSES = ['not_started', 'in_progress', 'done']

async function load() {
	features.value = await api.listFeatures(props.id)
}

watch(() => props.id, load, { immediate: true })

const grouped = computed(() => {
	const bySection = {}
	for (const feature of features.value) {
		(bySection[feature.section] ??= []).push(feature)
	}
	return bySection
})

function openNewFeatureForm() {
	featureForm.value = { id: null, section: '', name: '', pointRef: '', status: 'not_started', businessValue: '', externalPending: '' }
}

function openEditFeatureForm(feature) {
	featureForm.value = { ...feature }
}

function cancelFeatureForm() {
	featureForm.value = null
}

function onFeatureDialogOpenChange(isOpen) {
	if (!isOpen) {
		featureForm.value = null
	}
}

async function submitFeatureForm() {
	const name = featureForm.value.name.trim()
	const section = featureForm.value.section.trim()
	if (!name || !section) {
		window.alert(t('projectmanager', 'Please fill in the section and name.'))
		return
	}
	const payload = {
		section,
		name,
		pointRef: featureForm.value.pointRef,
		status: featureForm.value.status,
		businessValue: featureForm.value.businessValue,
		externalPending: featureForm.value.externalPending,
	}
	if (featureForm.value.id === null) {
		await api.createFeature(props.id, payload)
	} else {
		await api.updateFeature(featureForm.value.id, payload)
	}
	featureForm.value = null
	await load()
}

const featureDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelFeatureForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function updateStatus(feature, status) {
	await api.updateFeature(feature.id, { status })
	await load()
}

async function updateField(feature, field, value) {
	await api.updateFeature(feature.id, { [field]: value })
	await load()
}

async function removeFeature(id) {
	if (!window.confirm(t('projectmanager', 'Delete this feature?'))) {
		return
	}
	await api.deleteFeature(id)
	await load()
}
</script>

<template>
	<div class="features-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewFeatureForm">{{ t('projectmanager', '+ Feature') }}</button>
		</div>
		<div v-for="(items, section) in grouped" :key="section" class="section">
			<h3>{{ section }}</h3>
			<table class="features-table">
				<thead>
					<tr>
						<th>{{ t('projectmanager', 'Feature') }}</th>
						<th>{{ t('projectmanager', 'Point') }}</th>
						<th>{{ t('projectmanager', 'Status') }}</th>
						<th>{{ t('projectmanager', 'Business value') }}</th>
						<th>{{ t('projectmanager', 'External pending') }}</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="feature in items" :key="feature.id">
						<td>
							<EditableCell :model-value="feature.name" @save="v => updateField(feature, 'name', v)" />
						</td>
						<td>
							<EditableCell :model-value="feature.pointRef" @save="v => updateField(feature, 'pointRef', v)" />
						</td>
						<td>
							<select :value="feature.status" @change="updateStatus(feature, $event.target.value)">
								<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
							</select>
							<StatusPill :status="feature.status" />
						</td>
						<td>
							<EditableCell :model-value="feature.businessValue" @save="v => updateField(feature, 'businessValue', v)" />
						</td>
						<td>
							<EditableCell :model-value="feature.externalPending" @save="v => updateField(feature, 'externalPending', v)" />
						</td>
						<td class="row-actions">
							<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditFeatureForm(feature)">
								<Pencil :size="16" />
							</button>
							<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="removeFeature(feature.id)">
								<Delete :size="16" />
							</button>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<NcDialog
			:open="featureForm !== null"
			:name="featureForm?.id === null ? t('projectmanager', '+ Feature') : t('projectmanager', 'Edit')"
			is-form
			size="normal"
			:buttons="featureDialogButtons"
			@update:open="onFeatureDialogOpenChange"
			@submit.prevent="submitFeatureForm">
			<div v-if="featureForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Section (e.g. ADMISSIONS)') }}</span>
					<input v-model="featureForm.section" type="text" autofocus>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Feature name') }}</span>
					<input v-model="featureForm.name" type="text">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Related point code (optional)') }}</span>
					<input v-model="featureForm.pointRef" type="text">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Status') }}</span>
					<select v-model="featureForm.status">
						<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Business value') }}</span>
					<textarea v-model="featureForm.businessValue" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'External pending') }}</span>
					<textarea v-model="featureForm.externalPending" rows="2"></textarea>
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.features-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.toolbar {
	margin-bottom: 16px;
}

.section {
	margin-bottom: 24px;
}

.features-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
}

.features-table th,
.features-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
}

.features-table th {
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	font-size: 12px;
}

.row-actions {
	white-space: nowrap;
}

.icon-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 24px;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
	vertical-align: middle;
}

.icon-btn:hover {
	background-color: var(--color-background-hover);
	color: var(--color-main-text);
}

.dialog-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 4px 0 16px;
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

.dialog-field input[type="text"],
.dialog-field select,
.dialog-field textarea {
	width: 100%;
	box-sizing: border-box;
	padding: 8px 10px;
	border: 2px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius-large);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
	font: inherit;
}

.dialog-field input:focus,
.dialog-field select:focus,
.dialog-field textarea:focus {
	border-color: var(--color-primary-element);
	outline: none;
}
</style>
