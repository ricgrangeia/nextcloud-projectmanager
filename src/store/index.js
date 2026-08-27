import { reactive } from 'vue'
import api from '../api/client.js'

export const state = reactive({
	projects: [],
	clients: [],
	loaded: false,
	showArchived: false,
})

export async function loadProjects() {
	state.projects = await api.listProjects(state.showArchived)
	state.loaded = true
}

export async function loadClients() {
	state.clients = await api.listClients()
}

export async function toggleShowArchived() {
	state.showArchived = !state.showArchived
	await loadProjects()
}
