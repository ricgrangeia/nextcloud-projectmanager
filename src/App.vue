<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import Cog from 'vue-material-design-icons/Cog.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import { t } from '@nextcloud/l10n'
import api from './api/client.js'
import { state, loadProjects, loadClients, toggleShowArchived } from './store/index.js'
import HelpButton from './components/HelpButton.vue'
import BackupDialog from './components/BackupDialog.vue'
import ClientSettingsDialog from './components/ClientSettingsDialog.vue'

const route = useRoute()
const router = useRouter()
const clientSettingsDialog = ref(null)
const appVersion = __APP_VERSION__
const appBuildDate = __APP_BUILD_DATE__

onMounted(() => {
	loadProjects()
	loadClients()
})

const projectGroups = computed(() => {
	if (state.clients.length === 0) {
		return null
	}
	const groups = state.clients.map((client) => ({
		key: `client-${client.id}`,
		client,
		projects: state.projects.filter((p) => p.clientId === client.id),
	}))
	const unassigned = state.projects.filter((p) => !p.clientId)
	if (unassigned.length > 0) {
		groups.push({ key: 'no-client', client: null, projects: unassigned })
	}
	return groups
})

async function createProject() {
	const name = window.prompt(t('projectmanager', 'New project'))
	if (!name || !name.trim()) {
		return
	}
	const project = await api.createProject({ name: name.trim(), hoursPerWorkingDay: 7 })
	await loadProjects()
	router.push({ name: 'grid', params: { id: project.id } })
}

async function createClient() {
	const name = window.prompt(t('projectmanager', 'New client'))
	if (!name || !name.trim()) {
		return
	}
	await api.createClient({ name: name.trim() })
	await loadClients()
}

function openClientSettings(client) {
	clientSettingsDialog.value.open(client)
}

async function deleteClient(client) {
	if (!window.confirm(t('projectmanager', 'Delete this client? Its projects are kept, just unassigned.'))) {
		return
	}
	await api.deleteClient(client.id)
	await Promise.all([loadClients(), loadProjects()])
}

function openProject(event, project) {
	event?.preventDefault?.()
	router.push({ name: 'grid', params: { id: project.id } })
}

function openClient(event, client) {
	event?.preventDefault?.()
	router.push({ name: 'client', params: { id: client.id } })
}

function projectDisplayName(project) {
	return project.archived
		? `${project.name} (${t('projectmanager', 'archived')})`
		: project.name
}

function openProjectSettings(project) {
	router.push({ name: 'grid', params: { id: project.id }, query: { settings: '1' } })
}
</script>

