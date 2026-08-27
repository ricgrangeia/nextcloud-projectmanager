import { createAppConfig } from '@nextcloud/vite-config'
import { resolve, join } from 'path'
import { readFileSync } from 'fs'

const infoXml = readFileSync(resolve('appinfo/info.xml'), 'utf-8')
const appVersion = infoXml.match(/<version>([^<]+)<\/version>/)?.[1] ?? '0.0.0'

const baseConfig = createAppConfig({
	main: resolve(join('src', 'main.js')),
}, {
	createEmptyCSSEntryPoints: true,
})

export default async (env) => {
	const config = await baseConfig(env)
	const buildDate = new Date().toISOString().slice(0, 10)
	config.define = {
		...config.define,
		__APP_VERSION__: JSON.stringify(appVersion),
		__APP_BUILD_DATE__: JSON.stringify(buildDate),
	}
	return config
}
