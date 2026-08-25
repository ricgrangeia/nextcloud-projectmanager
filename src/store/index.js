import { reactive } from 'vue'
import api from '../api/client.js'

export const state = reactive({
	projects: [],
	loaded: false,
})

export async function loadProjects() {
	state.projects = await api.listProjects()
	state.loaded = true
}