<template>
	<NcContent app-name="projectmanager">
		<NcAppNavigation>
			<template #list>
				<template v-if="projectGroups">
					<NcAppNavigationItem
						v-for="group in projectGroups"
						:key="group.key"
						:name="group.client ? group.client.name : t('projectmanager', 'No client')"
						:active="group.client && route.name === 'client' && route.params.id === String(group.client.id)"
						allow-collapse
						open
						@click="event => group.client && openClient(event, group.client)">
						<template v-if="group.client" #actions>
							<NcActionButton :aria-label="t('projectmanager', 'Client settings')" @click.stop="openClientSettings(group.client)">
								<template #icon>
									<Cog :size="16" />
								</template>
								{{ t('projectmanager', 'Client settings') }}
							</NcActionButton>
							<NcActionButton :aria-label="t('projectmanager', 'Delete client')" @click.stop="deleteClient(group.client)">
								<template #icon>
									<Delete :size="16" />
								</template>
								{{ t('projectmanager', 'Delete client') }}
							</NcActionButton>
						</template>
						<NcAppNavigationItem
							v-for="project in group.projects"
							:key="project.id"
							:name="projectDisplayName(project)"
							:class="{ 'is-archived': project.archived }"
							:active="route.params.id === String(project.id)"
							@click="event => openProject(event, project)">
							<template #actions>
								<NcActionButton :aria-label="t('projectmanager', 'Project settings')" @click.stop="openProjectSettings(project)">
									<template #icon>
										<Cog :size="16" />
									</template>
									{{ t('projectmanager', 'Project settings') }}
								</NcActionButton>
							</template>
						</NcAppNavigationItem>
					</NcAppNavigationItem>
				</template>
				<template v-else>
					<NcAppNavigationItem
						v-for="project in state.projects"
						:key="project.id"
						:name="projectDisplayName(project)"
						:class="{ 'is-archived': project.archived }"
						:active="route.params.id === String(project.id)"
						@click="event => openProject(event, project)">
						<template #actions>
							<NcActionButton :aria-label="t('projectmanager', 'Project settings')" @click.stop="openProjectSettings(project)">
								<template #icon>
									<Cog :size="16" />
								</template>
								{{ t('projectmanager', 'Project settings') }}
							</NcActionButton>
						</template>
					</NcAppNavigationItem>
				</template>
				<NcAppNavigationItem
					:name="state.showArchived ? t('projectmanager', 'Hide archived projects') : t('projectmanager', 'Show archived projects')"
					@click="toggleShowArchived" />
			</template>
			<template #footer>
				<div class="nav-footer">
					<div class="nav-footer-main">
						<NcAppNavigationNew
							:text="t('projectmanager', 'New project')"
							@click="createProject" />
						<button type="button" class="link-btn" @click="createClient">
							{{ t('projectmanager', '+ Client') }}
						</button>
						<HelpButton :title="t('projectmanager', 'How Project Manager works')">
						<h4>{{ t('projectmanager', 'Points & Leaves') }}</h4>
						<p>{{ t('projectmanager', 'Work is organized into Modules (groups) and Points — estimable units of work with a status and an optional hour estimate.') }}</p>
						<p>{{ t('projectmanager', 'Log what you did each day as Leaves: short entries tied to a Point and a date.') }}</p>

						<h4>{{ t('projectmanager', 'How hours are calculated') }}</h4>
						<p>{{ t('projectmanager', 'Set how many hours you actually worked each day in the "Hours/day" row.') }}</p>
						<p>{{ t('projectmanager', 'Each day, the app splits 100% of that day across the Points that have Leaves logged that day, proportional to how many Leaves each Point got. Multiplying by the day\'s hours gives each Point\'s "Done" hours; "Remaining" is the estimate minus Done.') }}</p>

						<h4>{{ t('projectmanager', 'Modules vs OTHERS') }}</h4>
						<p>{{ t('projectmanager', 'A module can count towards the estimate, or be marked OTHERS. OTHERS work is still tracked and shown, but kept separate from the original estimate — use it for extra requests, bug fixes, or anything outside what was originally planned.') }}</p>

						<h4>{{ t('projectmanager', 'Summary') }}</h4>
						<p>{{ t('projectmanager', 'The Summary card shows Estimated, Done and Remaining totals in hours and days (1 day = the project\'s hours-per-working-day, set in Project settings — hover a project in the sidebar and click its gear icon).') }}</p>

						<h4>{{ t('projectmanager', 'Features') }}</h4>
						<p>{{ t('projectmanager', 'The Features tab lists what was sold or proposed to the client, tracked against its current status. "Point" is an optional reference to a related Point code — it doesn\'t affect any calculation. "External pending" is for dependencies on someone else.') }}</p>

						<h4>{{ t('projectmanager', 'Tests') }}</h4>
						<p>{{ t('projectmanager', 'The Tests tab is a validation log: one row per scenario you tested, with the expected result and its outcome — use it to document what was checked before delivering.') }}</p>

						<h4>{{ t('projectmanager', 'Cost (optional)') }}</h4>
						<p>{{ t('projectmanager', 'In Project settings you can set an hourly rate and currency symbol; enabling "Show cost in the summary" adds a cost column computed as hours × rate.') }}</p>

						<h4>{{ t('projectmanager', 'Clients (optional)') }}</h4>
						<p>{{ t('projectmanager', 'Group projects under a Client using the "+ Client" button in the sidebar. A client can have its own hourly rate and currency: its projects use that currency, and use its rate unless they set their own — useful when the same client has projects billed at different rates.') }}</p>
						<p>{{ t('projectmanager', 'Click a client\'s name to see a summary combining Estimated, Done and Remaining hours (and cost) across all of its projects.') }}</p>
					</HelpButton>
					<BackupDialog />
					</div>
					<p class="app-version">v{{ appVersion }} · {{ appBuildDate }}</p>
				</div>
			</template>
		</NcAppNavigation>
		<NcAppContent>
			<router-view />
		</NcAppContent>
	</NcContent>
	<ClientSettingsDialog ref="clientSettingsDialog" />
</template>

<style scoped>
:deep(.app-navigation-entry.active .app-navigation-entry__name) {
	font-weight: bold;
}

.is-archived :deep(.app-navigation-entry__name) {
	opacity: 0.6;
	font-style: italic;
}

.link-btn {
	background: none;
	border: none;
	color: var(--color-primary-element);
	cursor: pointer;
	padding: 0 4px;
	font-size: 12px;
	white-space: nowrap;
}

.nav-footer {
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 0 8px;
}

.nav-footer-main {
	display: flex;
	align-items: center;
	gap: 4px;
}

.nav-footer-main :deep(.app-navigation-new) {
	flex: 1;
}

.app-version {
	margin: 0;
	padding: 2px 4px 4px;
	font-size: 11px;
	color: var(--color-text-maxcontrast);
}
</style>
