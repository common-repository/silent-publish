import { registerPlugin } from '@wordpress/plugins';
import { default as SilentPublish } from './components/silent-publish';

registerPlugin(
	'silent-publish',
	{
		render: SilentPublish,
	}
);
