import './bootstrap';
import { initJobsTable } from './jobs-table';

// FilePond imports
import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';

// Register plugin(s)
FilePond.registerPlugin(FilePondPluginFileValidateType);

// Auto-initialize any file input with FilePond when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	// FilePond
	const input = document.querySelector('#pdf-file');
	if (input) {
		FilePond.create(input, {
			allowMultiple: true,
			acceptedFileTypes: ['application/pdf'],
			maxFiles: 20,
			labelIdle: 'Trascina i file qui o <span class="filepond--label-action">sfoglia</span>',
			server: {
				process: {
					url: '/upload/process',
					method: 'POST',
					headers: {
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
					},
					withCredentials: false,
					onload: (response) => response,
				},
				revert: null,
			},
		});
	}
	// Jobs Table
	initJobsTable();
});
