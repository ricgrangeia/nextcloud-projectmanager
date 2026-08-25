<script setup>
import { ref } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import DatabaseExportOutline from 'vue-material-design-icons/DatabaseExportOutline.vue'
import { t } from '@nextcloud/l10n'
import api from '../api/client.js'
import { loadProjects } from '../store/index.js'

const open = ref(false)
const selectedFile = ref(null)
const importing = ref(false)
const importResult = ref(null)
const importError = ref('')

function onFileChange(event) {
	selectedFile.value = event.target.files[0] ?? null
	importResult.value = null
	importError.value = ''
}

async function doImport() {
	if (!selectedFile.value) {
		return
	}
	importing.value = true
	importError.value = ''
	importResult.value = null
	try {
		importResult.value = await api.backupImport(selectedFile.value)
		await loadProjects()
	} catch (e) {
		importError.value = e?.response?.data?.message || t('projectmanager', 'Import failed.')
	} finally {
		importing.value = false
	}
}
</script>

<template>
	<button type="button" class="backup-btn" :aria-label="t('projectmanager', 'Backup & restore')" :title="t('projectmanager', 'Backup & restore')" @click="open = true">
		<DatabaseExportOutline :size="18" />
	</button>
	<NcDialog :open="open" :name="t('projectmanager', 'Backup & restore')" size="normal" @update:open="(v) => (open = v)">
		<div class="backup-content">
			<h4>{{ t('projectmanager', 'Export everything') }}</h4>
			<p>{{ t('projectmanager', 'Downloads a single JSON file with every project you own — modules, points, leaves, day hours, features and tests. Keep it somewhere safe before migrating to a new server.') }}</p>
			<a class="backup-btn-action" :href="api.backupExportUrl()">{{ t('projectmanager', 'Download backup') }}</a>

			<hr class="backup-separator">

			<h4>{{ t('projectmanager', 'Restore from a backup') }}</h4>
			<p>{{ t('projectmanager', 'Choose a backup file exported from Project Manager. This always creates new projects — it never overwrites or merges with what you already have.') }}</p>
			<input type="file" accept="application/json" @change="onFileChange">
			<button type="button" class="backup-btn-action" :disabled="!selectedFile || importing" @click="doImport">
				{{ importing ? t('projectmanager', 'Importing…') : t('projectmanager', 'Import') }}
			</button>
			<p v-if="importError" class="import-error">{{ importError }}</p>
			<div v-if="importResult" class="import-summary">
				<p class="import-success">{{ t('projectmanager', 'Import complete.') }}</p>
				<ul>
					<li>{{ t('projectmanager', 'Projects') }}: {{ importResult.projects }}</li>
					<li>{{ t('projectmanager', 'Modules') }}: {{ importResult.modules }}</li>
					<li>{{ t('projectmanager', 'Points') }}: {{ importResult.points }}</li>
					<li>{{ t('projectmanager', 'Leaves') }}: {{ importResult.leaves }}</li>
					<li>{{ t('projectmanager', 'Day-hours entries') }}: {{ importResult.dayHours }}</li>
					<li>{{ t('projectmanager', 'Features') }}: {{ importResult.features }}</li>
					<li>{{ t('projectmanager', 'Tests') }}: {{ importResult.tests }}</li>
				</ul>
			</div>
		</div>
	</NcDialog>
</template>

<style scoped>
.backup-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 22px;
	height: 22px;
	background: none;
	border: none;
	border-radius: 50%;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	padding: 0;
	vertical-align: middle;
	flex-shrink: 0;
}

.backup-btn:hover {
	background-color: var(--color-background-hover);
	color: var(--color-main-text);
}

.backup-content {
	padding: 4px 0 16px;
	line-height: 1.6;
}

.backup-content h4 {
	margin: 20px 0 6px;
	font-size: 14px;
}

.backup-content h4:first-child {
	margin-top: 0;
}

.backup-content p {
	margin: 0 0 12px;
	color: var(--color-text-maxcontrast);
}

.backup-separator {
	border: none;
	border-top: 1px solid var(--color-border);
	margin: 20px 0;
}

.backup-btn-action {
	display: inline-block;
	margin-top: 4px;
	padding: 8px 16px;
	border: 2px solid var(--color-primary-element);
	border-radius: var(--border-radius-large);
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	font-weight: 600;
	cursor: pointer;
	text-decoration: none;
}

.backup-btn-action:disabled {
	opacity: 0.5;
	cursor: default;
}

.import-error {
	color: var(--color-error-text, #a02020);
	margin-top: 12px;
}

.import-summary {
	margin-top: 16px;
	padding: 12px 16px;
	background-color: var(--color-background-hover);
	border-radius: var(--border-radius-large);
}

.import-summary ul {
	margin: 0;
	padding-left: 20px;
}

.import-success {
	font-weight: 600;
	color: var(--color-main-text) !important;
}
</style>
