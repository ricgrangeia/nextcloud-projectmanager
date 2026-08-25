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

const tests = ref([])
const testForm = ref(null)
const STATUSES = ['to_test', 'passed', 'failed']

function todayIso() {
	const d = new Date()
	const pad = (n) => String(n).padStart(2, '0')
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

async function load() {
	tests.value = await api.listTests(props.id)
}

watch(() => props.id, load, { immediate: true })

const counts = computed(() => {
	const c = { passed: 0, failed: 0, to_test: 0 }
	for (const test of tests.value) {
		c[test.status] = (c[test.status] ?? 0) + 1
	}
	return c
})

function openNewTestForm() {
	testForm.value = { id: null, area: '', profile: '', scenario: '', expected: '', status: 'to_test', testDate: todayIso(), notes: '' }
}

function openEditTestForm(test) {
	testForm.value = { ...test, testDate: test.testDate ?? '' }
}

function cancelTestForm() {
	testForm.value = null
}

function onTestDialogOpenChange(isOpen) {
	if (!isOpen) {
		testForm.value = null
	}
}

async function submitTestForm() {
	const area = testForm.value.area.trim()
	if (!area) {
		window.alert(t('projectmanager', 'Please fill in the area.'))
		return
	}
	const payload = {
		area,
		profile: testForm.value.profile,
		scenario: testForm.value.scenario,
		expected: testForm.value.expected,
		status: testForm.value.status,
		testDate: testForm.value.testDate || null,
		notes: testForm.value.notes,
	}
	if (testForm.value.id === null) {
		await api.createTest(props.id, payload)
	} else {
		await api.updateTest(testForm.value.id, payload)
	}
	testForm.value = null
	await load()
}

const testDialogButtons = computed(() => [
	{ label: t('projectmanager', 'Cancel'), callback: cancelTestForm },
	{ label: t('projectmanager', 'Save'), type: 'primary', nativeType: 'submit' },
])

async function updateStatus(test, status) {
	await api.updateTest(test.id, { status })
	await load()
}

async function updateField(test, field, value) {
	await api.updateTest(test.id, { [field]: value })
	await load()
}

async function removeTest(id) {
	if (!window.confirm(t('projectmanager', 'Delete this test?'))) {
		return
	}
	await api.deleteTest(id)
	await load()
}
</script>

<template>
	<div class="tests-view">
		<div class="toolbar">
			<button type="button" class="link-btn" @click="openNewTestForm">{{ t('projectmanager', '+ Test') }}</button>
			<span class="counts">
				{{ t('projectmanager', 'Passed') }}: {{ counts.passed }} ·
				{{ t('projectmanager', 'Failed') }}: {{ counts.failed }} ·
				{{ t('projectmanager', 'To test') }}: {{ counts.to_test }}
			</span>
		</div>
		<table class="tests-table">
			<thead>
				<tr>
					<th>ID</th>
					<th>{{ t('projectmanager', 'Area') }}</th>
					<th>{{ t('projectmanager', 'Profile') }}</th>
					<th>{{ t('projectmanager', 'Scenario/Action') }}</th>
					<th>{{ t('projectmanager', 'Expected result') }}</th>
					<th>{{ t('projectmanager', 'Status') }}</th>
					<th>{{ t('projectmanager', 'Date') }}</th>
					<th>{{ t('projectmanager', 'Notes') }}</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="test in tests" :key="test.id">
					<td>{{ test.id }}</td>
					<td>
						<EditableCell :model-value="test.area" @save="v => updateField(test, 'area', v)" />
					</td>
					<td>
						<EditableCell :model-value="test.profile" @save="v => updateField(test, 'profile', v)" />
					</td>
					<td>
						<EditableCell :model-value="test.scenario" @save="v => updateField(test, 'scenario', v)" />
					</td>
					<td>
						<EditableCell :model-value="test.expected" @save="v => updateField(test, 'expected', v)" />
					</td>
					<td>
						<select :value="test.status" @change="updateStatus(test, $event.target.value)">
							<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
						</select>
						<StatusPill :status="test.status" />
					</td>
					<td>{{ test.testDate }}</td>
					<td>
						<EditableCell :model-value="test.notes" @save="v => updateField(test, 'notes', v)" />
					</td>
					<td class="row-actions">
						<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Edit')" :title="t('projectmanager', 'Edit')" @click="openEditTestForm(test)">
							<Pencil :size="16" />
						</button>
						<button type="button" class="icon-btn" :aria-label="t('projectmanager', 'Delete')" :title="t('projectmanager', 'Delete')" @click="removeTest(test.id)">
							<Delete :size="16" />
						</button>
					</td>
				</tr>
			</tbody>
		</table>

		<NcDialog
			:open="testForm !== null"
			:name="testForm?.id === null ? t('projectmanager', '+ Test') : t('projectmanager', 'Edit')"
			is-form
			size="normal"
			:buttons="testDialogButtons"
			@update:open="onTestDialogOpenChange"
			@submit.prevent="submitTestForm">
			<div v-if="testForm" class="dialog-form">
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Area') }}</span>
					<input v-model="testForm.area" type="text" autofocus>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Profile') }}</span>
					<input v-model="testForm.profile" type="text">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Scenario/Action') }}</span>
					<textarea v-model="testForm.scenario" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Expected result') }}</span>
					<textarea v-model="testForm.expected" rows="2"></textarea>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Status') }}</span>
					<select v-model="testForm.status">
						<option v-for="s in STATUSES" :key="s" :value="s">{{ statusLabel(s) }}</option>
					</select>
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Date') }}</span>
					<input v-model="testForm.testDate" type="date">
				</label>
				<label class="dialog-field">
					<span class="dialog-label">{{ t('projectmanager', 'Notes') }}</span>
					<textarea v-model="testForm.notes" rows="2"></textarea>
				</label>
			</div>
		</NcDialog>
	</div>
</template>

<style scoped>
.tests-view {
	padding: 16px;
	overflow: auto;
	height: 100%;
}

.toolbar {
	margin-bottom: 16px;
	display: flex;
	gap: 24px;
	align-items: center;
}

.counts {
	color: var(--color-text-maxcontrast);
	font-size: 13px;
}

.tests-table {
	border-collapse: collapse;
	width: 100%;
	font-size: 13px;
}

.tests-table th,
.tests-table td {
	border: 1px solid var(--color-border);
	padding: 6px 8px;
	text-align: left;
}

.tests-table th {
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
.dialog-field input[type="date"],
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
