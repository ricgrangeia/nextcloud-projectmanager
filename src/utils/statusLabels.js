import { t } from '@nextcloud/l10n'

const LABELS = {
	todo: () => t('projectmanager', 'To do'),
	in_progress: () => t('projectmanager', 'In progress'),
	partial: () => t('projectmanager', 'Partial'),
	done: () => t('projectmanager', 'Done'),
	not_started: () => t('projectmanager', 'Not started'),
	to_test: () => t('projectmanager', 'To test'),
	passed: () => t('projectmanager', 'Passed'),
	failed: () => t('projectmanager', 'Failed'),
}

export function statusLabel(status) {
	return LABELS[status] ? LABELS[status]() : status
}
